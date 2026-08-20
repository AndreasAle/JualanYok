<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrderReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cuan masuk! Pesanan baru '.$this->order->number)
            ->greeting('Selamat!')
            ->line(sprintf(
                '%s baru saja beli di tokomu senilai %s.',
                $this->order->customer_name,
                Money::format((float) $this->order->grand_total),
            ))
            ->action('Lihat Pesanan', route('creator.orders.show', $this->order->number));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order.paid',
            'title' => 'Pesanan baru masuk',
            'message' => sprintf(
                '%s beli %s — %s',
                $this->order->customer_name,
                $this->order->items->first()?->name ?? 'produk kamu',
                Money::format((float) $this->order->grand_total),
            ),
            'url' => route('creator.orders.show', $this->order->number),
            'order_number' => $this->order->number,
        ];
    }
}
