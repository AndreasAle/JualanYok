<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Support\Money;

/**
 * Turns a basket into a priced quote. Kept separate from order writing so the
 * checkout page and the order creation path always agree on the numbers, and
 * so nothing that reaches the ledger is ever taken from the client.
 */
class PricingService
{
    /**
     * @param  array<int, array{product: Product, variant: ?ProductVariant, quantity: int, unit_price: float}>  $lines
     */
    public function quote(
        Store $store,
        array $lines,
        ?Coupon $coupon = null,
        float $shipping = 0,
        ?string $paymentMethodKey = null,
        ?array $paymentMethod = null,
    ): array {
        $subtotal = 0.0;
        $items = [];

        foreach ($lines as $line) {
            $lineTotal = Money::round($line['unit_price'] * $line['quantity']);
            $subtotal += $lineTotal;

            $items[] = [
                'product_id' => $line['product']->id,
                'name' => $line['product']->name,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'total' => $lineTotal,
            ];
        }

        $subtotal = Money::round($subtotal);

        $discount = 0.0;
        if ($coupon) {
            // Percentage coupons restricted to certain products only discount
            // the eligible portion of the basket.
            $eligible = empty($coupon->product_ids)
                ? $subtotal
                : Money::round(collect($lines)
                    ->filter(fn ($l) => $coupon->appliesToProduct($l['product']->id))
                    ->sum(fn ($l) => $l['unit_price'] * $l['quantity']));

            $discount = $coupon->discountFor($eligible);
        }

        $taxPercent = (float) PlatformSetting::get('tax.percent', 0);
        $taxable = max(0, $subtotal - $discount);
        $tax = Money::percent($taxable, $taxPercent);

        $grandTotal = Money::round($taxable + $shipping + $tax);

        $paymentFee = $paymentMethod
            ? Money::round($grandTotal * (float) ($paymentMethod['fee_percent'] ?? 0) / 100 + (float) ($paymentMethod['fee_fixed'] ?? 0))
            : 0.0;

        // The buyer pays the gateway fee on top; the seller is never surprised
        // by a smaller payout than the sticker price minus platform fee.
        $grandTotal = Money::round($grandTotal + $paymentFee);

        $plan = $store->owner->currentPlan();
        $platformFee = $this->platformFee($plan, Money::round($taxable + $shipping));

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'shipping_total' => Money::round($shipping),
            'tax_total' => $tax,
            'payment_fee' => $paymentFee,
            'platform_fee' => $platformFee,
            'grand_total' => $grandTotal,
            'seller_net' => Money::round($taxable + $shipping - $platformFee),
            'currency' => 'IDR',
        ];
    }

    /** Platform commission for the seller's current plan. */
    public function platformFee(Plan $plan, float $base): float
    {
        return Money::round(
            $base * (float) $plan->transaction_fee_percent / 100
            + (float) $plan->transaction_fee_fixed
        );
    }
}
