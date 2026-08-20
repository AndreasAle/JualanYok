<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Jobs\DispatchStoreWebhook;
use App\Jobs\SendOrderReceipt;
use App\Models\AnalyticsEvent;
use App\Notifications\NewOrderReceived;
use App\Services\AnalyticsService;
use App\Services\FulfillmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Everything that happens after the money lands. Queued, so a slow mail server
 * or a creator's failing webhook can never delay the payment callback.
 */
class HandleOrderPaid implements ShouldQueue
{
    public function __construct(
        private readonly FulfillmentService $fulfillment,
        private readonly AnalyticsService $analytics,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        $this->fulfillment->fulfil($order);

        SendOrderReceipt::dispatch($order->id);

        DispatchStoreWebhook::dispatch($order->store_id, 'order.paid', [
            'order_number' => $order->number,
            'total' => (float) $order->grand_total,
            'customer_email' => $order->customer_email,
            'items' => $order->items->map(fn ($i) => [
                'name' => $i->name,
                'quantity' => $i->quantity,
                'total' => (float) $i->total,
            ])->all(),
        ]);

        $this->analytics->record(
            $order->store,
            AnalyticsEvent::PURCHASE,
            $order,
            ['meta' => ['order_number' => $order->number]],
            (float) $order->grand_total,
        );

        Notification::send($order->store->owner, new NewOrderReceived($order));
    }
}
