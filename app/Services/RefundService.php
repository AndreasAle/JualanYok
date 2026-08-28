<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Payments\PaymentManager;
use App\Payments\PaymentProviderInterface;
use App\Payments\PaymentResult;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class RefundService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AffiliateService $affiliates,
        private readonly PaymentManager $payments,
        private readonly MarketplaceLedgerService $marketplaceLedger,
    ) {}

    public function request(Order $order, float $amount, ?string $reason, ?User $requester = null): Refund
    {
        if (! in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
            throw ValidationException::withMessages(['order' => 'Pesanan ini belum dibayar.']);
        }

        if ($order->refunds()->whereIn('status', ['REQUESTED', 'APPROVED'])->exists()) {
            throw ValidationException::withMessages(['order' => 'Masih ada pengajuan refund yang sedang diproses untuk pesanan ini.']);
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
            'order_status_before' => $order->status->value,
            'reason' => $reason,
        ]);

        $order->update(['status' => OrderStatus::RefundRequested]);

        return $refund;
    }

    /**
     * Accept the request. A real provider API may complete it immediately;
     * manual providers wait for a transfer reference from finance.
     */
    public function approve(Refund $refund, User $admin, ?string $note = null): Refund
    {
        $refund = DB::transaction(function () use ($refund, $admin, $note) {
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if (in_array($refund->status, ['COMPLETED', 'APPROVED'], true)) {
                return $refund;
            }

            if ($refund->status !== 'REQUESTED') {
                throw ValidationException::withMessages(['refund' => 'Pengajuan refund ini sudah ditutup.']);
            }

            $payment = $refund->payment ?: $refund->order->latestPayment;
            $provider = $payment ? $this->providerFor($payment) : null;
            $automatic = $provider?->supportsRefund() === true;

            $refund->update([
                'status' => 'APPROVED',
                'execution_mode' => $automatic ? 'AUTOMATIC' : 'MANUAL',
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'admin_note' => $note,
            ]);

            return $refund->fresh(['payment', 'order.latestPayment']);
        });

        if ($refund->execution_mode !== 'AUTOMATIC') {
            return $refund;
        }

        $payment = $refund->payment ?: $refund->order->latestPayment;
        $provider = $payment ? $this->providerFor($payment) : null;

        if (! $payment || ! $provider) {
            $refund->update(['execution_mode' => 'MANUAL']);

            return $refund->fresh();
        }

        $result = $provider->refund($payment, (float) $refund->amount, $refund->reason);
        $payment->attempts()->create([
            'action' => 'refund',
            'status' => $result->status->value,
            'response' => $result->toArray(),
            'error' => $result->error,
        ]);
        $refund->update([
            'provider_reference' => $result->reference,
            'provider_response' => $result->toArray(),
        ]);

        if (! in_array($result->status, [PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true) || $result->error) {
            // Do not blindly retry an external money movement. Finance verifies
            // it at the gateway, then records the real transfer reference.
            $refund->update(['execution_mode' => 'MANUAL']);
            throw ValidationException::withMessages([
                'refund' => 'Gateway belum mengonfirmasi refund. Periksa dashboard provider, kirim dana jika perlu, lalu konfirmasi dengan nomor referensi.',
            ]);
        }

        return $this->completeAccounting($refund, $admin, $note, $result->reference, $result);
    }

    public function completeManual(Refund $refund, User $admin, string $transferReference, ?string $note = null): Refund
    {
        $transferReference = trim($transferReference);
        if (mb_strlen($transferReference) < 4) {
            throw ValidationException::withMessages(['transfer_reference' => 'Masukkan nomor referensi transfer yang valid.']);
        }

        return $this->completeAccounting($refund, $admin, $note, $transferReference);
    }

    /** Complete wallet, affiliate, order, payment, access, and GL atomically. */
    private function completeAccounting(
        Refund $refund,
        User $admin,
        ?string $note,
        ?string $reference,
        ?PaymentResult $providerResult = null,
    ): Refund {
        return DB::transaction(function () use ($refund, $admin, $note, $reference, $providerResult) {
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status === 'COMPLETED') {
                return $refund;
            }
            if ($refund->status !== 'APPROVED') {
                throw ValidationException::withMessages(['refund' => 'Refund harus diterima finance sebelum diselesaikan.']);
            }

            $order = Order::whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
            $amount = Money::round((float) $refund->amount);
            if ($amount > $order->refundableAmount() + 0.001) {
                throw ValidationException::withMessages(['refund' => 'Nominal refund melampaui sisa yang bisa dikembalikan.']);
            }

            $refundedTotal = Money::round((float) $order->refunded_total + $amount);
            $ratio = (float) $order->grand_total > 0
                ? min(1.0, $refundedTotal / (float) $order->grand_total)
                : 1.0;
            $sellerClawback = Money::round(max(
                0,
                Money::round((float) $order->seller_net * $ratio) - $this->completedSum($order, 'seller_clawback'),
            ));
            $orderReserveRemaining = Money::round(max(
                0,
                (float) $order->reserve_amount - $this->completedSum($order, 'reserve_clawback'),
            ));

            $wallet = $this->ledger->walletFor($order->store->owner);
            $sellerAllocation = $this->ledger->clawback(
                wallet: $wallet,
                amount: $sellerClawback,
                type: LedgerEntryType::Refund,
                reference: $refund,
                description: 'Refund pesanan '.$order->number,
                idempotencyKey: 'refund-seller:'.$refund->id,
                reserveLimit: $orderReserveRemaining,
            );

            $affiliateReversal = $this->affiliates->reverseForOrder($order, 'Refund '.$refund->id, $ratio);
            $affiliateClawback = Money::round((float) $affiliateReversal['amount']);
            $platformFeeReversal = $this->incrementalRefundComponent($order, 'platform_fee', 'platform_fee_reversal', $ratio);
            $shippingReversal = $order->shipping_provider === 'biteship'
                ? $this->incrementalRefundComponent($order, 'shipping_total', 'shipping_reversal', $ratio)
                : 0.0;
            $taxReversal = $this->incrementalRefundComponent($order, 'tax_total', 'tax_reversal', $ratio);
            $platformLoss = Money::round(max(
                0,
                $amount - $sellerClawback - $affiliateClawback - $platformFeeReversal - $shippingReversal - $taxReversal,
            ));
            $isFull = $refundedTotal >= (float) $order->grand_total - 0.001;

            $order->update([
                'refunded_total' => $refundedTotal,
                'status' => $isFull ? OrderStatus::Refunded : OrderStatus::PartiallyRefunded,
                'payment_status' => $isFull ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
            ]);
            if ($isFull) {
                $order->digitalAccesses()->update(['is_revoked' => true]);
            }

            $payment = $refund->payment ?: $order->latestPayment;
            $payment?->update([
                'refunded_amount' => Money::round((float) $payment->refunded_amount + $amount),
                'status' => $isFull ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
            ]);

            $refund->update([
                'status' => 'COMPLETED',
                'seller_clawback' => $sellerClawback,
                'reserve_clawback' => $sellerAllocation['reserve'],
                'seller_debt_created' => $sellerAllocation['debt'],
                'affiliate_clawback' => $affiliateClawback,
                'affiliate_debt_created' => $affiliateReversal['debt'],
                'platform_fee_reversal' => $platformFeeReversal,
                'shipping_reversal' => $shippingReversal,
                'tax_reversal' => $taxReversal,
                'platform_loss' => $platformLoss,
                'transfer_reference' => $reference,
                'provider_reference' => $providerResult?->reference ?? $refund->provider_reference,
                'provider_response' => $providerResult?->toArray() ?? $refund->provider_response,
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'admin_note' => $note ?? $refund->admin_note,
            ]);

            $this->marketplaceLedger->recordRefund($refund->fresh('order.store.owner'));

            return $refund->fresh();
        });
    }

    public function reject(Refund $refund, User $admin, string $note): Refund
    {
        return DB::transaction(function () use ($refund, $admin, $note) {
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status !== 'REQUESTED') {
                throw ValidationException::withMessages(['refund' => 'Hanya pengajuan baru yang bisa ditolak.']);
            }

            $refund->update([
                'status' => 'REJECTED',
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'admin_note' => $note,
            ]);
            $refund->order->update([
                'status' => OrderStatus::tryFrom((string) $refund->order_status_before) ?? OrderStatus::Completed,
            ]);

            return $refund;
        });
    }

    private function providerFor(Payment $payment): ?PaymentProviderInterface
    {
        try {
            return $this->payments->driver($payment->provider);
        } catch (Throwable) {
            return null;
        }
    }

    private function completedSum(Order $order, string $column): float
    {
        return Money::round((float) $order->refunds()->where('status', 'COMPLETED')->sum($column));
    }

    private function incrementalRefundComponent(Order $order, string $orderColumn, string $refundColumn, float $ratio): float
    {
        $target = Money::round((float) $order->{$orderColumn} * $ratio);

        return Money::round(max(0, $target - $this->completedSum($order, $refundColumn)));
    }
}
