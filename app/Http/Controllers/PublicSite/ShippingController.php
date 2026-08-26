<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Store;
use App\Services\CartService;
use App\Services\ShippingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

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

        try {
            return response()->json(['areas' => $this->shipping->searchAreas($data['q'])]);
        } catch (Throwable $exception) {
            return $this->providerFailure($exception, 'mencari wilayah');
        }
    }

    public function quotes(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->isLive(), 404);

        // Biteship may serialize postal codes as JSON numbers. Normalize the
        // scalar before validation so a valid selected area is not rejected.
        $shippingAddress = $request->input('shipping_address');
        if (is_array($shippingAddress) && isset($shippingAddress['postal_code']) && is_scalar($shippingAddress['postal_code'])) {
            $shippingAddress['postal_code'] = (string) $shippingAddress['postal_code'];
            $request->merge(['shipping_address' => $shippingAddress]);
        }

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

        try {
            return response()->json(['quotes' => $this->shipping->quotes($store, $lines, $data['shipping_address'])]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->providerFailure($exception, 'menghitung ongkir');
        }
    }

    private function cartLines(Request $request, Store $store): array
    {
        $token = $request->cookie($this->carts->cookieName($store));
        $cart = $token ? Cart::where('store_id', $store->id)->where('token', $token)->first() : null;

        abort_unless($cart, 419, 'Keranjang tidak ditemukan.');

        return $this->carts->checkoutLines($cart);
    }

    private function providerFailure(Throwable $exception, string $operation): JsonResponse
    {
        $upstreamStatus = $exception instanceof RequestException
            ? $exception->response->status()
            : null;

        Log::warning('Shipping provider request failed.', [
            'operation' => $operation,
            'provider' => config('shipping.default'),
            'upstream_status' => $upstreamStatus,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        if ($upstreamStatus === 401 || $upstreamStatus === 403) {
            return response()->json([
                'message' => 'Koneksi Biteship belum valid. Admin perlu memperbarui token API pengiriman.',
                'code' => 'SHIPPING_AUTH_FAILED',
            ], 503);
        }

        if ($upstreamStatus === 429) {
            return response()->json([
                'message' => 'Pencarian alamat terlalu sering. Tunggu sebentar lalu coba lagi.',
                'code' => 'SHIPPING_RATE_LIMITED',
            ], 429);
        }

        if ($exception instanceof ConnectionException) {
            return response()->json([
                'message' => 'Biteship sedang tidak dapat dijangkau. Coba lagi beberapa saat.',
                'code' => 'SHIPPING_UNREACHABLE',
            ], 503);
        }

        return response()->json([
            'message' => "Gagal {$operation}. Layanan pengiriman sedang bermasalah; coba lagi atau hubungi admin.",
            'code' => 'SHIPPING_PROVIDER_FAILED',
        ], 503);
    }
}
