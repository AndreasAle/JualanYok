<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Support\Media;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The buyer's basket for one store.
 *
 * A cart is a wish list, not a reservation: nothing is held and no price is
 * locked. Every read re-prices from the catalogue and re-checks availability,
 * so a cart left open for a week cannot resurrect a deleted product or an old
 * price. CheckoutService remains the only thing that decides what is actually
 * charged.
 */
class CartService
{
    public const COOKIE_PREFIX = 'jy_cart_';

    public const LIFETIME_DAYS = 30;

    /** A cart may hold this many distinct lines — a guard against abuse. */
    public const MAX_LINES = 50;

    public function cookieName(Store $store): string
    {
        return self::COOKIE_PREFIX.$store->id;
    }

    /** Finds the buyer's cart for this store, or starts a new one. */
    public function resolve(Store $store, ?string $token, ?User $user = null): Cart
    {
        $cart = $token
            ? Cart::where('store_id', $store->id)->where('token', $token)->first()
            : null;

        // A logged-in buyer keeps their cart across devices.
        if (! $cart && $user) {
            $cart = Cart::where('store_id', $store->id)
                ->where('user_id', $user->id)
                ->latest('id')
                ->first();
        }

        if ($cart && $cart->expires_at && $cart->expires_at->isPast()) {
            $cart->items()->delete();
            $cart->delete();
            $cart = null;
        }

        $cart ??= Cart::create([
            'store_id' => $store->id,
            'token' => Cart::generateToken(),
            'user_id' => $user?->id,
            'expires_at' => now()->addDays(self::LIFETIME_DAYS),
        ]);

        if ($user && $cart->user_id !== $user->id) {
            $cart->update(['user_id' => $user->id]);
        }

        return $cart;
    }

    /**
     * Products whose price or terms are decided per purchase cannot sit in a
     * basket — they keep the direct "buy now" path instead.
     */
    public function isCartable(Product $product): bool
    {
        if (! $product->isBuyable() || ! $product->isDeliverable()) {
            return false;
        }

        if ($product->is_pay_what_you_want) {
            return false;
        }

        return ! in_array($product->type->value, ['DONATION', 'SERVICE', 'EXTERNAL'], true);
    }

    /**
     * Called before a cart is resolved, so a rejected "add" never leaves an
     * empty cart row behind.
     */
    public function assertCartable(Product $product, ?ProductVariant $variant = null): void
    {
        if (! $this->isCartable($product)) {
            throw ValidationException::withMessages([
                'product_id' => sprintf('"%s" tidak bisa dimasukkan keranjang. Pakai tombol Beli Sekarang.', $product->name),
            ]);
        }

        // Stock lives on the variant row, so a variant-bearing product added
        // without one would reserve nothing and ship an unspecified item.
        if (! $variant && $product->requiresVariant()) {
            throw ValidationException::withMessages([
                'variant_id' => sprintf('Pilih dulu varian untuk "%s".', $product->name),
            ]);
        }
    }

