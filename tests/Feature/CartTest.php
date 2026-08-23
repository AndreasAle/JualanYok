<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The basket. Anything that decides what a buyer is charged has to be rebuilt
 * on the server, so these tests lean hard on the cases where the browser's idea
 * of the cart and the catalogue disagree.
 */
class CartTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->store = $this->makeStore();
    }

    private function cookieName(): string
    {
        return CartService::COOKIE_PREFIX.$this->store->id;
    }

    /** Adds a product and returns the resulting cart token. */
    private function addToCart(Product $product, int $quantity = 1, ?string $token = null): string
    {
        $request = $token
            ? $this->withCookie($this->cookieName(), $token)
            : $this;

        $response = $request->post("/{$this->store->username}/keranjang", [
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);

        $response->assertRedirect();

        return $token ?? Cart::where('store_id', $this->store->id)->latest('id')->firstOrFail()->token;
    }

    public function test_a_guest_can_fill_a_basket_across_requests(): void
    {
        $first = $this->makeProduct($this->store, ['name' => 'E-book A', 'price' => 50000]);
        $second = $this->makeProduct($this->store, ['name' => 'E-book B', 'price' => 30000]);

        $token = $this->addToCart($first, 2);
        $this->addToCart($second, 1, $token);

        $cart = Cart::where('token', $token)->firstOrFail();
        $payload = app(CartService::class)->payload($cart);

        $this->assertSame(2, count($payload['items']));
        $this->assertSame(3, $payload['item_count']);
        $this->assertEquals(130000, $payload['subtotal']);
    }

    public function test_adding_the_same_product_twice_merges_into_one_line(): void
    {
        $product = $this->makeProduct($this->store, ['price' => 25000]);

        $token = $this->addToCart($product, 1);
        $this->addToCart($product, 2, $token);

        $cart = Cart::where('token', $token)->firstOrFail();

        $this->assertSame(1, $cart->items()->count());
        $this->assertSame(3, $cart->items()->first()->quantity);
    }

    public function test_the_storefront_does_not_create_a_cart_for_every_visitor(): void
    {
        $this->makeProduct($this->store);

        $this->get("/{$this->store->username}")->assertOk();
        $this->get("/{$this->store->username}")->assertOk();

        $this->assertSame(0, Cart::count(), 'Browsing must not write cart rows.');
    }

    public function test_a_cart_belongs_to_one_store_only(): void
    {
        $other = $this->makeStore();
        $foreign = $this->makeProduct($other);

        $token = $this->addToCart($this->makeProduct($this->store));

        $this->withCookie($this->cookieName(), $token)
            ->post("/{$this->store->username}/keranjang", [
                'product_id' => $foreign->id,
                'quantity' => 1,
            ])
            ->assertNotFound();

        $this->assertSame(1, Cart::where('token', $token)->firstOrFail()->items()->count());
    }

    public function test_quantity_is_capped_by_available_stock(): void
    {
        $product = $this->makeProduct($this->store, ['type' => 'PHYSICAL', 'name' => 'Kaos', 'stock' => 3]);

        $token = $this->addToCart($product, 10);

        $cart = Cart::where('token', $token)->firstOrFail();

        $this->assertSame(3, $cart->items()->first()->quantity, 'Cannot stack more than exists.');
    }

    public function test_the_cart_reprices_itself_when_the_seller_changes_the_price(): void
    {
        $product = $this->makeProduct($this->store, ['price' => 100000]);
        $token = $this->addToCart($product, 1);

        $product->update(['price' => 75000]);

        $payload = app(CartService::class)->payload(Cart::where('token', $token)->firstOrFail());

        $this->assertEquals(75000, $payload['subtotal'], 'A stale snapshot must never win.');
    }

    public function test_a_product_pulled_from_sale_is_flagged_and_excluded_from_the_total(): void
    {
        $keep = $this->makeProduct($this->store, ['name' => 'Tetap', 'price' => 40000]);
        $pull = $this->makeProduct($this->store, ['name' => 'Ditarik', 'price' => 60000]);

        $token = $this->addToCart($keep, 1);
        $this->addToCart($pull, 1, $token);

        $pull->update(['status' => 'DRAFT']);

        $payload = app(CartService::class)->payload(Cart::where('token', $token)->firstOrFail());

        $this->assertTrue($payload['has_issue']);
        $this->assertEquals(40000, $payload['subtotal'], 'The unavailable line is not charged.');

        $flagged = collect($payload['items'])->firstWhere('name', 'Ditarik');
        $this->assertNotNull($flagged['issue']);
    }

    public function test_products_priced_per_purchase_cannot_be_carted(): void
    {
        $donation = $this->makeProduct($this->store, [
            'type' => 'DONATION',
            'name' => 'Traktir Kopi',
            'is_pay_what_you_want' => true,
            'minimum_price' => 10000,
        ]);

        $this->post("/{$this->store->username}/keranjang", [
            'product_id' => $donation->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('product_id');

        $this->assertSame(0, Cart::count());
    }

    public function test_a_digital_product_without_a_file_cannot_be_carted(): void
    {
        $product = $this->makeProduct($this->store, ['without_files' => true]);

        $this->post("/{$this->store->username}/keranjang", [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('product_id');
    }

    /** Builds a physical product with two sized variants and per-variant stock. */
    private function variantProduct(int $stock = 5): Product
    {
        $product = $this->makeProduct($this->store, [
            'type' => 'PHYSICAL',
            'name' => 'Kaos Oversize',
            'price' => 129000,
            'stock' => 0,
        ]);

        foreach (['M', 'L'] as $position => $name) {
            $variant = $product->variants()->create([
                'name' => $name,
                'price' => null,
                'is_active' => true,
                'position' => $position,
            ]);

            Inventory::create([
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => $stock,
                'track_stock' => true,
            ]);
        }

        return $product->fresh();
    }

    public function test_a_product_with_variants_cannot_be_carted_without_choosing_one(): void
    {
        $product = $this->variantProduct();

        $this->post("/{$this->store->username}/keranjang", [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('variant_id');

        $this->assertSame(0, Cart::count());
    }

    public function test_choosing_a_variant_carts_it_with_that_variants_stock(): void
    {
        $product = $this->variantProduct(stock: 3);
        $variant = $product->variants()->where('name', 'L')->firstOrFail();

        $this->post("/{$this->store->username}/keranjang", [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 9,
        ])->assertRedirect();

        $cart = Cart::latest('id')->firstOrFail();
        $line = $cart->items()->firstOrFail();

        $this->assertSame($variant->id, $line->product_variant_id);
        $this->assertSame(3, $line->quantity, 'Stok varian itu sendiri yang membatasi.');

        $payload = app(CartService::class)->payload($cart);
        $this->assertSame('L', $payload['items'][0]['variant_name']);
        $this->assertSame(3, $payload['items'][0]['available_stock']);
    }

    public function test_a_line_that_predates_variants_is_flagged_not_fatal(): void
    {
        $plain = $this->makeProduct($this->store, ['name' => 'E-book', 'price' => 40000]);
        $product = $this->makeProduct($this->store, [
            'type' => 'PHYSICAL',
            'name' => 'Kaos',
            'price' => 129000,
            'stock' => 5,
        ]);

        $token = $this->addToCart($plain, 1);
        $this->addToCart($product, 1, $token);

        // The seller introduces options after the item is already in a basket.
        $variant = $product->variants()->create(['name' => 'L', 'is_active' => true, 'position' => 0]);
        Inventory::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'track_stock' => true,
        ]);

        $payload = app(CartService::class)->payload(Cart::where('token', $token)->firstOrFail());

        $stale = collect($payload['items'])->firstWhere('name', 'Kaos');
        $this->assertSame('Pilih varian dulu di halaman produk.', $stale['issue']);
        $this->assertEquals(40000, $payload['subtotal'], 'Only the still-valid line counts.');

        // Checkout goes through with the good line instead of failing outright.
        $this->withCookie($this->cookieName(), $token)
            ->post("/{$this->store->username}/checkout", [
                'from_cart' => true,
                'items' => [],
                'name' => 'Rina',
                'email' => 'rina@example.test',
                'terms' => '1',
                'idempotency_key' => 'stale-variant',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame(1, $order->items()->count());
        $this->assertEquals(40000, (float) $order->subtotal);
    }

    public function test_checkout_refuses_a_variant_product_bought_without_a_variant(): void
    {
        $product = $this->variantProduct();

        // The direct buy-now path, bypassing the cart entirely.
        $this->from("/{$this->store->username}")
            ->post("/{$this->store->username}/checkout", [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'name' => 'Rina',
                'email' => 'rina@example.test',
                'terms' => '1',
                'idempotency_key' => 'no-variant',
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
    }

    public function test_checking_out_a_cart_creates_one_order_with_every_line(): void
    {
        $first = $this->makeProduct($this->store, ['name' => 'E-book A', 'price' => 50000]);
        $second = $this->makeProduct($this->store, ['name' => 'E-book B', 'price' => 30000]);

        $token = $this->addToCart($first, 2);
        $this->addToCart($second, 1, $token);

        $this->withCookie($this->cookieName(), $token)
            ->post("/{$this->store->username}/checkout", [
                'from_cart' => true,
                'name' => 'Rina',
                'email' => 'rina@example.test',
                'terms' => '1',
                'idempotency_key' => 'cart-checkout-1',
            ])
            ->assertRedirect();

        $order = Order::where('store_id', $this->store->id)->firstOrFail();

        $this->assertSame(2, $order->items()->count());
        $this->assertEquals(130000, (float) $order->subtotal);

        // The basket is emptied only after the order exists.
        $this->assertSame(0, Cart::where('token', $token)->firstOrFail()->items()->count());
    }

    /**
     * The browser's checkout form always includes an `items` key, empty in cart
     * mode. An earlier `min:1` rule rejected exactly that — caught only by
     * driving the real UI, so it is pinned here.
     */
    public function test_the_exact_payload_the_browser_sends_is_accepted(): void
    {
        $product = $this->makeProduct($this->store, ['price' => 60000]);
        $token = $this->addToCart($product, 1);

        $this->withCookie($this->cookieName(), $token)
            ->from("/{$this->store->username}")
            ->post("/{$this->store->username}/checkout", [
                'from_cart' => true,
                'items' => [],
                'name' => 'Rina',
                'email' => 'rina@example.test',
                'phone' => '',
                'note' => '',
                'coupon_code' => '',
                'marketing_consent' => false,
                'terms' => true,
                'idempotency_key' => 'browser-shape',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(1, Order::count());
    }

    public function test_checkout_ignores_line_items_the_browser_sends_alongside_the_cart(): void
    {
        $cheap = $this->makeProduct($this->store, ['name' => 'Murah', 'price' => 10000]);
        $expensive = $this->makeProduct($this->store, ['name' => 'Mahal', 'price' => 900000]);

        $token = $this->addToCart($expensive, 1);

        // A tampered payload trying to swap the expensive item for a cheap one.
        $this->withCookie($this->cookieName(), $token)
            ->post("/{$this->store->username}/checkout", [
                'from_cart' => true,
                'items' => [['product_id' => $cheap->id, 'quantity' => 1]],
                'name' => 'Rina',
                'email' => 'rina@example.test',
                'terms' => '1',
                'idempotency_key' => 'cart-tamper',
            ])
            ->assertRedirect();

        $order = Order::where('store_id', $this->store->id)->firstOrFail();

        $this->assertEquals(900000, (float) $order->subtotal, 'The stored cart decides, not the request.');
        $this->assertSame($expensive->id, $order->items()->first()->product_id);
    }

    public function test_checking_out_an_empty_cart_is_refused(): void
    {
        $product = $this->makeProduct($this->store);
        $token = $this->addToCart($product, 1);

        $product->update(['status' => 'DRAFT']);

        $this->withCookie($this->cookieName(), $token)
            ->from("/{$this->store->username}")
            ->post("/{$this->store->username}/checkout", [
                'from_cart' => true,
                'name' => 'Rina',
                'email' => 'rina@example.test',
                'terms' => '1',
                'idempotency_key' => 'cart-empty',
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
    }

    public function test_a_buyer_cannot_change_a_line_in_someone_elses_cart(): void
    {
        $mine = $this->addToCart($this->makeProduct($this->store, ['name' => 'Punyaku']));
        $theirs = $this->addToCart($this->makeProduct($this->store, ['name' => 'Punya orang']));

        $victimLine = Cart::where('token', $theirs)->firstOrFail()->items()->firstOrFail();

        $this->withCookie($this->cookieName(), $mine)
            ->delete("/{$this->store->username}/keranjang/{$victimLine->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('cart_items', ['id' => $victimLine->id]);
    }

    public function test_setting_a_quantity_to_zero_removes_the_line(): void
    {
        $product = $this->makeProduct($this->store);
        $token = $this->addToCart($product, 2);

        $line = Cart::where('token', $token)->firstOrFail()->items()->firstOrFail();

        $this->withCookie($this->cookieName(), $token)
            ->put("/{$this->store->username}/keranjang/{$line->id}", ['quantity' => 0])
            ->assertRedirect();

        $this->assertDatabaseMissing('cart_items', ['id' => $line->id]);
    }

    public function test_stock_reservation_still_governs_the_final_order(): void
    {
        $product = $this->makeProduct($this->store, ['type' => 'PHYSICAL', 'name' => 'Kaos', 'stock' => 2]);

        $token = $this->addToCart($product, 2);

        // Someone else buys the stock while this basket sits open.
        Inventory::where('product_id', $product->id)->update(['quantity' => 1]);

        $payload = app(CartService::class)->payload(Cart::where('token', $token)->firstOrFail());

        $this->assertTrue($payload['has_issue']);
        $this->assertStringContainsString('Stok tinggal 1', $payload['items'][0]['issue']);
    }

    public function test_the_storefront_shows_the_basket_it_was_given(): void
    {
        $product = $this->makeProduct($this->store, ['price' => 45000]);
        $token = $this->addToCart($product, 2);

        $this->withCookie($this->cookieName(), $token)
            ->get("/{$this->store->username}")
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('cart.item_count', 2)
                    ->where('cart.subtotal', 90000),
            );
    }
}
