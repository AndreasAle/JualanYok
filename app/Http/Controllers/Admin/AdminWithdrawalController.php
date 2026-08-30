<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Withdrawal;
use App\Services\AuditLogger;
use App\Services\NotificationCenterService;
use App\Services\WithdrawalService;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminWithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $withdrawals,
        private readonly AuditLogger $audit,
        private readonly NotificationCenterService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $withdrawals = Withdrawal::with(['user:id,name,username,email', 'payoutMethod'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where('number', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Withdrawal $w) => [
                'number' => $w->number,
                'user' => [
                    'name' => $w->user->name,
                    'username' => $w->user->username,
                    'email' => $w->user->email,
                ],
                'amount' => (float) $w->amount,
                'fee' => (float) $w->fee,
                'net_amount' => (float) $w->net_amount,
                'status' => $w->status->value,
                'status_label' => $w->status->label(),
                'is_open' => $w->status->isOpen(),
                'account' => $w->payout_snapshot,
                'account_verified' => $w->payoutMethod?->status === 'verified',
                'review_note' => $w->review_note,
                'created_at' => $w->created_at->toDateTimeString(),
                'paid_at' => $w->paid_at?->toDateTimeString(),
            ]);

        return Inertia::render('Admin/Withdrawals', [
            'withdrawals' => $withdrawals,
            'filters' => $request->only(['status', 'q']),
            'statuses' => collect(WithdrawalStatus::cases())
                ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'canProcess' => $request->user()->hasRole(Role::FINANCE_ADMIN, Role::SUPER_ADMIN),
        ]);
    }

    public function approve(Request $request, Withdrawal $withdrawal)
    {
        $this->ensureFinance($request);

        $note = $request->input('note');

        $withdrawal = $this->withdrawals->approve($withdrawal, $request->user(), $note);
        $this->audit->log('withdrawal.approved', $withdrawal, reason: $note);

        $this->notifyOwner($withdrawal, 'withdrawal.approved', 'Penarikan disetujui', "{$withdrawal->number} sedang disiapkan untuk ditransfer.", 'success');

        return back()->with('success', "Penarikan {$withdrawal->number} disetujui.");
    }

    public function reject(Request $request, Withdrawal $withdrawal)
    {
        $this->ensureFinance($request);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $withdrawal = $this->withdrawals->reverse($withdrawal, WithdrawalStatus::Rejected, $request->user(), $data['reason']);
        $this->audit->log('withdrawal.rejected', $withdrawal, reason: $data['reason']);

        $this->notifyOwner($withdrawal, 'withdrawal.rejected', 'Penarikan ditolak', $data['reason'], 'danger', true);

        return back()->with('success', 'Penarikan ditolak dan saldo dikembalikan.');
    }

    public function markPaid(Request $request, Withdrawal $withdrawal)
    {
        $this->ensureFinance($request);

        $data = $request->validate([
            'transfer_reference' => ['required', 'string', 'max:120'],
        ]);

        $withdrawal = $this->withdrawals->markPaid($withdrawal, $request->user(), $data['transfer_reference']);
        $this->audit->log('withdrawal.paid', $withdrawal, after: $data);

        $this->notifyOwner($withdrawal, 'withdrawal.paid', 'Dana penarikan sudah dikirim', "{$withdrawal->number} senilai ".Money::format((float) $withdrawal->net_amount).' sudah ditransfer.', 'success');

        return back()->with('success', "Penarikan {$withdrawal->number} ditandai sudah cair.");
    }

    /** Moving money is restricted to finance, even among admins. */
    private function ensureFinance(Request $request): void
    {
        abort_unless(
            $request->user()->hasRole(Role::FINANCE_ADMIN, Role::SUPER_ADMIN),
            403,
            'Hanya finance admin yang bisa memproses penarikan.',
        );
    }

    private function notifyOwner(Withdrawal $withdrawal, string $type, string $title, string $message, string $tone, bool $action = false): void
    {
        $this->notifications->send($withdrawal->user, [
            'type' => $type,
            'category' => 'finance',
            'priority' => 'high',
            'title' => $title,
            'message' => $message,
            'url' => route('creator.withdrawals.index'),
            'action_label' => 'Lihat penarikan',
            'action_required' => $action,
            'group_key' => 'withdrawal:'.$withdrawal->id,
            'tone' => $tone,
            'meta' => ['withdrawal_id' => $withdrawal->id],
        ]);
    }
}
