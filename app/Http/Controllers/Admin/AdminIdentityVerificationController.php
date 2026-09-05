<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdentityVerification;
use App\Services\AuditLogger;
use App\Services\NotificationCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reviewing identity checks.
 *
 * Finance only, because the queue shows a person's national ID number and two
 * photographs of them. Every decision is written to the audit log, so looking
 * at someone's identity is never anonymous.
 */
class AdminIdentityVerificationController extends Controller
{
    /** Long enough to read a page, short enough that a copied link dies fast. */
    private const LINK_TTL_MINUTES = 20;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationCenterService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        $rows = IdentityVerification::query()
            ->with(['user:id,name,username,email,phone', 'reviewer:id,name'])
            ->when(
                in_array($status, [IdentityVerification::PENDING, IdentityVerification::APPROVED, IdentityVerification::REJECTED], true),
                fn ($query) => $query->where('status', $status),
            )
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim()->toString().'%';

                $query->where(function ($search) use ($term) {
                    // Never the NIK: it is ciphertext, so a LIKE over it would
                    // match nothing and only suggest that it is searchable.
                    $search->where('full_name', 'like', $term)
                        ->orWhere('nik_last4', 'like', $term)
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term));
                });
            })
            ->orderByRaw("CASE status WHEN 'PENDING' THEN 0 WHEN 'REJECTED' THEN 1 ELSE 2 END")
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (IdentityVerification $row) => [
                'id' => $row->id,
                'user' => [
                    'name' => $row->user->name,
                    'email' => $row->user->email,
                    'phone' => $row->user->phone,
                ],
                'status' => $row->status,
                'status_label' => $row->statusLabel(),
                'full_name' => $row->full_name,
                // The decrypted number, on this page only. A reviewer cannot
                // check an ID card against a masked number.
                'nik' => $row->nik,
                'birth_place' => $row->birth_place,
                'birth_date' => $row->birth_date->format('d/m/Y'),
                'address' => $row->address,
                'id_card_url' => $this->documentLink($row, 'id_card'),
                'selfie_url' => $this->documentLink($row, 'selfie'),
                'consented_at' => $row->consented_at->toDateTimeString(),
                'consent_ip' => $row->consent_ip,
                'reviewer' => $row->reviewer?->name,
                'reviewed_at' => $row->reviewed_at?->toDateTimeString(),
                'rejection_reason' => $row->rejection_reason,
                'created_at' => $row->created_at->toDateTimeString(),
            ]);

        return Inertia::render('Admin/IdentityVerifications', [
            'verifications' => $rows,
            'filters' => $request->only(['status', 'q']),
            'stats' => [
                'pending' => IdentityVerification::where('status', IdentityVerification::PENDING)->count(),
                'approved' => IdentityVerification::where('status', IdentityVerification::APPROVED)->count(),
                'rejected' => IdentityVerification::where('status', IdentityVerification::REJECTED)->count(),
            ],
        ]);
    }

    public function approve(Request $request, IdentityVerification $verification)
    {
        DB::transaction(function () use ($request, $verification) {
            $row = IdentityVerification::query()->lockForUpdate()->findOrFail($verification->id);

            if ($row->isApproved()) {
                return;
            }

            $before = ['status' => $row->status];

            $row->forceFill([
                'status' => IdentityVerification::APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $this->audit->log('identity.approved', $row, before: $before, after: ['status' => $row->status]);

            $this->notifications->send($row->user, [
                'type' => 'identity.approved',
                'category' => 'finance',
                'priority' => 'normal',
                'title' => 'Identitas kamu terverifikasi',
                'message' => 'Penarikan dana sudah bisa kamu ajukan sekarang.',
                'url' => route('creator.withdrawals.index'),
                'action_label' => 'Tarik saldo',
                'group_key' => 'identity:'.$row->id,
                'tone' => 'success',
            ]);
        });

        return back()->with('success', 'Identitas diverifikasi. Creator sudah bisa menarik dana.');
    }

    public function reject(Request $request, IdentityVerification $verification)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $verification, $data) {
            $row = IdentityVerification::query()->lockForUpdate()->findOrFail($verification->id);
            $before = ['status' => $row->status];

            $row->forceFill([
                'status' => IdentityVerification::REJECTED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => trim($data['reason']),
            ])->save();

            $this->audit->log(
                'identity.rejected',
                $row,
                before: $before,
                after: ['status' => $row->status],
                reason: $row->rejection_reason,
            );

            $this->notifications->send($row->user, [
                'type' => 'identity.rejected',
                'category' => 'finance',
                'priority' => 'high',
                'title' => 'Verifikasi identitas perlu diperbaiki',
                'message' => $row->rejection_reason,
                'url' => route('creator.withdrawals.index'),
                'action_label' => 'Kirim ulang',
                'group_key' => 'identity:'.$row->id,
                'tone' => 'warning',
                'action_required' => true,
            ]);
        });

        return back()->with('success', 'Penolakan terkirim beserta alasannya.');
    }

    /**
     * Hand back one of the two photographs.
     *
     * Private disk, signed link, finance-only route, and the file streams
     * inline with `nosniff` so a doctored image cannot execute as a page from
     * our own origin.
     */
    public function document(Request $request, IdentityVerification $verification, string $kind): StreamedResponse
    {
        abort_unless(in_array($kind, ['id_card', 'selfie'], true), 404);
        abort_unless($request->hasValidSignature(), 403);

        $path = $kind === 'id_card' ? $verification->id_card_path : $verification->selfie_path;
        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        $this->audit->log('identity.document_viewed', $verification, after: ['kind' => $kind]);

        return $disk->response($path, null, [
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function documentLink(IdentityVerification $row, string $kind): string
    {
        return URL::temporarySignedRoute(
            'admin.identity.document',
            now()->addMinutes(self::LINK_TTL_MINUTES),
            ['verification' => $row->id, 'kind' => $kind],
        );
    }
}
