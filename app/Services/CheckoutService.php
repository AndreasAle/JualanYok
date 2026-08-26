<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly AffiliateService $affiliates,
    ) {}

    /**
     * Creates a PENDING_PAYMENT order.
     *
     * Stock is reserved here (not at payment time) under a row lock so two
     * concurrent buyers can never oversell the last unit. The whole thing runs
     * in one transaction: either the order and every reservation exist, or
     * neither does.
     *
     * @param  array<int, array{product_id:int, variant_id?:int|null, quantity:int, price?:float|null}>  $lines
     */
    public function createOrder(Store $store, array $lines, array $buyer, array $options = []): Order
    {
        if (empty($lines)) {
            throw ValidationException::withMessages(['items' => 'Keranjang masih kosong.']);
        }

        // An idempotency key makes a double-submitted checkout return the same
        // order instead of charging the buyer twice.
        $idempotencyKey = $options['idempotency_key'] ?? null;

        if ($idempotencyKey && $existing = Order::where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($store, $lines, $buyer, $options, $idempotencyKey) {
            $resolved = $this->resolveLines($store, $lines);

            $coupon = $this->resolveCoupon($store, $options['coupon_code'] ?? null, $resolved);

            $paymentMethod = $options['payment_method'] ?? null;
            $shipping = (float) ($options['shipping_total'] ?? 0);

            $quote = $this->pricing->quote($store, $resolved, $coupon, $shipping, null, $paymentMethod);

            $customer = $this->upsertCustomer($store, $buyer);

            $attribution = $this->affiliates->resolveAttribution(
                $store,
                $options['affiliate_code'] ?? null,
                $buyer['email'] ?? null,
            );

            $order = Order::create([
                'number' => Order::generateNumber(),
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'user_id' => $options['user_id'] ?? null,
                'status' => OrderStatus::PendingPayment,
                'payment_status' => PaymentStatus::Pending,
                'fulfillment_status' => FulfillmentStatus::Unfulfilled,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'customer_note' => $options['note'] ?? null,
                'custom_fields' => $options['custom_fields'] ?? null,
                'shipping_address' => $options['shipping_address'] ?? null,
                'shipping_method' => $options['shipping_method'] ?? null,
                'shipping_provider' => $options['shipping_provider'] ?? null,
                'shipping_service' => $options['shipping_service'] ?? null,
                'shipping_courier' => $options['shipping_courier'] ?? null,
                'shipping_courier_type' => $options['shipping_courier_type'] ?? null,
                'shipping_insurance' => $options['shipping_insurance'] ?? 0,
                'shipping_quote' => $options['shipping_quote'] ?? null,
                'currency' => $quote['currency'],
                'subtotal' => $quote['subtotal'],
                'discount_total' => $quote['discount_total'],
                'shipping_total' => $quote['shipping_total'],
                'tax_total' => $quote['tax_total'],
                'platform_fee' => $quote['platform_fee'],
                'payment_fee' => $quote['payment_fee'],
                'grand_total' => $quote['grand_total'],
                'seller_net' => $quote['seller_net'],
                'coupon_code' => $coupon?->code,
                'coupon_id' => $coupon?->id,
                'affiliate_code' => $attribution['code'] ?? null,
                'affiliate_user_id' => $attribution['user_id'] ?? null,
                'affiliate_click_id' => $attribution['click_id'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'utm' => $options['utm'] ?? null,
                'ip_address' => $options['ip'] ?? null,
                'expires_at' => now()->addHours((int) config('payments.expiry_hours', 24)),
            ]);

            foreach ($resolved as $line) {
                /** @var Product $product */
                $product = $line['product'];
                /** @var ProductVariant|null $variant */
                $variant = $line['variant'];

                $lineTotal = Money::round($line['unit_price'] * $line['quantity']);

                $commission = $this->affiliates->commissionForLine($product, $lineTotal, $attribution);

                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_type' => $product->type->value,
                    'name' => $product->name,
                    'variant_name' => $variant?->name,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'total' => $lineTotal,
                    'commission_rate' => $commission['rate'],
                    'commission_amount' => $commission['amount'],
                    'meta' => $line['meta'] ?? null,
                ]);

                if ($product->type->tracksStock()) {
                    $this->reserveStock($product, $variant, $line['quantity'], $item);
                }
            }

            $order->affiliate_commission = Money::round($order->items()->sum('commission_amount'));
            $order->save();

            return $order->fresh(['items', 'store', 'customer']);
        });
    }

    /**
     * Reserves stock with a locked read so parallel checkouts serialise on the
     * same inventory row.
     */
    private function reserveStock(Product $product, ?ProductVariant $variant, int $quantity, OrderItem $item): void
    {
        /** @var Inventory|null $inventory */
        $inventory = Inventory::where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->lockForUpdate()
            ->first();

        if (! $inventory) {
            return; // Product does not track stock.
        }

        if (! $inventory->canFulfil($quantity)) {
            throw ValidationException::withMessages([
                'items' => sprintf('Stok "%s" tinggal %d.', $product->name, $inventory->availableQuantity()),
            ]);
        }

        $inventory->reserved += $quantity;
        $inventory->save();

        $inventory->movements()->create([
            'change' => -$quantity,
            'balance_after' => $inventory->availableQuantity(),
            'reason' => 'reserve',
            'reference_type' => $item->getMorphClass(),
            'reference_id' => $item->id,
        ]);
    }

    /** Loads products fresh from the database — prices never come from the client. */
    private function resolveLines(Store $store, array $lines): array
    {
        $resolved = [];

        foreach ($lines as $line) {
            /** @var Product $product */
            $product = Product::where('store_id', $store->id)
                ->whereKey($line['product_id'])
                ->firstOrFail();

            if (! $product->isBuyable()) {
                throw ValidationException::withMessages([
                    'items' => sprintf('Produk "%s" sedang tidak tersedia.', $product->name),
                ]);
            }

            // Last line of defence: never take money for a digital product that
            // has no file behind it. The storefront already hides these, so
            // reaching here means a stale link or a crafted request.
            if (! $product->isDeliverable()) {
                throw ValidationException::withMessages([
                    'items' => sprintf('Produk "%s" belum siap dikirim. Hubungi penjual.', $product->name),
                ]);
            }

            $quantity = max(1, (int) ($line['quantity'] ?? 1));

            if ($quantity < $product->min_quantity) {
                throw ValidationException::withMessages([
                    'items' => sprintf('Minimal pembelian "%s" adalah %d.', $product->name, $product->min_quantity),
                ]);
            }

            if ($product->max_quantity !== null && $quantity > $product->max_quantity) {
                throw ValidationException::withMessages([
                    'items' => sprintf('Maksimal pembelian "%s" adalah %d.', $product->name, $product->max_quantity),
                ]);
            }

            $variant = null;
            if (! empty($line['variant_id'])) {
                $variant = ProductVariant::where('product_id', $product->id)
                    ->whereKey($line['variant_id'])
                    ->where('is_active', true)
                    ->firstOrFail();
            }

            // Stock is tracked per variant, so an order line without one would
            // reserve nothing and leave the seller guessing what to ship.
            if (! $variant && $product->requiresVariant()) {
                throw ValidationException::withMessages([
                    'items' => sprintf('Pilih varian untuk "%s" dulu.', $product->name),
                ]);
            }

            $unitPrice = $variant ? $variant->effectivePrice() : $product->effectivePrice();

            // Pay-what-you-want is the one case where the buyer sets the price,
            // and even then it is floored at the seller's minimum.
            if ($product->is_pay_what_you_want) {
                $offered = (float) ($line['price'] ?? 0);
                $minimum = (float) ($product->minimum_price ?? 0);

                if ($offered < $minimum) {
                    throw ValidationException::withMessages([
                        'items' => sprintf('Minimal untuk "%s" adalah %s.', $product->name, Money::format($minimum)),
                    ]);
                }

                $unitPrice = Money::round($offered);
            }

            $resolved[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => $quantity,
                'unit_price' => Money::round($unitPrice),
                'meta' => $line['meta'] ?? null,
            ];
        }

        return $resolved;
    }

    private function resolveCoupon(Store $store, ?string $code, array $lines): ?Coupon
    {
        if (! $code) {
            return null;
        }

        $coupon = Coupon::where('code', $code)
            ->where(fn ($q) => $q->where('store_id', $store->id)->orWhereNull('store_id'))
            ->where('is_active', true)
            ->first();

        if (! $coupon || ! $coupon->isWithinWindow() || ! $coupon->hasQuotaLeft()) {
            throw ValidationException::withMessages(['coupon_code' => 'Kode kupon tidak valid atau sudah habis.']);
        }

        $subtotal = collect($lines)->sum(fn ($l) => $l['unit_price'] * $l['quantity']);

        if ($subtotal < (float) $coupon->min_order_amount) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Minimal belanja '.Money::format((float) $coupon->min_order_amount).' untuk pakai kupon ini.',
            ]);
        }

        return $coupon;
    }

    /** Buyers are identified by email within a store; no password required. */
    private function upsertCustomer(Store $store, array $buyer): Customer
    {
        $customer = Customer::firstOrNew([
            'store_id' => $store->id,
            'email' => strtolower(trim($buyer['email'])),
        ]);

        $customer->name = $buyer['name'] ?? $customer->name;
        $customer->phone = $buyer['phone'] ?? $customer->phone;
        $customer->source ??= $buyer['source'] ?? 'checkout';

        if (! empty($buyer['marketing_consent']) && ! $customer->marketing_consent) {
            $customer->marketing_consent = true;
            $customer->marketing_consent_at = now();
        }

        if (! $customer->exists && ! empty($buyer['user_id'])) {
            $customer->user_id = $buyer['user_id'];
        }

        $customer->save();

        return $customer;
    }

    /** Releases reserved stock when an order is cancelled or expires. */
    public function releaseReservations(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if ($item->product_type !== ProductType::Physical->value) {
                    continue;
                }

                $inventory = Inventory::where('product_id', $item->product_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    continue;
                }

                $inventory->reserved = max(0, $inventory->reserved - $item->quantity);
                $inventory->save();

                $inventory->movements()->create([
                    'change' => $item->quantity,
                    'balance_after' => $inventory->availableQuantity(),
                    'reason' => 'release',
                    'reference_type' => $item->getMorphClass(),
                    'reference_id' => $item->id,
                ]);
            }
        });
    }
}
