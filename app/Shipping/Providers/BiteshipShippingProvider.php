<?php

namespace App\Shipping\Providers;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\StoreShippingProfile;
use App\Shipping\Contracts\ShippingProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class BiteshipShippingProvider implements ShippingProvider
{
    public function code(): string
    {
        return 'biteship';
    }

    public function searchAreas(string $query): array
    {
        $response = $this->client()
            ->retry(2, 250, fn (Throwable $exception) => $this->shouldRetry($exception))
            ->get('/v1/maps/areas', [
                'countries' => 'ID',
                'input' => $query,
                'type' => 'single',
            ])->throw()->json();

        return collect(data_get($response, 'areas', data_get($response, 'data.areas', [])))
            ->map(fn (array $area) => [
                'id' => $area['id'] ?? null,
                'name' => $area['name'] ?? $area['display_name'] ?? null,
                'postal_code' => $area['postal_code'] ?? null,
                'administrative_division_level_1_name' => $area['administrative_division_level_1_name'] ?? null,
                'administrative_division_level_2_name' => $area['administrative_division_level_2_name'] ?? null,
                'administrative_division_level_3_name' => $area['administrative_division_level_3_name'] ?? null,
            ])
            ->filter(fn (array $area) => filled($area['id']))
            ->values()
            ->all();
    }

    public function quote(StoreShippingProfile $origin, array $destination, array $items): array
    {
        $insuranceValue = $origin->default_insurance
            ? (int) collect($items)->sum(fn (array $item) => ((int) ($item['value'] ?? 0)) * ((int) ($item['quantity'] ?? 1)))
            : 0;

        $payload = [
            'origin_area_id' => $origin->area_id,
            'destination_area_id' => $destination['area_id'],
            'couriers' => implode(',', $origin->enabled_couriers ?: config('shipping.providers.biteship.couriers', [])),
            'items' => $items,
        ];

        if ($insuranceValue > 0) {
            $payload['courier_insurance'] = $insuranceValue;
        }

        $response = $this->client()
            ->retry(2, 250, fn (Throwable $exception) => $this->shouldRetry($exception))
            ->post('/v1/rates/couriers', $payload)->throw()->json();

        return collect($response['pricing'] ?? [])
            ->filter(fn (array $rate) => ($rate['available_for_order'] ?? true) === true)
            ->map(function (array $rate) use ($insuranceValue) {
                $deliveryFee = (float) ($rate['price'] ?? 0);
                $insuranceFee = $insuranceValue > 0
                    ? (float) ($rate['courier_insurance_fee'] ?? $rate['insurance_fee'] ?? data_get($rate, 'insurance.fee', 0))
                    : 0;

                return [
                    'provider' => 'biteship',
                    'courier_company' => $rate['courier_code'] ?? $rate['company'] ?? null,
                    'courier_name' => $rate['courier_name'] ?? $rate['company'] ?? 'Kurir',
                    'courier_type' => $rate['courier_service_code'] ?? $rate['type'] ?? null,
                    'service_name' => $rate['courier_service_name'] ?? $rate['service_name'] ?? $rate['type'] ?? 'Reguler',
                    'delivery_fee' => $deliveryFee,
                    'insurance_fee' => $insuranceFee,
                    'insurance_value' => $insuranceValue,
                    'amount' => $deliveryFee + $insuranceFee,
                    'duration' => $rate['duration'] ?? null,
                    'supports_insurance' => (bool) ($rate['available_for_insurance'] ?? false),
                ];
            })
            ->filter(fn (array $rate) => filled($rate['courier_company']) && filled($rate['courier_type']))
            ->values()
            ->all();
    }

    public function createShipment(Order $order, StoreShippingProfile $origin): array
    {
        $order->loadMissing(['store', 'items.product', 'items.variant']);
        $address = $order->shipping_address;
        $quote = $order->shipping_quote;

        $payload = [
            'shipper_contact_name' => $origin->contact_name,
            'shipper_contact_phone' => $origin->contact_phone,
            'shipper_contact_email' => $origin->contact_email,
            'shipper_organization' => $order->store->name,
            'origin_contact_name' => $origin->contact_name,
            'origin_contact_phone' => $origin->contact_phone,
            'origin_address' => $origin->address_line,
            'origin_area_id' => $origin->area_id,
            'origin_postal_code' => $origin->postal_code,
            'destination_contact_name' => $order->customer_name,
            'destination_contact_phone' => $order->customer_phone,
            'destination_contact_email' => $order->customer_email,
            'destination_address' => $address['address_line'],
            'destination_area_id' => $address['area_id'],
            'destination_postal_code' => $address['postal_code'],
            'courier_company' => $quote['courier_company'],
            'courier_type' => $quote['courier_type'],
            'origin_collection_method' => $origin->collection_method,
            'delivery_type' => 'now',
            'origin_note' => $origin->note,
            'destination_note' => $address['note'] ?? null,
            'order_note' => $order->customer_note,
            'reference_id' => $order->number,
            'metadata' => [
                'order_number' => $order->number,
                'store_id' => $order->store_id,
            ],
            'items' => collect($order->items)
                ->filter(fn ($item) => $item->product_type === 'PHYSICAL' && $item->product)
                ->map(function ($item) {
                    $product = $item->product;
                    $variant = $item->variant;

                    return [
                        'name' => $item->name.($item->variant_name ? ' - '.$item->variant_name : ''),
                        'description' => $item->name,
                        'value' => (int) round((float) $item->unit_price),
                        'quantity' => $item->quantity,
                        'weight' => $product->shippingWeight($variant),
                        'category' => $product->shipping_category ?: 'others',
                        ...$product->shippingDimensions($variant),
                    ];
                })->values()->all(),
        ];

        if ((float) ($quote['insurance_value'] ?? 0) > 0) {
            $payload['courier_insurance'] = (int) $quote['insurance_value'];
        }

        $response = $this->client()->post('/v1/orders', $payload)->throw()->json();
        $body = data_get($response, 'data', $response);

        return [
            'external_id' => $body['id'] ?? null,
            'status' => $body['status'] ?? 'confirmed',
            'waybill_id' => data_get($body, 'courier.waybill_id'),
            'tracking_id' => data_get($body, 'courier.tracking_id'),
            'tracking_url' => data_get($body, 'courier.tracking_url'),
            'actual_price' => data_get($body, 'price'),
            'request_payload' => $payload,
            'provider_response' => $response,
        ];
    }

    public function track(Shipment $shipment): array
    {
        if (! $shipment->external_id) {
            throw new RuntimeException('ID pengiriman Biteship belum tersedia.');
        }

        return $this->client()->get('/v1/orders/'.$shipment->external_id)->throw()->json();
    }

    public function cancel(Shipment $shipment, string $reason): array
    {
        if (! $shipment->external_id) {
            throw new RuntimeException('ID pengiriman Biteship belum tersedia.');
        }

        return $this->client()->post('/v1/orders/'.$shipment->external_id.'/cancel', [
            'cancellation_reason' => $reason,
        ])->throw()->json();
    }

    private function client(): PendingRequest
    {
        $token = (string) config('shipping.providers.biteship.token');

        if ($token === '') {
            throw new RuntimeException('Token Biteship belum dikonfigurasi.');
        }

        $baseUrl = rtrim((string) config('shipping.providers.biteship.base_url'), '/');

        // Endpoint methods already include `/v1`. Accept both documented base URL
        // styles so an environment value ending in `/v1` never becomes `/v1/v1`.
        $baseUrl = preg_replace('#/v1$#i', '', $baseUrl) ?: $baseUrl;

        return Http::baseUrl($baseUrl)
            ->withBasicAuth($token, '')
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('shipping.providers.biteship.timeout', 20));
    }

    private function shouldRetry(Throwable $exception): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException && $exception->response->serverError());
    }
}
