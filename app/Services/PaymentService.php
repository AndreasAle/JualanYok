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

        $methodConfig = $this->manager->findMethod($providerKey, $method, $channel);

        if (! $methodConfig) {
            throw ValidationException::withMessages(['method' => 'Metode pembayaran tidak tersedia.']);
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
                'currency' => $order->currency,
            ]);

            $provider = $this->manager->driver($providerKey);
            $result = $provider->createPayment($payment);

            $payment->fill([
                'reference' => $result->reference,
                'fee' => $result->fee ?? 0,
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

        $fee = Money::round(
            $beforeFee * (float) ($methodConfig['fee_percent'] ?? 0) / 100
            + (float) ($methodConfig['fee_fixed'] ?? 0)
        );

        $order->forceFill([
            'payment_fee' => $fee,
            'grand_total' => Money::round($beforeFee + $fee),
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

            $payment->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => $result->paidAt ?? now(),
                'fee' => $result->fee ?? $payment->fee,
            ]);

            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            if (! $order->status->isSettled()) {
                $order->update([
                    'status' => OrderStatus::Paid,
                    'payment_status' => PaymentStatus::Paid,
                    'paid_at' => $payment->paid_at,
                ]);

                $this->commitStock($order);
                $this->creditSeller($order);
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
    private function creditSeller(Order $order): void
    {
        $wallet = $this->ledger->walletFor($order->store->owner);

        $sellerNet = Money::round(
            (float) $order->subtotal
            - (float) $order->discount_total
            + (float) $order->shipping_total
            - (float) $order->platform_fee
            - (float) $order->affiliate_commission
        );

        $this->ledger->record(
            wallet: $wallet,
            type: LedgerEntryType::SellerRevenue,
            bucket: BalanceBucket::Pending,
            amount: max(0, $sellerNet),
            reference: $order,
            description: 'Penjualan '.$order->number,
            idempotencyKey: 'order-revenue:'.$order->id,
            meta: [
                'gross' => (float) $order->grand_total,
                'platform_fee' => (float) $order->platform_fee,
                'payment_fee' => (float) $order->payment_fee,
                'affiliate_commission' => (float) $order->affiliate_commission,
            ],
        );

        $order->update([
            'seller_net' => max(0, $sellerNet),
            'status' => OrderStatus::Processing,
        ]);
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
