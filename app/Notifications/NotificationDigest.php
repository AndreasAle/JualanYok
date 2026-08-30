<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NotificationDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<int, array<string, mixed>> $items */
    public function __construct(public readonly array $items) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(count($this->items).' pembaruan dari JualanYok')
            ->greeting('Halo '.($notifiable->name ?? '').',')
            ->line('Ini ringkasan pembaruan yang kamu pilih untuk diterima sekali sehari.');

        foreach (array_slice($this->items, 0, 10) as $item) {
            $mail->line('• '.($item['title'] ?? 'Pembaruan').' — '.($item['message'] ?? ''));
        }

        if (count($this->items) > 10) {
            $mail->line('Masih ada '.(count($this->items) - 10).' pembaruan lainnya.');
        }

        return $mail
            ->action('Buka Pusat Notifikasi', route('notifications.index'))
            ->line('Kamu bisa mengubah frekuensi email dari halaman preferensi notifikasi.');
    }
}
