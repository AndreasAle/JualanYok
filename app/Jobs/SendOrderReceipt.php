<?php

namespace App\Jobs;

use App\Models\Order;
use App\Notifications\OrderReceipt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class SendOrderReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $orderId) {}

    public function handle(): void
    {
        $order = Order::with(['items', 'store', 'digitalAccesses.file'])->find($this->orderId);

        if (! $order) {
            return;
        }

        Notification::route('mail', $order->customer_email)
            ->notify(new OrderReceipt($order));
    }
}
