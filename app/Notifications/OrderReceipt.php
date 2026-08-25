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

        $mail->line('Total dibayar: **'.Money::format((float) $order->grand_total).'**');

        $hasFiles = $order->digitalAccesses()->exists();

        /*
         * Points at the token page, never the member area. Most buyers check out
         * as guests, so a link behind a login means the sale completes and the
         * product never actually reaches them.
         */
        $mail->action(
            $hasFiles ? 'Ambil File Kamu' : 'Lihat Pesanan',
            $order->deliveryUrl(),
        );

        $mail->line(
            $hasFiles
                ? 'Tautan di atas permanen dan cuma kamu yang punya — simpan email ini, filenya bisa diunduh ulang kapan saja.'
                : 'Tautan di atas permanen, jadi kamu bisa cek pesanan ini kapan saja tanpa perlu login.',
        );

        if ($order->items->first()?->product?->post_purchase_message) {
            $mail->line($order->items->first()->product->post_purchase_message);
        }

        return $mail;
    }
}
