<?php

namespace App\Shipping\Contracts;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\StoreShippingProfile;

interface ShippingProvider
{
    public function code(): string;

    /** @return array<int, array<string, mixed>> */
    public function searchAreas(string $query): array;

    /** @param array<int, array<string, mixed>> $items
     *  @return array<int, array<string, mixed>>
     */
    public function quote(StoreShippingProfile $origin, array $destination, array $items): array;

    /** @return array<string, mixed> */
    public function createShipment(Order $order, StoreShippingProfile $origin): array;

    /** @return array<string, mixed> */
    public function track(Shipment $shipment): array;

    /** @return array<string, mixed> */
    public function cancel(Shipment $shipment, string $reason): array;
}