    public function add(Cart $cart, Product $product, ?ProductVariant $variant, int $quantity): CartItem
    {
        if ($product->store_id !== $cart->store_id) {
            throw ValidationException::withMessages([
                'product_id' => 'Produk ini bukan dari toko yang sama.',
            ]);
        }

        if ($variant && ($variant->product_id !== $product->id || ! $variant->is_active)) {
            throw ValidationException::withMessages(['variant_id' => 'Varian tidak tersedia.']);
        }

        $this->assertCartable($product, $variant);

        return DB::transaction(function () use ($cart, $product, $variant, $quantity) {
            $item = $cart->items()
                ->where('product_id', $product->id)
                ->where('product_variant_id', $variant?->id)
                ->first();

            if (! $item && $cart->items()->count() >= self::MAX_LINES) {
                throw ValidationException::withMessages([
                    'product_id' => 'Keranjang sudah penuh. Selesaikan pesanan ini dulu ya.',
                ]);
            }

            $wanted = ($item?->quantity ?? 0) + $quantity;

            $item
                ? $item->update([
                    'quantity' => $this->clampQuantity($product, $variant, $wanted),
                    'unit_price' => $this->priceOf($product, $variant),
                ])
                : $item = $cart->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => $this->clampQuantity($product, $variant, $wanted),
                    'unit_price' => $this->priceOf($product, $variant),
                ]);

            $cart->touch();

            return $item;
        });
    }

    public function setQuantity(Cart $cart, CartItem $item, int $quantity): void
    {
        $this->assertOwns($cart, $item);

        if ($quantity <= 0) {
            $this->remove($cart, $item);

            return;
        }

        $product = $item->product;

        if (! $product) {
            $this->remove($cart, $item);

            return;
        }

        $item->update([
            'quantity' => $this->clampQuantity($product, $item->variant, $quantity),
            'unit_price' => $this->priceOf($product, $item->variant),
        ]);

        $cart->touch();
    }

    public function remove(Cart $cart, CartItem $item): void
    {
        $this->assertOwns($cart, $item);

        $item->delete();
        $cart->touch();
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->update(['coupon_code' => null]);
    }

    /**
     * The cart as the buyer should see it right now.
     *
     * Lines that are no longer purchasable stay visible but are marked, so the
     * buyer understands why the total changed instead of finding items silently
     * missing.
     */
    public function payload(Cart $cart): array
    {
        $cart->load(['items.product', 'items.variant']);

        $lines = [];
        $subtotal = 0.0;
        $hasIssue = false;

        foreach ($cart->items as $item) {
            $product = $item->product;

            if (! $product) {
                $item->delete();

                continue;
            }

            $current = $this->priceOf($product, $item->variant);
            $available = $this->availableStock($product, $item->variant);
            $issue = $this->issueFor($product, $item, $available);

            // Keep the stored snapshot honest with the catalogue.
            if ((float) $item->unit_price !== $current) {
                $item->update(['unit_price' => $current]);
            }

            if ($issue === null) {
                $subtotal += $current * $item->quantity;
            } else {
                $hasIssue = true;
            }

            $lines[] = [
                'id' => $item->id,
                'product_id' => $product->id,
                'variant_id' => $item->product_variant_id,
                'name' => $product->name,
                'variant_name' => $item->variant?->name,
                'slug' => $product->slug,
                'type_label' => $product->type->label(),
                'thumbnail_url' => $product->thumbnailUrl(),
                'unit_price' => $current,
                'quantity' => $item->quantity,
                'line_total' => Money::round($current * $item->quantity),
                'min_quantity' => $product->min_quantity,
                'max_quantity' => $this->maxQuantity($product, $available),
                'available_stock' => $available,
                'issue' => $issue,
            ];
        }

        return [
            'token' => $cart->token,
            'coupon_code' => $cart->coupon_code,
            'items' => $lines,
            'item_count' => array_sum(array_column($lines, 'quantity')),
            'subtotal' => Money::round($subtotal),
            'has_issue' => $hasIssue,
        ];
    }

    /** The shape CheckoutService expects, with unusable lines left out. */
    public function checkoutLines(Cart $cart): array
    {
        $lines = [];

        foreach ($this->payload($cart)['items'] as $line) {
            if ($line['issue'] !== null) {
                continue;
            }

            $lines[] = [
                'product_id' => $line['product_id'],
                'variant_id' => $line['variant_id'],
                'quantity' => $line['quantity'],
            ];
        }

        return $lines;
    }

    private function issueFor(Product $product, CartItem $item, ?int $available): ?string
    {
        if (! $product->isBuyable() || ! $product->isDeliverable() || $product->visibility === 'private') {
            return 'Produk ini sudah tidak dijual.';
        }

        // A line saved before the seller added options, or before this rule
        // existed. Flagged rather than left to fail the whole checkout.
        if (! $item->product_variant_id && $product->requiresVariant()) {
            return 'Pilih varian dulu di halaman produk.';
        }

        if ($available !== null && $available <= 0) {
            return 'Stok habis.';
        }

        if ($available !== null && $item->quantity > $available) {
            return "Stok tinggal {$available}.";
        }

        if ($item->quantity < $product->min_quantity) {
            return "Minimal pembelian {$product->min_quantity}.";
        }

        return null;
    }

    private function priceOf(Product $product, ?ProductVariant $variant): float
    {
        return Money::round($variant ? $variant->effectivePrice() : $product->effectivePrice());
    }

    /** Null means the product does not track stock. */
    private function availableStock(Product $product, ?ProductVariant $variant): ?int
    {
        if (! $product->type->tracksStock()) {
            return null;
        }

        $inventory = Inventory::where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->first();

        if (! $inventory || ! $inventory->track_stock) {
            return null;
        }

        return $inventory->availableQuantity();
    }

    private function maxQuantity(Product $product, ?int $available): int
    {
        $caps = array_filter([
            $product->max_quantity,
            $available,
        ], fn ($v) => $v !== null);

        return $caps === [] ? 99 : max(1, min(min($caps), 99));
    }

    private function clampQuantity(Product $product, ?ProductVariant $variant, int $quantity): int
    {
        $available = $this->availableStock($product, $variant);

        return max(
            $product->min_quantity,
            min($quantity, $this->maxQuantity($product, $available)),
        );
    }

    private function assertOwns(Cart $cart, CartItem $item): void
    {
        abort_unless($item->cart_id === $cart->id, 403);
    }
}
