<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The six digits that turn a signup into a shop.
 *
 * A code rather than a link, because verification happens inside the setup
 * wizard: a link would drop the creator into a new tab and lose the four steps
 * they just filled in. The code can be typed back into the page they are
 * already on.
 */
class EmailVerificationCode extends Notification
{
    use Queueable;

    public function __construct(private readonly string $code) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Kode verifikasi toko kamu: {$this->code}")
            ->view('mail.auth.login-code', [
                'code' => $this->code,
                'expiresInMinutes' => 15,
                'year' => now()->year,
            ])
            ->text('mail.auth.login-code-text', [
                'code' => $this->code,
                'expiresInMinutes' => 15,
                'year' => now()->year,
            ]);
    }
}
