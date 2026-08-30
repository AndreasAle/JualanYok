<?php

namespace App\Listeners;

use App\Services\NotificationCenterService;
use Illuminate\Auth\Events\PasswordReset;

class NotifyPasswordChanged
{
    public function __construct(private readonly NotificationCenterService $notifications) {}

    public function handle(PasswordReset $event): void
    {
        $this->notifications->send($event->user, [
            'type' => 'security.password_changed',
            'category' => 'security',
            'priority' => 'high',
            'title' => 'Password akun sudah berubah',
            'message' => 'Password berhasil diperbarui. Jika bukan kamu yang melakukannya, hubungi support sekarang.',
            'url' => route('notifications.index'),
            'action_label' => 'Periksa keamanan',
            'action_required' => true,
            'group_key' => 'security:password:'.$event->user->id.':'.now()->format('YmdH'),
            'tone' => 'warning',
        ]);
    }
}
