<?php

namespace App\Services;

use App\Enums\BalanceBucket;
use App\Enums\LedgerEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Payments\PaymentManager;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AffiliateService $affiliates,
        private readonly PaymentManager $payments,
    ) {}

    public function request(Order $order, float $amount, ?string $reason, ?User $requester = null): Refund
    {
        // A disputed physical order is intentionally no longer in a normal
        // settled status, but its payment is still captured and refundable.
        if (! in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
            throw ValidationException::withMessages(['order' => 'Pesanan ini belum dibayar.']);
        }

        $amount = Money::round($amount);

        if ($amount <= 0 || $amount > $order->refundableAmount() + 0.001) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal refund maksimal '.Money::format($order->refundableAmount()).'.',
            ]);
        }

        $refund = $order->refunds()->create([
            'payment_id' => $order->latestPayment?->id,
            'requested_by' => $requester?->id,
            'amount' => $amount,
            'status' => 'REQUESTED',
            'reason' => $reason,
        ]);

        $order->update(['status' => OrderStatus::RefundRequested]);

        return $refund;
    }

    /**
     * Approves and books a refund.
     *
     * The seller's share is clawed back from whichever bucket still holds it,
     * and any affiliate commission on the order is reversed, so a refund can
     * never leave the platform paying out money it gave back to the buyer.
     */
    public function approve(Refund $refund, User $admin, ?string $note = null): Refund
    {
        return DB::transaction(function () use ($refund, $admin, $note) {
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($refund->status === 'COMPLETED') {
                return $refund;
            }

            $order = Order::whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
            $amount = (float) $refund->amount;

            /*
             * Proportional share of the seller's net for a partial refund.
             *
             * Measured against the goods, not the grand total: the buyer's
             * gateway fee never reached the seller, so counting it here would
             * shrink the ratio and let the seller keep money on a refunded item.
             */
            $goodsTotal = Money::round(
                (float) $order->subtotal
                - (float) $order->discount_total
                + (float) $order->shipping_total
                + (float) $order->tax_total
            );

            $ratio = $goodsTotal > 0 ? min(1.0, $amount / $goodsTotal) : 1.0;
            $sellerClawback = Money::round((float) $order->seller_net * $ratio);

            $wallet = $this->ledger->walletFor($order->store->owner);

            $pending = (float) $wallet->pending_balance;
            $fromPending = min($sellerClawback, max(0, $pending));
            $fromAvailable = Money::round($sellerClawback - $fromPending);

            if ($fromPending > 0) {
                $this->ledger->record(
                    wallet: $wallet,
                    type: LedgerEntryType::Refund,
                    bucket: BalanceBucket::Pending,
                    amount: -$fromPending,
                    reference: $refund,
                    description: 'Refund pesanan '.$order->number,
                    idempotencyKey: 'refund-pending:'.$refund->id,
                );
            }

            if ($fromAvailable > 0) {
                $this->ledger->record(
                    wallet: $wallet,
                    type: LedgerEntryType::Refund,
                    bucket: BalanceBucket::Available,
                    amount: -$fromAvailable,
                    reference: $refund,
                    description: 'Refund pesanan '.$order->number,
                    idempotencyKey: 'refund-available:'.$refund->id,
                );
            }

            $this->affiliates->reverseForOrder($order, 'Refund '.$refund->id);

            $refundedTotal = Money::round((float) $order->refunded_total + $amount);
            $isFull = $refundedTotal >= (float) $order->grand_total - 0.001;

            $order->update([
                'refunded_total' => $refundedTotal,
                'status' => $isFull ? OrderStatus::Refunded : OrderStatus::PartiallyRefunded,
                'payment_status' => $isFull ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
            ]);

            // Revoke delivered digital goods on a full refund.
            if ($isFull) {
                $order->digitalAccesses()->update(['is_revoked' => true]);
            }

            $payment = $order->latestPayment;
            $provider = $payment ? $this->payments->driver($payment->provider) : null;

            if ($payment && $provider?->supportsRefund()) {
                $result = $provider->refund($payment, $amount, $refund->reason);

                $payment->attempts()->create([
                    'action' => 'refund',
                    'status' => $result->status->value,
                    'response' => $result->toArray(),
                    'error' => $result->error,
                ]);

                $payment->update([
                    'refunded_amount' => Money::round((float) $payment->refunded_amount + $amount),
                    'status' => $isFull ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
                ]);
            }

            $refund->update([
                'status' => 'COMPLETED',
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'admin_note' => $note,
            ]);

            return $refund;
        });
    }

    public function reject(Refund $refund, User $admin, string $note): Refund
    {
        $refund->update([
            'status' => 'REJECTED',
            'processed_by' => $admin->id,
            'processed_at' => now(),
            'admin_note' => $note,
        ]);

        $refund->order->update(['status' => OrderStatus::Completed]);

        return $refund;
    }
}
