<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Code128Barcode;
use App\Support\Money;
use App\Support\QrImage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShippingLabelController extends Controller
{
    public function __invoke(Request $request, Order $order): Response
    {
        abort_unless($order->store_id === $request->user()->store?->id, 403);

        $order->loadMissing(['store.shippingProfile', 'items.product', 'items.variant', 'shipment']);
        $shipment = $order->shipment;

        abort_unless($shipment && filled($shipment->waybill_id), 409, 'Nomor resi belum tersedia. Sinkronkan Biteship lalu coba lagi.');

        $payload = $shipment->request_payload ?? [];
        $provider = $shipment->provider_response ?? [];
        $items = collect($payload['items'] ?? [])->map(fn (array $item) => [
            'name' => (string) ($item['name'] ?? 'Barang'),
            'description' => (string) ($item['description'] ?? ''),
            'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
            'weight' => max(0, (int) ($item['weight'] ?? 0)),
            'length' => max(0, (int) ($item['length'] ?? 0)),
            'width' => max(0, (int) ($item['width'] ?? 0)),
            'height' => max(0, (int) ($item['height'] ?? 0)),
        ]);

        if ($items->isEmpty()) {
            $items = $order->items->map(function ($item) {
                $dimensions = $item->product?->shippingDimensions($item->variant) ?? ['length' => 0, 'width' => 0, 'height' => 0];

                return [
                    'name' => $item->name.($item->variant_name ? ' - '.$item->variant_name : ''),
                    'description' => '',
                    'quantity' => max(1, (int) $item->quantity),
                    'weight' => $item->product?->shippingWeight($item->variant) ?? 0,
                    ...$dimensions,
                ];
            });
        }

        $address = $order->shipping_address ?? [];
        $profile = $order->store->shippingProfile;
        $totalWeight = $items->sum(fn (array $item) => $item['weight'] * $item['quantity']);
        $shippingCost = (float) $shipment->actual_price > 0
            ? (float) $shipment->actual_price
            : ((float) $shipment->quoted_price > 0 ? (float) $shipment->quoted_price : (float) $order->shipping_total);
        $routingCode = data_get($provider, 'courier.routing_code')
            ?? data_get($provider, 'data.courier.routing_code');

        return response()->view('creator.shipping-label', [
            'order' => $order,
            'shipment' => $shipment,
            'barcode' => Code128Barcode::svg($shipment->waybill_id),
            'trackingQr' => QrImage::svgDataUri($order->trackingUrl(), 180),
            'courier' => strtoupper((string) ($shipment->courier_company ?: $order->shipping_courier ?: 'KURIR')),
            'service' => (string) ($order->shipping_service ?: $shipment->courier_name ?: $shipment->courier_type ?: 'Reguler'),
            'routingCode' => $routingCode,
            'sender' => [
                'name' => (string) ($payload['origin_contact_name'] ?? $profile?->contact_name ?? $order->store->name),
                'phone' => (string) ($payload['origin_contact_phone'] ?? $profile?->contact_phone ?? ''),
                'address' => (string) ($payload['origin_address'] ?? $profile?->fullAddress() ?? ''),
                'postal_code' => (string) ($payload['origin_postal_code'] ?? $profile?->postal_code ?? ''),
            ],
            'recipient' => [
                'name' => (string) ($payload['destination_contact_name'] ?? $order->customer_name),
                'phone' => (string) ($payload['destination_contact_phone'] ?? $order->customer_phone),
                'address' => (string) ($payload['destination_address'] ?? $address['address_line'] ?? ''),
                'district' => (string) ($address['district'] ?? ''),
                'city' => (string) ($address['city'] ?? ''),
                'province' => (string) ($address['province'] ?? ''),
                'postal_code' => (string) ($payload['destination_postal_code'] ?? $address['postal_code'] ?? ''),
            ],
            'items' => $items,
            'totalWeight' => $totalWeight,
            'shippingCost' => Money::format($shippingCost),
            'note' => (string) ($payload['destination_note'] ?? $payload['order_note'] ?? $order->customer_note ?? ''),
        ])->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
