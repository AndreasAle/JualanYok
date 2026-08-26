<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Store;
use App\Services\CartService;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function __construct(
        private readonly ShippingService $shipping,
        private readonly CartService $carts,
    ) {}

    public function areas(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->isLive(), 404);
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:120']]);

        return response()->json(['areas' => $this->shipping->searchAreas($data['q'])]);
    }

    public function quotes(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->isLive(), 404);
        $data = $request->validate([
            'from_cart' => ['nullable', 'boolean'],
            'items' => ['required_without:from_cart', 'array'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.address_line' => ['required', 'string', 'max:500'],
            'shipping_address.district' => ['nullable', 'string', 'max:120'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.province' => ['required', 'string', 'max:120'],
            'shipping_address.postal_code' => ['required', 'string', 'max:12'],
            'shipping_address.area_id' => ['required', 'string', 'max:120'],
            'shipping_address.note' => ['nullable', 'string', 'max:500'],
        ]);

        $lines = $request->boolean('from_cart')
            ? $this->cartLines($request, $store)
            : $data['items'];

        return response()->json(['quotes' => $this->shipping->quotes($store, $lines, $data['shipping_address'])]);
    }

    private function cartLines(Request $request, Store $store): array
    {
        $token = $request->cookie($this->carts->cookieName($store));
        $cart = $token ? Cart::where('store_id', $store->id)->where('token', $token)->first() : null;

        abort_unless($cart, 419, 'Keranjang tidak ditemukan.');

        return $this->carts->checkoutLines($cart);
    }
}
