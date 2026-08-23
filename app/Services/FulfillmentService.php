<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Models\Attendee;
use App\Models\Booking;
use App\Models\DigitalAccess;
use App\Models\Enrollment;
use App\Models\Membership;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Grants the buyer whatever they bought. Called once per paid order; every
 * grant is idempotent so a replayed job cannot double-enrol or hand out a
 * second set of download tokens.
 */
class FulfillmentService
{
    public function fulfil(Order $order): void
    {
        // Everything the grant branches below touch, loaded up front. Without
        // this the job dies outright under strict local mode, and quietly runs
        // one query per line in production.
        $order->loadMissing([
            'items.product.files',
            'items.product.course',
            'items.product.event',
            'items.product.service',
        ]);

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                match ($item->product_type) {
                    ProductType::Digital->value => $this->grantDigital($order, $item),
                    ProductType::Course->value => $this->enrol($order, $item),
                    ProductType::Event->value => $this->issueTicket($order, $item),
                    ProductType::Membership->value => $this->startMembership($order, $item),
                    ProductType::Service->value => $this->confirmBooking($order, $item),
                    default => null,
                };

                if (ProductType::from($item->product_type)->isAutoFulfilled()) {
                    $item->update(['fulfillment_status' => FulfillmentStatus::Fulfilled]);
                }
            }

            $this->syncOrderStatus($order->fresh('items'));
        });
    }

    private function grantDigital(Order $order, OrderItem $item): void
    {
        $product = $item->product;

        if (! $product) {
            return;
        }

        // Checkout refuses undeliverable products, so an empty file list here
        // means pre-existing data. Flag it rather than completing silently.
        if ($product->files->isEmpty()) {
            Log::warning('Digital order fulfilled with no file attached.', [
                'order_id' => $order->id,
                'product_id' => $product->id,
            ]);

            return;
        }

        foreach ($product->files as $file) {
            DigitalAccess::firstOrCreate(
                ['order_item_id' => $item->id, 'product_file_id' => $file->id],
                [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'customer_id' => $order->customer_id,
                    'token' => DigitalAccess::generateToken(),
                    'download_limit' => $file->download_limit,
                    'expires_at' => $file->access_days ? now()->addDays($file->access_days) : null,
                ],
            );
        }
    }

    private function enrol(Order $order, OrderItem $item): void
    {
        $course = $item->product?->course;

        if (! $course) {
            return;
        }

        Enrollment::firstOrCreate(
            ['course_id' => $course->id, 'customer_id' => $order->customer_id],
            [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'started_at' => now(),
                'expires_at' => $course->access_days ? now()->addDays($course->access_days) : null,
                'certificate_code' => $course->certificate_enabled ? Str::upper(Str::random(10)) : null,
            ],
        );
    }

    private function issueTicket(Order $order, OrderItem $item): void
    {
        $event = $item->product?->event;

        if (! $event) {
            return;
        }

        for ($i = 0; $i < $item->quantity; $i++) {
            Attendee::firstOrCreate(
                ['order_id' => $order->id, 'event_id' => $event->id, 'code' => sprintf('%s-%02d', $order->number, $i + 1)],
                [
                    'ticket_id' => $item->meta['ticket_id'] ?? null,
                    'customer_id' => $order->customer_id,
                    'name' => $order->customer_name,
                    'email' => $order->customer_email,
                    'phone' => $order->customer_phone,
                ],
            );
        }
    }

    private function startMembership(Order $order, OrderItem $item): void
    {
        $plan = $item->product?->membershipPlans()->where('is_active', true)->first();

        if (! $plan) {
            return;
        }

        $months = match ($plan->interval) {
            'yearly' => 12,
            'quarterly' => 3,
            default => 1,
        } * max(1, $plan->interval_count);

        Membership::firstOrCreate(
            ['order_id' => $order->id, 'membership_plan_id' => $plan->id],
            [
                'customer_id' => $order->customer_id,
                'status' => 'ACTIVE',
                'started_at' => now(),
                'current_period_end' => now()->addMonths($months),
            ],
        );
    }

    private function confirmBooking(Order $order, OrderItem $item): void
    {
        $service = $item->product?->service;
        $slot = $item->meta['slot'] ?? null;

        if (! $service || ! $slot) {
            return;
        }

        $starts = Carbon::parse($slot);

        // The unique (service_id, starts_at) index is the real guard against
        // double booking; firstOrCreate just keeps the retry path quiet.
        Booking::firstOrCreate(
            ['service_id' => $service->id, 'starts_at' => $starts],
            [
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'ends_at' => $starts->copy()->addMinutes($service->duration_minutes),
                'timezone' => $service->timezone,
                'status' => 'confirmed',
                'customer_note' => $order->customer_note,
                'meeting_url' => $service->meeting_url,
            ],
        );
    }

    /** An order is COMPLETED only when nothing is left for the seller to do. */
    public function syncOrderStatus(Order $order): void
    {
        $needsManual = $order->items->contains(
            fn (OrderItem $i) => ! ProductType::from($i->product_type)->isAutoFulfilled()
        );

        if ($needsManual) {
            $order->update([
                'status' => OrderStatus::Processing,
                'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            ]);

            return;
        }

        $order->update([
            'status' => OrderStatus::Completed,
            'fulfillment_status' => FulfillmentStatus::Fulfilled,
            'completed_at' => now(),
        ]);
    }

    /** Seller marks a physical order shipped. */
    public function markShipped(Order $order, ?string $trackingNumber, ?string $courier = null): void
    {
        $order->update([
            'fulfillment_status' => FulfillmentStatus::Shipped,
            'tracking_number' => $trackingNumber,
            'shipping_method' => $courier ?? $order->shipping_method,
            'shipped_at' => now(),
            'status' => OrderStatus::Processing,
        ]);

        $order->items()->update(['fulfillment_status' => FulfillmentStatus::Shipped]);
    }

    public function markDelivered(Order $order): void
    {
        $order->update([
            'fulfillment_status' => FulfillmentStatus::Delivered,
            'status' => OrderStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
