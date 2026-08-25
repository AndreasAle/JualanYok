<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\ProductFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells someone who already bought that the file they own has a new edition.
 *
 * This is the thing a chat-based handover cannot do: once a marketplace seller
 * has sent a file through a message thread, past buyers are unreachable. Here
 * the entitlement outlives the transaction, so an update reaches everyone.
 */
class ProductFileUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
        private readonly ProductFile $file,
        private readonly string $productName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Versi baru: {$this->productName}")
            ->greeting('Halo '.$this->order->customer_name.'!')
            ->line("Penjual baru saja memperbarui **{$this->productName}** yang kamu beli.")
            ->line("File: {$this->file->name} — sekarang versi {$this->file->version}.")
            ->action('Unduh Versi Terbaru', $this->order->deliveryUrl())
            ->line('Gratis, tidak perlu beli ulang. Tautannya sama seperti waktu kamu beli.');
    }
}
