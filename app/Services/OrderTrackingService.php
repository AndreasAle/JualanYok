<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OrderTrackingService
{
    public const CREATOR_STAGES = ['processing', 'packed', 'ready_for_pickup'];

    private const LABELS = [
        'order_placed' => 'Pesanan dibuat',
        'payment_confirmed' => 'Pembayaran dikonfirmasi',
        'processing' => 'Pesanan sedang diproses',
        'packed' => 'Pesanan sudah dikemas',
        'ready_for_pickup' => 'Paket siap diserahkan ke kurir',
        'confirmed' => 'Pengiriman dikonfirmasi',
        'scheduled' => 'Penjemputan dijadwalkan',
        'allocated' => 'Kurir sudah dialokasikan',
        'picking_up' => 'Kurir menuju lokasi penjual',
        'picked' => 'Paket sudah diserahkan ke kurir',
        'in_transit' => 'Paket dalam perjalanan',
        'dropping_off' => 'Kurir menuju alamat penerima',
        'delivered' => 'Paket sudah diterima',
        'completed' => 'Pesanan selesai',
        'on_hold' => 'Pengiriman sedang tertahan',
        'return_in_transit' => 'Paket dikembalikan ke penjual',
        'returned' => 'Paket sudah kembali ke penjual',
        'cancelled' => 'Pengiriman dibatalkan',
        'rejected' => 'Pengiriman ditolak',
        'courier_not_found' => 'Kurir belum tersedia',
        'disposed' => 'Paket tidak dapat dilanjutkan',
    ];

    private const PROGRESS = [
        'order_placed' => 8,
        'payment_confirmed' => 18,
        'processing' => 30,
        'packed' => 42,
        'ready_for_pickup' => 52,
        'confirmed' => 54,
        'scheduled' => 56,
        'allocated' => 60,
        'picking_up' => 64,
        'picked' => 70,
        'in_transit' => 82,
        'dropping_off' => 92,
        'delivered' => 98,
        'completed' => 100,
    ];

    public function creatorUpdate(Order $order, User $actor, string $stage, ?string $description = null): void
    {
        if (! in_array($stage, self::CREATOR_STAGES, true)) {
            throw ValidationException::withMessages(['stage' => 'Tahap persiapan tidak valid.']);
        }

        if (! $order->requiresShipping() || $order->payment_status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages(['stage' => 'Hanya pesanan fisik yang sudah lunas yang dapat diperbarui.']);
        }

        $rank = array_flip(self::CREATOR_STAGES);
        $latest = $order->trackingEvents()->where('source', 'creator')->latest('occurred_at')->first();
        if ($latest && ($rank[$stage] ?? -1) < ($rank[$latest->stage] ?? -1)) {
            throw ValidationException::withMessages(['stage' => 'Tahap pesanan tidak boleh dimundurkan.']);
        }

        if ($latest?->stage === $stage) {
            return;
        }

        $order->trackingEvents()->create([
            'actor_id' => $actor->id,
            'stage' => $stage,
            'source' => 'creator',
            'title' => self::LABELS[$stage],
            'description' => $description,
            'occurred_at' => now(),
        ]);

        if ($stage === 'ready_for_pickup' && $order->fulfillment_status === FulfillmentStatus::Unfulfilled) {
            $order->update(['fulfillment_status' => FulfillmentStatus::Ready]);
        }
    }

    /** @return array<string, mixed> */
    public function payload(Order $order): array
    {
        $order->loadMissing(['store', 'items.product', 'trackingEvents', 'shipment.events']);
        $timeline = $this->timeline($order);
        $current = $timeline->last();
        $shipment = $order->shipment;

        return [
            'tracking_code' => $order->tracking_code,
            'order_number' => $order->number,
            'status' => $current['stage'] ?? 'order_placed',
            'status_label' => $current['title'] ?? self::LABELS['order_placed'],
            'progress' => $this->progress((string) ($current['stage'] ?? 'order_placed')),
            'created_at' => $order->created_at?->toIso8601String(),
            'last_updated_at' => $this->lastUpdatedAt($order)?->toIso8601String(),
            'store' => [
                'name' => $order->store->name,
                'username' => $order->store->username,
                'avatar_url' => $order->store->avatarUrl(),
                'url' => route('storefront.show', $order->store->username),
            ],
            'buyer_first_name' => str($order->customer_name)->before(' ')->toString(),
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->name,
                'variant_name' => $item->variant_name,
                'quantity' => (int) $item->quantity,
                'thumbnail_url' => $item->product?->thumbnailUrl(),
            ])->values(),
            'shipment' => $shipment ? [
                'courier' => $shipment->courier_name ?: $shipment->courier_company,
                'service' => $order->shipping_service,
                'waybill_id' => $shipment->waybill_id,
                'tracking_url' => $shipment->tracking_url,
                'driver_name' => $shipment->driver_name,
                'driver_photo_url' => $shipment->driver_photo_url,
                'driver_plate_number' => $shipment->driver_plate_number,
            ] : null,
            'timeline' => $timeline->values(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function timeline(Order $order): Collection
    {
        $events = collect([[
            'stage' => 'order_placed',
            'title' => self::LABELS['order_placed'],
            'description' => 'Pesanan berhasil dibuat di JualanYok.',
            'location' => null,
            'source' => 'system',
            'occurred_at' => $order->created_at,
        ]]);

        if ($order->paid_at) {
            $events->push([
                'stage' => 'payment_confirmed',
                'title' => self::LABELS['payment_confirmed'],
                'description' => 'Pembayaran sudah diterima dan pesanan diteruskan ke penjual.',
                'location' => null,
                'source' => 'system',
                'occurred_at' => $order->paid_at,
            ]);
        }

        foreach ($order->trackingEvents as $event) {
            $events->push([
                'stage' => $event->stage,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'source' => $event->source,
                'occurred_at' => $event->occurred_at,
            ]);
        }

        foreach ($order->shipment?->events ?? [] as $event) {
            $stage = $event->status instanceof ShipmentStatus ? $event->status->value : (string) $event->status;
            $events->push([
                'stage' => $stage,
                'title' => self::LABELS[$stage] ?? str($stage)->replace('_', ' ')->title()->toString(),
                'description' => $event->description,
                'location' => $event->location,
                'source' => 'courier',
                'occurred_at' => $event->event_at,
            ]);
        }

        if ($order->shipment && ! $events->contains(fn ($event) => $event['stage'] === $order->shipment->status->value)) {
            $events->push([
                'stage' => $order->shipment->status->value,
                'title' => $order->shipment->status->label(),
                'description' => null,
                'location' => null,
                'source' => 'courier',
                'occurred_at' => $order->shipment->last_synced_at ?? $order->shipment->updated_at,
            ]);
        }

        if ($order->fulfillment_status === FulfillmentStatus::Ready && ! $events->contains(fn ($event) => $event['stage'] === 'ready_for_pickup')) {
            $events->push([
                'stage' => 'ready_for_pickup',
                'title' => self::LABELS['ready_for_pickup'],
                'description' => 'Penjual sudah menyiapkan paket untuk proses penjemputan atau drop-off.',
                'location' => null,
                'source' => 'creator',
                'occurred_at' => $order->updated_at,
            ]);
        }

        if ($order->shipped_at && ! $events->contains(fn ($event) => in_array($event['stage'], ['picked', 'in_transit', 'dropping_off'], true))) {
            $events->push([
                'stage' => 'picked',
                'title' => self::LABELS['picked'],
                'description' => $order->tracking_number ? 'Nomor resi: '.$order->tracking_number : null,
                'location' => null,
                'source' => 'creator',
                'occurred_at' => $order->shipped_at,
            ]);
        }

        if ($order->delivered_at && ! $events->contains(fn ($event) => $event['stage'] === 'delivered')) {
            $events->push([
                'stage' => 'delivered',
                'title' => self::LABELS['delivered'],
                'description' => 'Paket ditandai sudah sampai ke penerima.',
                'location' => null,
                'source' => $order->shipment ? 'courier' : 'creator',
                'occurred_at' => $order->delivered_at,
            ]);
        }

        if ($order->completed_at) {
            $events->push([
                'stage' => 'completed',
                'title' => self::LABELS['completed'],
                'description' => 'Pesanan dan masa konfirmasi penerimaan sudah selesai.',
                'location' => null,
                'source' => 'system',
                'occurred_at' => $order->completed_at,
            ]);
        }

        return $events
            ->filter(fn ($event) => $event['occurred_at'] !== null)
            ->sortBy('occurred_at')
            ->map(fn ($event) => [
                ...$event,
                'occurred_at' => $event['occurred_at']->toIso8601String(),
            ])->values();
    }

    private function progress(string $stage): int
    {
        return self::PROGRESS[$stage] ?? match ($stage) {
            'on_hold', 'courier_not_found' => 60,
            'return_in_transit', 'returned', 'rejected', 'cancelled', 'disposed' => 100,
            default => 20,
        };
    }

    private function lastUpdatedAt(Order $order)
    {
        return collect([
            $order->updated_at,
            $order->trackingEvents->max('occurred_at'),
            $order->shipment?->last_synced_at,
            $order->shipment?->events?->max('event_at'),
        ])->filter()->sortDesc()->first();
    }
}
