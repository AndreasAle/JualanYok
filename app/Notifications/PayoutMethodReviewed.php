<?php

namespace App\Notifications;

use App\Models\PayoutMethod;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PayoutMethodReviewed extends Notification
{
    use Queueable;

    public function __construct(public readonly PayoutMethod $method) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $verified = $this->method->status === 'verified';

        return [
            'type' => $verified ? 'payout_method.verified' : 'payout_method.rejected',
            'title' => $verified ? 'Rekening pencairan terverifikasi' : 'Rekening pencairan ditolak',
            'message' => $verified
                ? "{$this->method->provider} {$this->method->maskedNumber()} sudah bisa dipakai untuk menarik saldo."
                : ($this->method->review_note ?: 'Periksa kembali data rekening pencairanmu.'),
            'url' => route('creator.withdrawals.index'),
            'payout_method_id' => $this->method->id,
        ];
    }
}
