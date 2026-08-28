<?php

namespace App\Services;

use App\Enums\BalanceBucket;
use App\Enums\FulfillmentStatus;
use App\Enums\LedgerEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Payments\PaymentManager;
use App\Payments\PaymentResult;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PaymentService
{
    public function __construct(
        private readonly PaymentManager $manager,
        private readonly LedgerService $ledger,
        private readonly AffiliateService $affiliates,
        private readonly PaymentEconomicsService $economics,
        private readonly MarketplaceLedgerService $marketplaceLedger,
    ) {}

    /**
     * Opens a payment for an order. If an unexpired payment already exists for
     * the same method it is reused, so refreshing the checkout page does not
     * create duplicate charges.
     */
    public function createPayment(Order $order, string $providerKey, string $method, ?string $channel = null): Payment
    {
        if (! $order->isPayable()) {
            throw ValidationException::withMessages([
                'order' => 'Pesanan ini sudah tidak bisa dibayar.',
            ]);
        }

        $methodConfig = $this->manager->findMethod($providerKey, $method, $channel, (float) $order->grand_total);

        if (! $methodConfig) {
            throw ValidationException::withMessages(['method' => 'Metode pembayaran tidak tersedia.']);
        }

        if (($methodConfig['economically_available'] ?? true) === false) {
            throw ValidationException::withMessages([
                'method' => 'Metode ini tidak efisien untuk nominal pesanan tersebut. Pilih metode yang direkomendasikan.',
            ]);
        }

        return DB::transaction(function () use ($order, $providerKey, $method, $channel, $methodConfig) {
            $existing = $order->payments()
                ->where('provider', $providerKey)
                ->where('method', $method)
                ->where('channel', $channel)
                ->where('status', PaymentStatus::Pending->value)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->isOpen()) {
                return $existing;
            }

            // The gateway fee depends on the method, which is only known now.
            // Without this the buyer sees "Total bayar" including the fee but is
            // charged the pre-fee total — harmless with a mock gateway, a real
            // mismatch the moment an exact amount has to be paid.
            $this->applyPaymentFee($order, $methodConfig);

            $payment = $order->payments()->create([
                'provider' => $providerKey,
                'method' => $method,
                'channel' => $channel,
                'status' => PaymentStatus::Pending,
                'amount' => $order->grand_total,
                'fee_estimated' => $methodConfig['processing_fee_estimate'] ?? 0,
                'fee_source' => 'ESTIMATE',
                'settlement_days' => $methodConfig['settlement_days'] ?? 0,
                'currency' => $order->currency,
            ]);

            $provider = $this->manager->driver($providerKey);
            $result = $provider->createPayment($payment);

            $payment->fill([
                'reference' => $result->reference,
                'fee' => $result->fee ?? 0,
                'fee_source' => $result->fee !== null ? 'PROVIDER' : 'ESTIMATE',
                'instructions' => $result->instructions,
                'redirect_url' => $result->redirectUrl,
                'expires_at' => $result->expiresAt ?? now()->addHours(24),
                'status' => $result->status,
            ])->save();

            $payment->attempts()->create([
                'action' => 'create',
                'status' => $result->status->value,
                'response' => $result->toArray(),
                'error' => $result->error,
            ]);

            return $payment;
        });
    }

    /**
     * Recomputes the order total for the chosen payment method.
     *
     * Written from the untouched components rather than by adding to the running
     * total, so switching methods twice cannot stack two fees.
     */
    private function applyPaymentFee(Order $order, array $methodConfig): void
    {
        $beforeFee = Money::round(
            (float) $order->subtotal
            - (float) $order->discount_total
            + (float) $order->shipping_total
            + (float) $order->tax_total
        );

        $estimated = Money::round((float) ($methodConfig['processing_fee_estimate'] ?? 0));
        $bearer = strtoupper((string) ($methodConfig['fee_bearer'] ?? config('marketplace.gateway_fee_bearer', 'SELLER')));
        // QRIS MDR may not be surcharged to the buyer. Seller is therefore
        // enforced even if a bad environment value says otherwise.
        if (($methodConfig['method'] ?? null) === 'qris') {
            $bearer = 'SELLER';
        }
        $buyerFee = $bearer === 'BUYER' ? $estimated : 0.0;

        $order->forceFill([
            'payment_fee' => $buyerFee,
            'gateway_fee_estimated' => $estimated,
            'gateway_fee_bearer' => $bearer,
            'grand_total' => Money::round($beforeFee + $buyerFee),
        ])->save();
    }

    /**
     * Entry point for gateway callbacks.
     *
     * Every callback is logged raw before anything else, the signature is
     * verified, and the (provider, event_id) unique index makes replays a
     * no-op. Only then is the payment settled.
     */
    public function handleWebhook(string $providerKey, Request $request): array
    {
        $provider = $this->manager->driver($providerKey);

        $valid = $provider->verifyWebhook($request);
        $result = $valid ? $provider->parseWebhook($request) : null;

        $log = PaymentWebhook::firstOrNew([
            'provider' => $providerKey,
            'event_id' => $result?->eventId ?? hash('sha256', $request->getContent()),
        ]);

        // Already handled — replay is a success, not a duplicate settlement.
        if ($log->exists && $log->processed) {
            return ['status' => 'duplicate', 'message' => 'Callback sudah diproses.'];
        }

        $log->fill([
            'reference' => $result?->reference,
            'headers' => collect($request->headers->all())
                ->except(['authorization', 'cookie'])
                ->all(),
            'payload' => $request->getContent(),
            'signature_valid' => $valid,
        ])->save();

        if (! $valid) {
            $log->update(['error' => 'Signature tidak valid.']);
            Log::warning('payment.webhook.invalid_signature', ['provider' => $providerKey]);

            return ['status' => 'invalid', 'message' => 'Signature tidak valid.'];
        }

        try {
            $this->applyResult($result, $providerKey);
            $log->update(['processed' => true, 'processed_at' => now()]);

            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            $log->update(['error' => $e->getMessage()]);
            Log::error('payment.webhook.failed', ['provider' => $providerKey, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    /** Applies a normalised gateway result to the local payment record. */
    public function applyResult(PaymentResult $result, string $providerKey): ?Payment
    {
        $payment = Payment::where('provider', $providerKey)
            ->where('reference', $result->reference)
            ->first();

        if (! $payment) {
            $planPayment = app(PlanPaymentService::class)->applyGatewayResult($result, $providerKey);

            if ($planPayment) {
                return null;
            }

            Log::warning('payment.webhook.unknown_reference', [
                'provider' => $providerKey,
                'reference' => $result->reference,
            ]);

            return null;
        }

        $payment->attempts()->create([
            'action' => 'callback',
            'status' => $result->status->value,
            'response' => $result->toArray(),
            'error' => $result->error,
        ]);

        return $this->transitionPayment($payment, $result);
    }

    /**
     * Reconciles a local payment against the provider. This is the recovery
     * path when a valid gateway callback is delayed or never reaches us.
     */
    public function syncStatus(Payment $payment): Payment
    {
        $payment->refresh();
        $provider = $this->manager->driver($payment->provider);
        $result = $provider->checkStatus($payment);

        $statusChanged = $result->status !== $payment->status;
        $recentIdenticalCheck = $payment->attempts()
            ->where('action', 'status_check')
            ->where('status', $result->status->value)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($statusChanged || filled($result->error) || ! $recentIdenticalCheck) {
            $payment->attempts()->create([
                'action' => 'status_check',
                'status' => $result->status->value,
                'response' => $result->toArray(),
                'error' => $result->error,
            ]);
        }

        return $this->transitionPayment($payment, $result);
    }

    private function transitionPayment(Payment $payment, PaymentResult $result): Payment
    {

        return match (true) {
            $result->isPaid() => $this->markPaid($payment, $result),
            $result->status === PaymentStatus::Expired => $this->markExpired($payment),
            $result->status === PaymentStatus::Failed => $this->markFailed($payment),
            default => $payment,
        };
    }

    /**
     * Settles a paid payment: verifies the amount, flips the order, commits
     * stock, writes the ledger, and fires OrderPaid for the side effects.
     *
     * Runs inside one transaction with the payment row locked, so two
     * concurrent callbacks cannot both settle the same payment.
     */
    public function markPaid(Payment $payment, PaymentResult $result): Payment
    {
        return DB::transaction(function () use ($payment, $result) {
            /** @var Payment $payment */
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatus::Paid) {
                return $payment;   // Already settled.
            }

            // Never trust an amount that disagrees with what we asked for.
            if ($result->amount !== null && ! Money::equals((float) $payment->amount, $result->amount)) {
                throw new RuntimeException(sprintf(
                    'Nominal callback (%s) tidak cocok dengan tagihan (%s).',
                    Money::format($result->amount),
                    Money::format((float) $payment->amount),
                ));
            }

            $settledFee = $this->economics->settledFee($payment, $result->fee);

            $payment->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => $result->paidAt ?? now(),
                'fee' => $settledFee['amount'],
                'fee_source' => $settledFee['source'],
            ]);

            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            if (! $order->status->isSettled()) {
                $order->update([
                    'status' => OrderStatus::Paid,
                    'payment_status' => PaymentStatus::Paid,
                    'paid_at' => $payment->paid_at,
                ]);

                $this->commitStock($order);
                $this->creditSeller($order, $payment->fresh());
                $this->affiliates->recordForPaidOrder($order);
                $this->recordCouponUsage($order);
                $this->updateCustomerStats($order);

                // Fulfilment, receipts, webhooks and analytics all hang off
                // this event and run on the queue.
                event(new OrderPaid($order->fresh(['items', 'store', 'customer'])));
            }

            return $payment->fresh();
        });
    }

    /** Converts reservations into actual stock decrements. */
    private function commitStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_type !== 'PHYSICAL') {
                continue;
            }

            $inventory = Inventory::where('product_id', $item->product_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                continue;
            }

            $inventory->quantity = max(0, $inventory->quantity - $item->quantity);
            $inventory->reserved = max(0, $inventory->reserved - $item->quantity);
            $inventory->save();

            $inventory->movements()->create([
                'change' => -$item->quantity,
                'balance_after' => $inventory->quantity,
                'reason' => 'sale',
                'reference_type' => $item->getMorphClass(),
                'reference_id' => $item->id,
            ]);

            if ($item->product_variant_id) {
                ProductVariant::whereKey($item->product_variant_id)
                    ->update(['stock' => $inventory->quantity]);
            }
        }

        foreach ($order->items->groupBy('product_id') as $productId => $items) {
            Product::whereKey($productId)->increment('sales_count', $items->sum('quantity'));
        }
    }

    /**
     * Books the sale on the ledger: gross in, fees out, net to the seller's
     * pending bucket where it matures after the holding period.
     */
    private function creditSeller(Order $order, Payment $payment): void
    {
        $wallet = $this->ledger->walletFor($order->store->owner);

        $gatewayFee = Money::round((float) $payment->fee);
        $sellerGross = Money::round(
            (float) $order->commission_base
            + ($order->shipping_provider === 'biteship' ? 0 : (float) $order->shipping_total)
            - (float) $order->platform_fee
            - (float) $order->affiliate_commission
        );
        $gatewayChargedToSeller = $order->gateway_fee_bearer === 'SELLER'
            ? min(max(0, $sellerGross), $gatewayFee)
            : 0.0;
        $gatewaySubsidy = Money::round(max(0, $gatewayFee - $gatewayChargedToSeller));

        $sellerNet = Money::round(
            max(0, $sellerGross - $gatewayChargedToSeller)
        );

        $debtOffset = min($sellerNet, max(0, (float) $wallet->negative_balance));
        $creditable = Money::round($sellerNet - $debtOffset);
        $reserveRate = $this->reserveRate($order);
        $reserveAmount = Money::percent($creditable, $reserveRate);
        $pendingAmount = Money::round($creditable - $reserveAmount);

        if ($pendingAmount > 0) {
            $this->ledger->record(
                wallet: $wallet,
                type: LedgerEntryType::SellerRevenue,
                bucket: BalanceBucket::Pending,
                amount: $pendingAmount,
                reference: $order,
                description: 'Penjualan '.$order->number,
                idempotencyKey: 'order-revenue:'.$order->id,
                meta: [
                    'gross' => (float) $order->grand_total,
                    'commission_base' => (float) $order->commission_base,
                    'platform_fee' => (float) $order->platform_fee,
                    'gateway_fee' => $gatewayFee,
                    'gateway_fee_source' => $payment->fee_source,
                    'affiliate_commission' => (float) $order->affiliate_commission,
                ],
            );
        }

        if ($reserveAmount > 0) {
            $this->ledger->record(
                wallet: $wallet,
                type: LedgerEntryType::Reserve,
                bucket: BalanceBucket::Reserve,
                amount: $reserveAmount,
                reference: $order,
                description: 'Cadangan risiko '.$order->number,
                idempotencyKey: 'order-reserve:'.$order->id,
            );
        }

        if ($debtOffset > 0) {
            $this->ledger->record(
                wallet: $wallet,
                type: LedgerEntryType::DebtRecovery,
                bucket: BalanceBucket::Negative,
                amount: -$debtOffset,
                reference: $order,
                description: 'Pemulihan saldo negatif dari '.$order->number,
                idempotencyKey: 'order-debt-offset:'.$order->id,
            );
        }

        $contribution = Money::round(
            (float) $order->platform_fee
            - $gatewaySubsidy
            - (float) $order->split_fee_actual
            + (float) $order->shipping_variance
        );

        $order->update([
            'gateway_fee_actual' => $gatewayFee,
            'seller_net' => $sellerNet,
            'reserve_amount' => $reserveAmount,
            'reserve_rate' => $reserveRate,
            'debt_offset' => $debtOffset,
            'contribution_margin' => $contribution,
            'settlement_version' => 2,
            'status' => OrderStatus::Processing,
            // Physical revenue stays pending until delivery + complaint window.
            'funds_release_at' => $order->requiresShipping()
                ? null
                : now()->addDays((int) config('jualanyok.holding_period_days', 7)),
            'reserve_release_at' => $reserveAmount > 0
                ? now()->addDays((int) config('marketplace.reserve.release_days', 30))
                : null,
        ]);

        $this->marketplaceLedger->recordSale($order->fresh(['store.owner']), $payment);
    }

    private function reserveRate(Order $order): float
    {
        if (! (bool) config('marketplace.reserve.enabled', true)) {
            return 0.0;
        }

        $base = $order->requiresShipping()
            ? (float) config('marketplace.reserve.physical_percent', 5)
            : (float) config('marketplace.reserve.base_percent', 2);

        $paidBefore = Order::where('store_id', $order->store_id)
            ->whereKeyNot($order->id)
            ->paid()
            ->count();

        if ($paidBefore < (int) config('marketplace.reserve.new_seller_paid_orders', 10)) {
            $base += (float) config('marketplace.reserve.new_seller_bonus_percent', 2);
        }

        return min((float) config('marketplace.reserve.maximum_percent', 10), max(0, $base));
    }

    private function recordCouponUsage(Order $order): void
    {
        if (! $order->coupon_id) {
            return;
        }

        CouponUsage::firstOrCreate(
            ['coupon_id' => $order->coupon_id, 'order_id' => $order->id],
            ['customer_id' => $order->customer_id, 'amount' => $order->discount_total],
        );

        Coupon::whereKey($order->coupon_id)->increment('used_count');
    }

    private function updateCustomerStats(Order $order): void
    {
        if (! $order->customer) {
            return;
        }

        $order->customer->forceFill([
            'orders_count' => $order->customer->orders_count + 1,
            'lifetime_value' => Money::round((float) $order->customer->lifetime_value + (float) $order->grand_total),
            'last_order_at' => now(),
        ])->save();
    }

    public function markExpired(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            // Releasing the claim matters for exact-amount providers like QRIS:
            // an abandoned checkout would otherwise reserve its rupiah figure
            // forever and slowly burn through the usable amounts.
            $payment->forceFill([
                'status' => PaymentStatus::Expired,
                'claimable_amount' => null,
            ])->save();

            $order = $payment->order;

            if ($order->status->isOpen()) {
                $order->update([
                    'status' => OrderStatus::Expired,
                    'payment_status' => PaymentStatus::Expired,
                    'fulfillment_status' => FulfillmentStatus::Cancelled,
                ]);

                app(CheckoutService::class)->releaseReservations($order);
            }

            return $payment;
        });
    }

    public function markFailed(Payment $payment): Payment
    {
        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'claimable_amount' => null,
        ])->save();

        return $payment;
    }

    /** Sweeps payments whose expiry has passed without a callback. */
    public function expireStale(): int
    {
        $count = 0;

        Payment::where('status', PaymentStatus::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($payments) use (&$count) {
                foreach ($payments as $payment) {
                    $this->markExpired($payment);
                    $count++;
                }
            });

        return $count;
    }
}
