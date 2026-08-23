<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once, right after an account is created.
 *
 * Someone who signed up with Google never sees a verification email — their
 * address is already proven — so without this they would hear nothing from us
 * at all. It doubles as the record that the address really reaches them.
 */
class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly bool $viaGoogle = false) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim((string) ($notifiable->name ?? '')) ?: 'Kreator';

        $mail = (new MailMessage)
            ->subject('Selamat datang di JualanYok!')
            ->greeting("Halo {$name}!")
            ->line('Akun kamu sudah aktif. Sekarang kamu bisa bikin toko, pasang produk, dan mulai jualan.');

        if ($this->viaGoogle) {
            $mail->line('Kamu daftar pakai akun Google, jadi emailmu langsung terverifikasi.');
        }

        return $mail
            ->action('Buka Dashboard', url('/dashboard'))
            ->line('Butuh bantuan? Balas email ini aja, kami baca kok.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Selamat datang di JualanYok',
            'body' => 'Akun kamu sudah aktif. Yuk mulai bikin toko pertamamu.',
            'url' => '/dashboard',
        ];
    }
}
