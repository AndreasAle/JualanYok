<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\PayoutMethod;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    public function __construct(private readonly WithdrawalService $withdrawals) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $wallet = $user->walletOrCreate();

        return Inertia::render('Creator/Withdrawals', [
            'wallet' => [
                'available' => (float) $wallet->available_balance,
                'pending' => (float) $wallet->pending_balance,
                'held' => (float) $wallet->held_balance,
                'is_frozen' => (bool) $wallet->is_frozen,
            ],
            'config' => [
                'minimum' => $this->withdrawals->minimumAmount(),
                'fee' => $this->withdrawals->fee(),
            ],
            'payoutMethods' => $user->payoutMethods()->get()->map(fn (PayoutMethod $m) => [
                'id' => $m->id,
                'type' => $m->type,
                'provider' => $m->provider,
                'account_name' => $m->account_name,
                'masked' => $m->maskedNumber(),
                'status' => $m->status,
                'is_default' => (bool) $m->is_default,
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
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'payout_method_id' => ['required', 'exists:payout_methods,id'],
        ]);

        $method = PayoutMethod::whereKey($data['payout_method_id'])->firstOrFail();

        $withdrawal = $this->withdrawals->request($request->user(), (float) $data['amount'], $method);

        return back()->with('success', "Penarikan {$withdrawal->number} diajukan. Kami proses maksimal 2 hari kerja.");
    }

    public function cancel(Request $request, Withdrawal $withdrawal)
    {
        $this->withdrawals->cancelByOwner($withdrawal, $request->user());

        return back()->with('success', 'Penarikan dibatalkan, saldo sudah dikembalikan.');
    }
}
