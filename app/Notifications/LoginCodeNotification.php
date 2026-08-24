<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginCodeNotification extends Notification
{
    public function __construct(private readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode masuk JualanYok — berlaku 10 menit')
            ->view('mail.auth.login-code', [
                'code' => $this->code,
                'expiresInMinutes' => 10,
                'year' => now()->year,
            ])
            ->text('mail.auth.login-code-text', [
                'code' => $this->code,
                'expiresInMinutes' => 10,
                'year' => now()->year,
            ]);
    }
}
