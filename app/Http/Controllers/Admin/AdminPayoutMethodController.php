<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayoutMethod;
use App\Notifications\PayoutMethodReviewed;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminPayoutMethodController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        $methods = PayoutMethod::query()
            ->with(['user:id,name,username,email,phone', 'reviewer:id,name'])
            ->when(in_array($status, ['unverified', 'verified', 'rejected'], true), fn ($query) => $query->where('status', $status))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim()->toString().'%';

                $query->where(function ($search) use ($term) {
                    $search->where('provider', 'like', $term)
                        ->orWhere('account_name', 'like', $term)
                        ->orWhere('account_number_last4', 'like', $term)
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('name', 'like', $term)
                            ->orWhere('username', 'like', $term)
                            ->orWhere('email', 'like', $term));
                });
            })
            ->orderByRaw("CASE status WHEN 'unverified' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END")
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PayoutMethod $method) => [
                'id' => $method->id,
                'user' => [
                    'name' => $method->user->name,
                    'username' => $method->user->username,
                    'email' => $method->user->email,
                    'phone' => $method->user->phone,
                ],
                'type' => $method->type,
                'provider' => $method->provider,
                'account_name' => $method->account_name,
                // This page is role-restricted. The number is encrypted at rest
                // and deliberately revealed only to finance during review.
                'account_number' => $method->account_number,
                'masked' => $method->maskedNumber(),
                'is_default' => (bool) $method->is_default,
                'status' => $method->status,
                'review_note' => $method->review_note,
                'reviewer' => $method->reviewer?->name,
                'reviewed_at' => $method->reviewed_at?->toDateTimeString(),
                'created_at' => $method->created_at->toDateTimeString(),
            ]);

        return Inertia::render('Admin/PayoutMethods', [
            'methods' => $methods,
            'filters' => $request->only(['status', 'q']),
            'stats' => [
                'pending' => PayoutMethod::where('status', 'unverified')->count(),
                'verified' => PayoutMethod::where('status', 'verified')->count(),
                'rejected' => PayoutMethod::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function approve(Request $request, PayoutMethod $payoutMethod)
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $payoutMethod, $data) {
            $method = PayoutMethod::query()->lockForUpdate()->findOrFail($payoutMethod->id);

            if ($method->status === 'verified') {
                return;
            }

            $before = $this->auditSnapshot($method);

            $method->forceFill([
                'status' => 'verified',
                'verified_at' => now(),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
            ])->save();

            $this->audit->log(
                'payout_method.verified',
                $method,
                before: $before,
                after: $this->auditSnapshot($method->fresh()),
                reason: $method->review_note,
            );

            $method->user->notify(new PayoutMethodReviewed($method));
        });

        return back()->with('success', 'Rekening pencairan berhasil diverifikasi.');
    }

    public function reject(Request $request, PayoutMethod $payoutMethod)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $payoutMethod, $data) {
            $method = PayoutMethod::query()->lockForUpdate()->findOrFail($payoutMethod->id);

            if ($method->status === 'verified') {
                throw ValidationException::withMessages([
                    'status' => 'Rekening terverifikasi tidak dapat ditolak dari antrean. Hubungi super admin jika perlu dibekukan.',
                ]);
            }

            $before = $this->auditSnapshot($method);

            $method->forceFill([
                'status' => 'rejected',
                'verified_at' => null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => trim($data['reason']),
            ])->save();

            $this->audit->log(
                'payout_method.rejected',
                $method,
                before: $before,
                after: $this->auditSnapshot($method->fresh()),
                reason: $method->review_note,
            );

            $method->user->notify(new PayoutMethodReviewed($method));
        });

        return back()->with('success', 'Rekening ditolak dan alasannya sudah dikirim ke pemilik.');
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(PayoutMethod $method): array
    {
        return [
            'status' => $method->status,
            'provider' => $method->provider,
            'account_number_last4' => $method->account_number_last4,
            'reviewed_by' => $method->reviewed_by,
            'reviewed_at' => $method->reviewed_at?->toDateTimeString(),
            'review_note' => $method->review_note,
        ];
    }
}
