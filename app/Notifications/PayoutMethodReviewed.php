<?php

namespace App\Notifications;

use App\Models\PayoutMethod;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutMethodReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly PayoutMethod $method)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof User
            ? app(NotificationPreferenceService::class)->channels($notifiable, 'finance')
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verified = $this->method->status === 'verified';

        $mail = (new MailMessage)
            ->subject($verified ? 'Rekening pencairan sudah terverifikasi' : 'Rekening pencairan perlu diperbaiki')
            ->greeting('Halo '.($notifiable->name ?? '').',')
            ->line($verified
                ? "{$this->method->provider} {$this->method->maskedNumber()} sudah dapat digunakan untuk penarikan."
                : ($this->method->review_note ?: 'Tim finance meminta kamu memeriksa kembali data rekening pencairan.'))
            ->action('Buka Penarikan', route('creator.withdrawals.index'));

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        $verified = $this->method->status === 'verified';

        return [
            'type' => $verified ? 'payout_method.verified' : 'payout_method.rejected',
            'category' => 'finance',
            'priority' => 'high',
            'title' => $verified ? 'Rekening pencairan terverifikasi' : 'Rekening pencairan ditolak',
            'message' => $verified
                ? "{$this->method->provider} {$this->method->maskedNumber()} sudah bisa dipakai untuk menarik saldo."
                : ($this->method->review_note ?: 'Periksa kembali data rekening pencairanmu.'),
            'url' => route('creator.withdrawals.index'),
            'action_label' => $verified ? 'Tarik saldo' : 'Perbaiki rekening',
            'action_required' => ! $verified,
            'group_key' => 'payout-method:'.$this->method->id,
            'tone' => $verified ? 'success' : 'danger',
            'payout_method_id' => $this->method->id,
        ];
    }
}
