<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Store;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checking out part of a basket.
 *
 * A basket is a shortlist as much as an order, so buying three of five items
 * has to be one action. The rule that has to hold while allowing that: the
 * browser may say which of its own rows to include, and nothing else — what
 * each row is and what it costs still comes from the server's copy.
 */
class CartSelectionTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        $this->store = $this->makeStore();
        $this->store->forceFill(['is_published' => true])->save();
    }

    private function cartWithTwoProducts(): Cart
    {
        $cart = Cart::create(['store_id' => $this->store->id, 'token' => 'keranjang-uji']);
        $carts = app(CartService::class);

        $carts->add($cart, $this->makeProduct($this->store, ['name' => 'Barang A', 'price' => 10000]), null, 1);
        $carts->add($cart, $this->makeProduct($this->store, ['name' => 'Barang B', 'price' => 25000]), null, 2);

        return $cart->fresh();
    }

    public function test_the_cart_page_renders_for_a_guest(): void
    {
        $this->get("/{$this->store->username}/keranjang")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Storefront/Cart')->has('cart.items'));
    }

    public function test_only_the_ticked_rows_are_charged(): void
    {
        $cart = $this->cartWithTwoProducts();
        $lines = app(CartService::class)->checkoutLines($cart);
        $first = $lines[0];

        $keep = $cart->items()->where('product_id', $first['product_id'])->value('id');

        $selected = app(CartService::class)->checkoutLines($cart, [$keep]);

        $this->assertCount(1, $selected);
        $this->assertSame($first['product_id'], $selected[0]['product_id']);
    }

    public function test_an_id_from_outside_this_cart_pulls_in_nothing(): void
    {
        $cart = $this->cartWithTwoProducts();

        $otherStore = $this->makeStore(null, ['username' => 'tokolain']);
        $otherCart = Cart::create(['store_id' => $otherStore->id, 'token' => 'keranjang-lain']);
        app(CartService::class)->add($otherCart, $this->makeProduct($otherStore), null, 1);

        $strangerRow = $otherCart->fresh()->items()->value('id');

        $lines = app(CartService::class)->checkoutLines($cart, [$strangerRow]);

        $this->assertSame([], $lines, 'Baris keranjang orang lain tidak boleh ikut ditagih.');
    }

    public function test_no_selection_still_means_the_whole_cart(): void
    {
        $cart = $this->cartWithTwoProducts();

        // Buying from the sheet sends no selection at all; that must keep
        // meaning "everything", not "nothing".
        $this->assertCount(2, app(CartService::class)->checkoutLines($cart, null));
    }

    public function test_a_partial_checkout_charges_only_what_was_ticked(): void
    {
        $cart = $this->cartWithTwoProducts();
        $cheap = $cart->items()->orderBy('id')->value('id');

        $this->withCookie(app(CartService::class)->cookieName($this->store), $cart->token)
            ->post("/{$this->store->username}/checkout", [
                'from_cart' => true,
                'items' => [],
                'cart_item_ids' => [$cheap],
                'name' => 'Luna',
                'email' => 'luna@contoh.test',
                'phone' => '081234567890',
                'terms' => '1',
                'idempotency_key' => 'sebagian-1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(1, $order->items()->count(), 'Hanya baris yang dicentang yang jadi pesanan.');
        $this->assertEqualsWithDelta(10000, (float) $order->subtotal, 0.01, 'Hanya harga baris yang dicentang yang ditagih.');
    }
}
