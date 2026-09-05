<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\IdentityVerification;
use App\Models\PayoutMethod;
use App\Models\Role;
use App\Models\Withdrawal;
use App\Services\NotificationCenterService;
use App\Services\WithdrawalService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $withdrawals,
        private readonly NotificationCenterService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $wallet = $user->walletOrCreate();

        return Inertia::render('Creator/Withdrawals', [
            'wallet' => [
                'available' => (float) $wallet->available_balance,
                'pending' => (float) $wallet->pending_balance,
                'held' => (float) $wallet->held_balance,
                'reserve' => (float) $wallet->reserve_balance,
                'negative' => (float) $wallet->negative_balance,
                'is_frozen' => (bool) $wallet->is_frozen,
            ],
            'config' => [
                'minimum' => $this->withdrawals->minimumAmount(),
                'fee' => $this->withdrawals->fee(),
            ],
            /*
             * Payouts move real money to a named person, so the platform has to
             * know who that person is. The page needs the state of that check
             * to explain itself rather than just refusing.
             */
            'identity' => ($kyc = IdentityVerification::where('user_id', $user->id)->first())
                ? [
                    'status' => $kyc->status,
                    'status_label' => $kyc->statusLabel(),
                    'full_name' => $kyc->full_name,
                    'masked_nik' => $kyc->maskedNik(),
                    'rejection_reason' => $kyc->rejection_reason,
                    'submitted_at' => $kyc->created_at->translatedFormat('d M Y'),
                ]
                : null,
            'payoutMethods' => $user->payoutMethods()->get()->map(fn (PayoutMethod $m) => [
                'id' => $m->id,
                'type' => $m->type,
                'provider' => $m->provider,
                'account_name' => $m->account_name,
                'masked' => $m->maskedNumber(),
                'status' => $m->status,
                'is_default' => (bool) $m->is_default,
                'review_note' => $m->review_note,
                'reviewed_at' => $m->reviewed_at?->toDateTimeString(),
            ]),
            'withdrawals' => $user->withdrawals()
                ->latest()
                ->paginate(10)
                ->through(fn (Withdrawal $w) => [
                    'number' => $w->number,
                    'amount' => (float) $w->amount,
                    'fee' => (float) $w->fee,
                    'net_amount' => (float) $w->net_amount,
                    'status' => $w->status->value,
                    'status_label' => $w->status->label(),
                    'can_cancel' => $w->status->isCancellableByOwner(),
                    'account' => $w->payout_snapshot,
                    'review_note' => $w->review_note,
                    'created_at' => $w->created_at->toDateTimeString(),
                    'paid_at' => $w->paid_at?->toDateTimeString(),
                ]),
        ]);
    }

    public function store(Request $request)
    {
        /*
         * Identity first.
         *
         * This is the point where money leaves the platform and lands in a
         * named bank account. Knowing who that person is protects the buyers
         * whose payments funded the balance, and it is the ordinary
         * expectation of anyone moving other people's money.
         */
        $identity = IdentityVerification::where('user_id', $request->user()->id)->first();

        if (! $identity?->isApproved()) {
            throw ValidationException::withMessages([
                'amount' => $identity === null
                    ? 'Lengkapi verifikasi identitas dulu sebelum menarik dana.'
                    : ($identity->status === IdentityVerification::PENDING
                        ? 'Verifikasi identitasmu masih ditinjau. Kami kabari lewat email begitu selesai.'
                        : 'Verifikasi identitasmu ditolak. Perbaiki datanya lalu kirim ulang.'),
            ]);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'payout_method_id' => ['required', 'exists:payout_methods,id'],
        ]);

        $method = PayoutMethod::whereKey($data['payout_method_id'])->firstOrFail();

        $withdrawal = $this->withdrawals->request($request->user(), (float) $data['amount'], $method);

        $this->notifications->send($request->user(), [
            'type' => 'withdrawal.requested',
            'category' => 'finance',
            'priority' => 'normal',
            'title' => 'Penarikan berhasil diajukan',
            'message' => "{$withdrawal->number} senilai ".Money::format((float) $withdrawal->net_amount).' sedang diperiksa tim finance.',
            'url' => route('creator.withdrawals.index'),
            'action_label' => 'Lihat penarikan',
            'group_key' => 'withdrawal:'.$withdrawal->id,
            'tone' => 'info',
            'meta' => ['withdrawal_id' => $withdrawal->id],
        ]);

        $this->notifications->sendToAdmins([Role::FINANCE_ADMIN, Role::SUPER_ADMIN], [
            'type' => 'withdrawal.review_requested',
            'category' => 'finance',
            'priority' => 'high',
            'title' => 'Penarikan menunggu pemeriksaan',
            'message' => "{$request->user()->name} mengajukan {$withdrawal->number} senilai ".Money::format((float) $withdrawal->amount).'.',
            'url' => route('admin.withdrawals.index', ['status' => 'REQUESTED']),
            'action_label' => 'Proses penarikan',
            'action_required' => true,
            'group_key' => 'finance:withdrawals:pending',
            'tone' => 'warning',
            'meta' => ['withdrawal_id' => $withdrawal->id],
        ]);

        return back()->with('success', "Penarikan {$withdrawal->number} diajukan. Kami proses maksimal 2 hari kerja.");
    }

    public function cancel(Request $request, Withdrawal $withdrawal)
    {
        $this->withdrawals->cancelByOwner($withdrawal, $request->user());

        return back()->with('success', 'Penarikan dibatalkan, saldo sudah dikembalikan.');
    }
}
