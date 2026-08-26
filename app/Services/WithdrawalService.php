<?php

namespace App\Services;

use App\Enums\BalanceBucket;
use App\Enums\LedgerEntryType;
use App\Enums\WithdrawalStatus;
use App\Models\Order;
use App\Models\PayoutMethod;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Withdrawal lifecycle.
 *
 * Money is moved out of `available` into `held` the moment a request is made,
 * inside the same locked transaction that creates the request. That is what
 * makes a double withdrawal impossible: the second request finds the funds
 * already held and fails the balance check.
 */
class WithdrawalService
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function minimumAmount(): float
    {
        return (float) PlatformSetting::get('withdrawal.minimum', config('jualanyok.fees.minimum_withdrawal'));
    }

    public function fee(): float
    {
        return (float) PlatformSetting::get('withdrawal.fee', config('jualanyok.fees.withdrawal_fee'));
    }

    public function request(User $user, float $amount, PayoutMethod $method): Withdrawal
    {
        $amount = Money::round($amount);

        if ($method->user_id !== $user->id) {
            throw ValidationException::withMessages(['payout_method_id' => 'Rekening tujuan tidak valid.']);
        }

        if ($method->status !== 'verified') {
            throw ValidationException::withMessages(['payout_method_id' => 'Rekening ini belum diverifikasi.']);
        }

        $minimum = $this->minimumAmount();

        if (! Money::isAtLeast($amount, $minimum)) {
            throw ValidationException::withMessages([
                'amount' => 'Minimal penarikan '.Money::format($minimum).'.',
            ]);
        }

        return DB::transaction(function () use ($user, $amount, $method) {
            $wallet = $this->ledger->walletFor($user);

            if ($wallet->is_frozen) {
                throw ValidationException::withMessages([
                    'amount' => 'Saldo kamu sedang ditahan. Hubungi support ya.',
                ]);
            }

            $fee = $this->fee();

            if ($amount + $fee > (float) $wallet->fresh()->available_balance + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => 'Saldo tersedia tidak cukup (termasuk biaya admin '.Money::format($fee).').',
                ]);
            }

            $withdrawal = Withdrawal::create([
                'number' => Withdrawal::generateNumber(),
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'payout_method_id' => $method->id,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => Money::round($amount - $fee),
                'currency' => $wallet->currency,
                'status' => WithdrawalStatus::Requested,
                'payout_snapshot' => [
                    'provider' => $method->provider,
                    'account_name' => $method->account_name,
                    'masked' => $method->maskedNumber(),
                    'type' => $method->type,
                ],
            ]);

            // Hold the funds immediately — LedgerService refuses to take the
            // available bucket below zero, so a concurrent request cannot also
            // reserve the same money.
            $this->ledger->move(
                wallet: $wallet,
                from: BalanceBucket::Available,
                to: BalanceBucket::Held,
                amount: $amount,
                type: LedgerEntryType::Withdrawal,
                reference: $withdrawal,
                description: 'Penarikan '.$withdrawal->number,
                idempotencyKey: 'withdrawal-hold:'.$withdrawal->id,
            );

            return $withdrawal;
        });
    }

    public function approve(Withdrawal $withdrawal, User $admin, ?string $note = null): Withdrawal
    {
        return $this->transition($withdrawal, WithdrawalStatus::Approved, $admin, $note);
    }

    public function markProcessing(Withdrawal $withdrawal, User $admin): Withdrawal
    {
        return $this->transition($withdrawal, WithdrawalStatus::Processing, $admin);
    }

    /** Final settlement: held funds leave the wallet, fee is booked. */
    public function markPaid(Withdrawal $withdrawal, User $admin, ?string $transferReference = null): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $admin, $transferReference) {
            $withdrawal = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($withdrawal->status === WithdrawalStatus::Paid) {
                return $withdrawal;
            }

            if (! $withdrawal->status->isOpen()) {
                throw ValidationException::withMessages([
                    'status' => 'Penarikan ini sudah tidak aktif.',
                ]);
            }

            $wallet = $withdrawal->wallet;

            $this->ledger->record(
                wallet: $wallet,
                type: LedgerEntryType::Withdrawal,
                bucket: BalanceBucket::Held,
                amount: -(float) $withdrawal->amount,
                reference: $withdrawal,
                description: 'Dana cair '.$withdrawal->number,
                idempotencyKey: 'withdrawal-paid:'.$withdrawal->id,
            );

            $this->ledger->record(
                wallet: $wallet,
                type: LedgerEntryType::Withdrawal,
                bucket: BalanceBucket::Withdrawn,
                amount: (float) $withdrawal->amount,
                reference: $withdrawal,
                description: 'Total penarikan '.$withdrawal->number,
                idempotencyKey: 'withdrawal-total:'.$withdrawal->id,
            );

            $withdrawal->update([
                'status' => WithdrawalStatus::Paid,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'transfer_reference' => $transferReference,
                'paid_at' => now(),
            ]);

            return $withdrawal;
        });
    }

    /** Rejection, admin cancellation and gateway failure all return the hold. */
    public function reverse(Withdrawal $withdrawal, WithdrawalStatus $status, ?User $actor = null, ?string $reason = null): Withdrawal
    {
        if (! $status->isReversal()) {
            throw ValidationException::withMessages(['status' => 'Status pembatalan tidak valid.']);
        }

        return DB::transaction(function () use ($withdrawal, $status, $actor, $reason) {
            $withdrawal = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if (! $withdrawal->status->isOpen()) {
                throw ValidationException::withMessages([
                    'status' => 'Penarikan ini sudah selesai diproses.',
                ]);
            }

            $this->ledger->move(
                wallet: $withdrawal->wallet,
                from: BalanceBucket::Held,
                to: BalanceBucket::Available,
                amount: (float) $withdrawal->amount,
                type: LedgerEntryType::WithdrawalReversal,
                reference: $withdrawal,
                description: 'Pengembalian dana '.$withdrawal->number,
                idempotencyKey: 'withdrawal-reverse:'.$withdrawal->id,
            );

            $withdrawal->update([
                'status' => $status,
                'reviewed_by' => $actor?->id,
                'reviewed_at' => now(),
                'review_note' => $reason,
            ]);

            return $withdrawal;
        });
    }

    /** Owner-initiated cancellation while the request is still untouched. */
    public function cancelByOwner(Withdrawal $withdrawal, User $user): Withdrawal
    {
        if ($withdrawal->user_id !== $user->id) {
            abort(403);
        }

        if (! $withdrawal->status->isCancellableByOwner()) {
            throw ValidationException::withMessages([
                'status' => 'Penarikan ini sudah diproses, tidak bisa dibatalkan.',
            ]);
        }

        return $this->reverse($withdrawal, WithdrawalStatus::Cancelled, $user, 'Dibatalkan oleh pengguna.');
    }

    private function transition(Withdrawal $withdrawal, WithdrawalStatus $status, User $admin, ?string $note = null): Withdrawal
    {
        if (! $withdrawal->status->isOpen()) {
            throw ValidationException::withMessages(['status' => 'Penarikan ini sudah selesai diproses.']);
        }

        $withdrawal->update([
            'status' => $status,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_note' => $note ?? $withdrawal->review_note,
        ]);

        return $withdrawal;
    }

    /**
     * Matures seller revenue: pending → available once the holding period has
     * elapsed and the order is past its refund window.
     */
    public function releaseMaturedRevenue(): int
    {
        $holdDays = (int) PlatformSetting::get('withdrawal.holding_days', config('jualanyok.holding_period_days'));
        $cutoff = now()->subDays($holdDays);
        $released = 0;

        Order::paid()
            ->where(function ($query) use ($cutoff) {
                $query->where('funds_release_at', '<=', now())
                    // Compatibility for old, non-physical orders made before
                    // funds_release_at existed.
                    ->orWhere(function ($legacy) use ($cutoff) {
                        $legacy->whereNull('funds_release_at')
                            ->where('paid_at', '<=', $cutoff)
                            ->whereDoesntHave('items', fn ($items) => $items->where('product_type', 'PHYSICAL'));
                    });
            })
            ->whereDoesntHave('refunds', fn ($q) => $q->whereIn('status', ['REQUESTED', 'APPROVED', 'COMPLETED']))
            ->whereDoesntHave('disputes', fn ($q) => $q->whereIn('status', ['OPEN', 'SELLER_RESPONDED', 'UNDER_REVIEW']))
            ->with('store.owner')
            ->chunkById(100, function ($orders) use (&$released) {
                foreach ($orders as $order) {
                    $amount = (float) $order->seller_net;

                    if ($amount <= 0) {
                        continue;
                    }

                    $wallet = $this->ledger->walletFor($order->store->owner);

                    try {
                        $this->ledger->move(
                            wallet: $wallet,
                            from: BalanceBucket::Pending,
                            to: BalanceBucket::Available,
                            amount: $amount,
                            type: LedgerEntryType::Release,
                            reference: $order,
                            description: 'Dana penjualan '.$order->number.' cair',
                            idempotencyKey: 'order-release:'.$order->id,
                        );
                        $released++;
                    } catch (\Throwable) {
                        // Already released, or the pending bucket has been
                        // reduced by a refund — skip and keep going.
                    }
                }
            });

        return $released;
    }
}
