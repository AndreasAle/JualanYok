<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\AnalyticsService;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * The buyer's basket on a public storefront.
 *
 * Carts belong to a store, not to the platform, so the identifying cookie is
 * namespaced per store — browsing two creators never mixes their baskets.
 * Guests are supported throughout; a login simply attaches the existing cart.
 */
class CartController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly AnalyticsService $analytics,
    ) {}

    /**
     * The basket as a page.
     *
     * The sheet is fine for "added to cart"; deciding what to actually buy is
     * not a thing to do through a peephole over the product you were reading.
     */
    public function index(Request $request, Store $store): \Inertia\Response
    {
        abort_unless($store->isLive(), 404);

        $cart = $this->currentCart($request, $store);

        return \Inertia\Inertia::render('Storefront/Cart', [
            'store' => [
                'username' => $store->username,
                'name' => $store->name,
                'avatar_url' => $store->avatarUrl(),
                'public_url' => $store->publicUrl(),
                'theme' => $store->theme?->only([
                    'primary_color', 'accent_color', 'background_type', 'background_value',
                    'font_family', 'button_style', 'card_style', 'product_layout', 'color_scheme',
                    'extras',
                ]) ?? [],
            ],
            'cart' => $this->carts->payload($cart),
        ]);
    }

    public function store(Request $request, Store $store)
    {
        abort_unless($store->isLive(), 404);

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $product = Product::where('store_id', $store->id)
            ->whereKey($data['product_id'])
            ->firstOrFail();

        $variant = ! empty($data['variant_id'])
            ? ProductVariant::where('product_id', $product->id)->whereKey($data['variant_id'])->firstOrFail()
            : null;

        // Checked before the cart exists, so a rejected add leaves no empty row.
        $this->carts->assertCartable($product, $variant);

        $cart = $this->currentCart($request, $store);

        $this->carts->add($cart, $product, $variant, $data['quantity']);

        $this->analytics->record(
            $store,
            AnalyticsEvent::ADD_TO_CART,
            $product,
            $this->analytics->contextFrom($request),
        );

        return back(303)
            ->with('success', sprintf('"%s" masuk keranjang.', $product->name))
            ->withCookie($this->cookieFor($store, $cart->token));
    }

    public function update(Request $request, Store $store, CartItem $item)
    {
        abort_unless($store->isLive(), 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $cart = $this->currentCart($request, $store);

        $this->carts->setQuantity($cart, $item, $data['quantity']);

        return back(303)->withCookie($this->cookieFor($store, $cart->token));
    }

    public function destroy(Request $request, Store $store, CartItem $item)
    {
        abort_unless($store->isLive(), 404);

        $cart = $this->currentCart($request, $store);

        $this->carts->remove($cart, $item);

        return back(303)
            ->with('success', 'Item dihapus dari keranjang.')
            ->withCookie($this->cookieFor($store, $cart->token));
    }

    public function clear(Request $request, Store $store)
    {
        abort_unless($store->isLive(), 404);

        $cart = $this->currentCart($request, $store);

        $this->carts->clear($cart);

        return back(303)->with('success', 'Keranjang dikosongkan.');
    }

    private function currentCart(Request $request, Store $store)
    {
        return $this->carts->resolve(
            $store,
            $request->cookie($this->carts->cookieName($store)),
            $request->user(),
        );
    }

    private function cookieFor(Store $store, string $token)
    {
        // httpOnly: the token is an identifier, never something the page scripts
        // need to read.
        return Cookie::make(
            name: $this->carts->cookieName($store),
            value: $token,
            minutes: CartService::LIFETIME_DAYS * 24 * 60,
            httpOnly: true,
        );
    }
}
