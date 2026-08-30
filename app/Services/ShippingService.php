<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Enums\ShipmentStatus;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\StoreShippingProfile;
use App\Shipping\ShippingManager;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ShippingService
{
    public function __construct(
        private readonly ShippingManager $manager,
        private readonly MarketplaceLedgerService $marketplaceLedger,
        private readonly NotificationCenterService $notifications,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function searchAreas(string $query): array
    {
        return $this->manager->provider()->searchAreas($query);
    }

    /**
     * Returns server-calculated rates. Every rate carries an encrypted token;
     * checkout accepts that token, never a price typed by the browser.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function quotes(Store $store, array $lines, array $destination): array
    {
        $profile = $this->activeProfile($store);
        $items = $this->shipmentItems($store, $lines);

        if ($items === []) {
            return [];
        }

        $destination = $this->normalizeAddress($destination);
        $expiresAt = now()->addMinutes((int) config('shipping.quote_ttl_minutes', 20));
        $fingerprint = $this->fingerprint($lines);

        return collect($this->manager->provider()->quote($profile, $destination, $items))
            ->map(function (array $quote) use ($store, $destination, $expiresAt, $fingerprint) {
                $payload = [
                    'version' => 1,
                    'store_id' => $store->id,
                    'destination' => $destination,
                    'fingerprint' => $fingerprint,
                    'quote' => $quote,
                    'expires_at' => $expiresAt->timestamp,
                ];

                return $quote + [
                    'token' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
                    'expires_at' => $expiresAt->toIso8601String(),
                ];
            })
            ->sortBy('amount')
            ->values()
            ->all();
    }

    /** @return array{destination:array<string,mixed>,quote:array<string,mixed>} */
    public function verifyQuote(Store $store, array $lines, string $token): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['shipping_quote_token' => 'Pilihan ongkir tidak valid. Pilih kurir lagi.']);
        }

        if ((int) ($payload['store_id'] ?? 0) !== $store->id
            || ! hash_equals((string) ($payload['fingerprint'] ?? ''), $this->fingerprint($lines))
            || (int) ($payload['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['shipping_quote_token' => 'Tarif ongkir sudah berubah atau kedaluwarsa. Pilih kurir lagi.']);
        }

        if ((float) data_get($payload, 'quote.amount', -1) < 0) {
            throw ValidationException::withMessages(['shipping_quote_token' => 'Tarif ongkir tidak sah.']);
        }

        return [
            'destination' => $this->normalizeAddress($payload['destination'] ?? []),
            'quote' => $payload['quote'],
        ];
    }

    public function saveCustomerAddress(Order $order): void
    {
        if (! $order->customer || ! $order->shipping_address) {
            return;
        }

        $address = $this->normalizeAddress($order->shipping_address);

        DB::transaction(function () use ($order, $address) {
            $order->customer->addresses()->update(['is_default' => false]);
            CustomerAddress::updateOrCreate(
                [
                    'customer_id' => $order->customer_id,
                    'address_line' => $address['address_line'],
                    'postal_code' => $address['postal_code'],
                ],
                [
                    'label' => $address['label'] ?? 'Utama',
                    'recipient' => $order->customer_name,
                    'phone' => $order->customer_phone,
                    'district' => $address['district'] ?? null,
                    'city' => $address['city'],
                    'province' => $address['province'],
                    'area_id' => $address['area_id'],
                    'latitude' => $address['latitude'] ?? null,
                    'longitude' => $address['longitude'] ?? null,
                    'note' => $address['note'] ?? null,
                    'is_default' => true,
                ],
            );
        });
    }

    public function createShipment(Order $order): Shipment
    {
        if (! $order->requiresShipping() || $order->payment_status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages(['shipment' => 'Pesanan harus lunas dan berisi produk fisik.']);
        }

        $order->loadMissing(['store.shippingProfile', 'items.product', 'items.variant']);
        $profile = $this->activeProfile($order->store);

        return DB::transaction(function () use ($order, $profile) {
            $shipment = Shipment::where('order_id', $order->id)->lockForUpdate()->first();

            if ($shipment?->external_id || ($shipment && $shipment->provider === 'manual')) {
                return $shipment;
            }

            $shipment ??= Shipment::create([
                'order_id' => $order->id,
                'provider' => $order->shipping_provider ?: config('shipping.default', 'manual'),
                'courier_company' => $order->shipping_courier,
                'courier_type' => $order->shipping_courier_type,
                'courier_name' => data_get($order->shipping_quote, 'courier_name'),
                'quoted_price' => $order->shipping_total,
                'insurance_fee' => $order->shipping_insurance,
                'collection_method' => $profile->collection_method,
                'status' => ShipmentStatus::Pending,
            ]);

            try {
                $result = $this->manager->provider($shipment->provider)->createShipment($order, $profile);
                $this->fillShipment($shipment, $result);
                $order->update(['fulfillment_status' => FulfillmentStatus::Ready]);
            } catch (\Throwable $e) {
                $shipment->update(['last_error' => $e->getMessage()]);
                throw $e;
            }

            return $shipment->fresh('events');
        });
    }

    public function sync(Shipment $shipment): Shipment
    {
        $result = $this->manager->provider($shipment->provider)->track($shipment);
        $this->fillShipment($shipment, $result);

        return $shipment->fresh('events');
    }

    /** Refresh active courier jobs when a webhook is delayed or missed. */
    public function syncActive(): int
    {
        $count = 0;
        $terminal = collect(ShipmentStatus::cases())->filter(fn (ShipmentStatus $status) => $status->isTerminal())->map->value->all();

        Shipment::query()
            ->where('provider', 'biteship')
            ->whereNotNull('external_id')
            ->whereNotIn('status', $terminal)
            ->where(fn ($query) => $query->whereNull('last_synced_at')->orWhere('last_synced_at', '<=', now()->subMinutes(5)))
            ->chunkById(50, function ($shipments) use (&$count) {
                foreach ($shipments as $shipment) {
                    try {
                        $this->sync($shipment);
                        $count++;
                    } catch (\Throwable $exception) {
                        $shipment->update(['last_error' => $exception->getMessage(), 'last_synced_at' => now()]);
                        Log::warning('shipping.sync.failed', ['shipment_id' => $shipment->id, 'error' => $exception->getMessage()]);
                    }
                }
            });

        return $count;
    }

    public function cancel(Shipment $shipment, string $reason): Shipment
    {
        if ($shipment->status->isMoving() || $shipment->status === ShipmentStatus::Delivered) {
            throw ValidationException::withMessages(['shipment' => 'Paket yang sudah dibawa kurir tidak dapat dibatalkan dari dashboard.']);
        }

        $result = $this->manager->provider($shipment->provider)->cancel($shipment, $reason);
        $this->fillShipment($shipment, $result + ['status' => 'cancelled']);

        return $shipment->fresh();
    }

    public function handleWebhook(Request $request): array
    {
        $secret = (string) config('shipping.providers.biteship.webhook_secret');
        $header = (string) config('shipping.providers.biteship.webhook_header', 'X-Callback-Token');
        $supplied = (string) $request->header($header);

        if ($secret !== '' && ! hash_equals($secret, $supplied)) {
            abort(401, 'Signature webhook pengiriman tidak valid.');
        }

        $externalId = data_get($request->all(), 'order_id')
            ?? data_get($request->all(), 'id')
            ?? data_get($request->all(), 'data.id');
        $shipment = Shipment::where('provider', 'biteship')->where('external_id', $externalId)->first();

        if (! $shipment) {
            Log::warning('shipping.webhook.unknown', ['external_id' => $externalId]);

            return ['status' => 'ignored'];
        }

        // If no shared secret is configured, treat the callback as a wake-up
        // signal and retrieve authoritative state from Biteship itself.
        $result = $secret === ''
            ? $this->manager->provider('biteship')->track($shipment)
            : $request->all();

        $this->fillShipment($shipment, $result);

        return ['status' => 'ok'];
    }

    /** @param array<string,mixed> $result */
    private function fillShipment(Shipment $shipment, array $result): void
    {
        $previousStatus = $shipment->status;
        $rawStatus = strtolower((string) ($result['status'] ?? data_get($result, 'data.status') ?? $shipment->status->value));
        $status = ShipmentStatus::tryFrom($rawStatus) ?? $shipment->status;
        $events = (array) ($result['history'] ?? $result['events'] ?? data_get($result, 'data.history', []));

        DB::transaction(function () use ($shipment, $result, $status, $events) {
            $previousVariance = (float) $shipment->order->shipping_variance;
            $actualPrice = (float) ($result['actual_price'] ?? $result['price'] ?? $shipment->actual_price ?? 0);
            $shipment->update([
                'external_id' => $result['external_id'] ?? (($result['object'] ?? null) === 'order' ? ($result['id'] ?? null) : null) ?? $shipment->external_id,
                'waybill_id' => $result['waybill_id'] ?? $result['courier_waybill_id'] ?? data_get($result, 'courier.waybill_id') ?? $shipment->waybill_id,
                'tracking_id' => $result['tracking_id'] ?? $result['courier_tracking_id'] ?? data_get($result, 'courier.tracking_id') ?? (($result['object'] ?? null) === 'tracking' ? ($result['id'] ?? null) : null) ?? $shipment->tracking_id,
                'tracking_url' => $result['tracking_url'] ?? $result['courier_link'] ?? data_get($result, 'courier.link') ?? data_get($result, 'courier.tracking_url') ?? $shipment->tracking_url,
                'driver_name' => $result['courier_driver_name'] ?? data_get($result, 'courier.driver_name') ?? $shipment->driver_name,
                'driver_phone' => $result['courier_driver_phone'] ?? data_get($result, 'courier.driver_phone') ?? $shipment->driver_phone,
                'driver_photo_url' => $result['courier_driver_photo_url'] ?? data_get($result, 'courier.driver_photo_url') ?? $shipment->driver_photo_url,
                'driver_plate_number' => $result['courier_driver_plate_number'] ?? data_get($result, 'courier.driver_plate_number') ?? $shipment->driver_plate_number,
                'actual_price' => $actualPrice ?: $shipment->actual_price,
                'status' => $status,
                'request_payload' => $result['request_payload'] ?? $shipment->request_payload,
                'provider_response' => $result['provider_response'] ?? $result,
                'last_error' => null,
                'last_synced_at' => now(),
                'picked_up_at' => $status->isMoving() && ! $shipment->picked_up_at ? now() : $shipment->picked_up_at,
                'delivered_at' => $status === ShipmentStatus::Delivered ? ($shipment->delivered_at ?? now()) : $shipment->delivered_at,
                'cancelled_at' => $status === ShipmentStatus::Cancelled ? ($shipment->cancelled_at ?? now()) : $shipment->cancelled_at,
            ]);

            if ($actualPrice > 0) {
                $order = $shipment->order;
                $variance = Money::round((float) $order->shipping_total - $actualPrice);
                $delta = Money::round($variance - $previousVariance);
                $order->update([
                    'shipping_cost_actual' => $actualPrice,
                    'shipping_variance' => $variance,
                    'contribution_margin' => Money::round((float) $order->contribution_margin + $delta),
                ]);
                $this->marketplaceLedger->recordShippingVariance(
                    $order,
                    $shipment,
                    $delta,
                    'shipping-variance:'.$shipment->id.':'.md5((string) $actualPrice),
                );
            }

            foreach ($events as $index => $event) {
                $eventId = (string) ($event['id'] ?? hash('sha256', json_encode([$index, $event])));
                $shipment->events()->updateOrCreate(
                    ['external_event_id' => $eventId],
                    [
                        'status' => strtolower((string) ($event['status'] ?? $status->value)),
                        'description' => $event['note'] ?? $event['description'] ?? null,
                        'location' => $event['location'] ?? null,
                        'event_at' => $event['updated_at'] ?? $event['created_at'] ?? now(),
                        'raw' => $event,
                    ],
                );
            }

            if ($shipment->wasChanged('status') && $events === []) {
                $eventId = 'status:'.hash('sha256', json_encode([
                    $status->value,
                    $result['updated_at'] ?? data_get($result, 'data.updated_at') ?? null,
                ]));
                $shipment->events()->firstOrCreate(
                    ['external_event_id' => $eventId],
                    [
                        'status' => $status->value,
                        'description' => $status->label(),
                        'location' => $result['location'] ?? data_get($result, 'data.location'),
                        'event_at' => $result['updated_at'] ?? data_get($result, 'data.updated_at') ?? now(),
                        'raw' => $result,
                    ],
                );
            }

            $this->syncOrderFromShipment($shipment->fresh());
        });

        if ($previousStatus !== $status) {
            $this->notifyBuyerOfShipmentUpdate($shipment->fresh(['order.user', 'order.store']), $status);
        }
    }

    private function notifyBuyerOfShipmentUpdate(Shipment $shipment, ShipmentStatus $status): void
    {
        if (! in_array($status, [
            ShipmentStatus::Picked,
            ShipmentStatus::DroppingOff,
            ShipmentStatus::Delivered,
            ShipmentStatus::OnHold,
            ShipmentStatus::ReturnInTransit,
            ShipmentStatus::Returned,
        ], true)) {
            return;
        }

        $order = $shipment->order;
        $payload = [
            'type' => 'shipping.status_updated',
            'category' => 'shipping',
            'priority' => in_array($status, [ShipmentStatus::OnHold, ShipmentStatus::ReturnInTransit, ShipmentStatus::Returned], true) ? 'high' : 'normal',
            'title' => $status->label(),
            'message' => "Status paket {$order->number} dari {$order->store->name} sudah diperbarui.",
            'url' => $order->trackingUrl(),
            'action_label' => 'Lacak barangmu',
            'group_key' => 'buyer-shipping:'.$shipment->id.':'.$status->value,
            'tone' => $status === ShipmentStatus::Delivered ? 'success' : (in_array($status, [ShipmentStatus::OnHold, ShipmentStatus::ReturnInTransit, ShipmentStatus::Returned], true) ? 'warning' : 'info'),
            'email_required' => true,
            'meta' => ['order_id' => $order->id, 'shipment_id' => $shipment->id],
        ];

        $this->notifications->sendToMail($order->customer_email, $payload);
    }

    private function syncOrderFromShipment(Shipment $shipment): void
    {
        $order = $shipment->order;

        if ($shipment->status->isMoving()) {
            app(FulfillmentService::class)->markShipped(
                $order,
                $shipment->waybill_id ?: $shipment->tracking_id,
                $shipment->courier_name ?: $shipment->courier_company,
            );
        } elseif ($shipment->status === ShipmentStatus::Delivered) {
            app(FulfillmentService::class)->markDelivered($order);
        } elseif (in_array($shipment->status, [ShipmentStatus::Returned, ShipmentStatus::ReturnInTransit], true)) {
            $order->update([
                'status' => OrderStatus::Disputed,
                'fulfillment_status' => FulfillmentStatus::Returned,
            ]);
        }
    }

    private function activeProfile(Store $store): StoreShippingProfile
    {
        $profile = $store->shippingProfile()->where('is_active', true)->first();

        if (! $profile || (config('shipping.default') === 'biteship' && blank($profile->area_id))) {
            throw ValidationException::withMessages([
                'shipping' => 'Toko belum mengatur alamat asal pengiriman. Hubungi penjual atau pilih produk nonfisik.',
            ]);
        }

        return $profile;
    }

    /** @param array<int,array<string,mixed>> $lines
     * @return array<int,array<string,mixed>>
     */
    private function shipmentItems(Store $store, array $lines): array
    {
        $items = [];

        foreach ($lines as $line) {
            $product = Product::where('store_id', $store->id)->whereKey($line['product_id'])->firstOrFail();
            if ($product->type !== ProductType::Physical) {
                continue;
            }

            $variant = ! empty($line['variant_id'])
                ? ProductVariant::where('product_id', $product->id)->whereKey($line['variant_id'])->firstOrFail()
                : null;
            $quantity = max(1, (int) ($line['quantity'] ?? 1));

            if ($product->shippingWeight($variant) <= 1 && (int) $product->weight_gram <= 0 && (int) ($variant?->weight_gram ?? 0) <= 0) {
                throw ValidationException::withMessages(['shipping' => "Berat produk {$product->name} belum diisi penjual."]);
            }

            $items[] = [
                'name' => $product->name,
                'description' => $product->short_description ?: $product->name,
                'value' => (int) round($variant?->effectivePrice() ?? $product->effectivePrice()),
                'quantity' => $quantity,
                'weight' => $product->shippingWeight($variant),
                'category' => $product->shipping_category ?: 'others',
                ...$product->shippingDimensions($variant),
            ];
        }

        return $items;
    }

    /** @param array<int,array<string,mixed>> $lines */
    public function requiresShipping(Store $store, array $lines): bool
    {
        foreach ($lines as $line) {
            $product = Product::where('store_id', $store->id)->whereKey($line['product_id'])->first();

            if ($product && $product->requiresVariant() && empty($line['variant_id'])) {
                throw ValidationException::withMessages(['items' => "Pilih varian untuk \"{$product->name}\" dulu."]);
            }

            if ($product?->type === ProductType::Physical) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int,array<string,mixed>> $lines */
    private function fingerprint(array $lines): string
    {
        $normalized = collect($lines)->map(fn (array $line) => [
            'product_id' => (int) $line['product_id'],
            'variant_id' => isset($line['variant_id']) ? (int) $line['variant_id'] : null,
            'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
        ])->sortBy(fn (array $line) => $line['product_id'].':'.($line['variant_id'] ?? 0))->values()->all();

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /** @return array<string,mixed> */
    private function normalizeAddress(array $address): array
    {
        $required = ['address_line', 'city', 'province', 'postal_code', 'area_id'];
        foreach ($required as $field) {
            if (blank($address[$field] ?? null)) {
                throw ValidationException::withMessages(["shipping_address.{$field}" => 'Alamat pengiriman belum lengkap.']);
            }
        }

        return collect($address)->only([
            'label', 'address_line', 'district', 'city', 'province', 'postal_code',
            'area_id', 'latitude', 'longitude', 'note',
        ])->all();
    }
}
