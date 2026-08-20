<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode masuk JualanYok: '.$this->code)
            ->greeting('Halo!')
            ->line('Ini kode masuk kamu:')
            ->line('# '.$this->code)
            ->line('Kode berlaku 10 menit dan cuma bisa dipakai sekali.')
            ->line('Kalau kamu nggak minta kode ini, abaikan saja email ini.');
    }
}
