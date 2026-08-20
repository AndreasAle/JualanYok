<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderReceipt extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;

        $mail = (new MailMessage)
            ->subject("Pesanan {$order->number} berhasil dibayar")
            ->greeting("Halo {$order->customer_name}!")
            ->line("Pembayaran kamu di toko {$order->store->name} sudah kami terima. Makasih ya!")
            ->line('Nomor pesanan: **'.$order->number.'**');

        foreach ($order->items as $item) {
            $mail->line(sprintf('• %s ×%d — %s', $item->name, $item->quantity, Money::format((float) $item->total)));
        }

        $mail->line('Total dibayar: **'.Money::format((float) $order->grand_total).'**')
            ->action('Lihat Pesanan', route('member.orders.show', $order->number))
            ->line('Semua produk digital bisa langsung kamu unduh dari halaman pesanan.');

        if ($order->items->first()?->product?->post_purchase_message) {
            $mail->line($order->items->first()->product->post_purchase_message);
        }

        return $mail;
    }
}
