<?php

namespace App\Shipping\Providers;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\StoreShippingProfile;
use App\Shipping\Contracts\ShippingProvider;

class ManualShippingProvider implements ShippingProvider
{
    public function code(): string
    {
        return 'manual';
    }

    public function searchAreas(string $query): array
    {
        return [];
    }

    public function quote(StoreShippingProfile $origin, array $destination, array $items): array
    {
        $subtotal = collect($items)->sum(fn (array $item) => ($item['value'] ?? 0) * ($item['quantity'] ?? 1));
        $amount = $subtotal >= (float) config('shipping.providers.manual.free_over', 0)
            && (float) config('shipping.providers.manual.free_over', 0) > 0
                ? 0
                : (float) config('shipping.providers.manual.flat_rate', 20000);

        return [[
            'provider' => 'manual',
            'courier_company' => 'seller',
            'courier_name' => 'Pengiriman oleh penjual',
            'courier_type' => 'regular',
            'service_name' => 'Reguler',
            'delivery_fee' => $amount,
            'amount' => $amount,
            'insurance_fee' => 0,
            'insurance_value' => 0,
            'duration' => config('shipping.providers.manual.estimated_days', '2 - 5 hari'),
        ]];
    }

    public function createShipment(Order $order, StoreShippingProfile $origin): array
    {
        return [
            'external_id' => null,
            'status' => 'confirmed',
            'provider_response' => ['message' => 'Menunggu nomor resi dari penjual.'],
        ];
    }

    public function track(Shipment $shipment): array
    {
        return [
            'status' => $shipment->status->value,
            'events' => [],
        ];
    }

    public function cancel(Shipment $shipment, string $reason): array
    {
        return ['status' => 'cancelled', 'reason' => $reason];
    }
}
