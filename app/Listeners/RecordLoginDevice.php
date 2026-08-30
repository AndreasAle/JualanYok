<?php

namespace App\Listeners;

use App\Models\UserLoginDevice;
use App\Services\NotificationCenterService;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class RecordLoginDevice
{
    public function __construct(
        private readonly Request $request,
        private readonly NotificationCenterService $notifications,
    ) {}

    public function handle(Login $event): void
    {
        if ($this->request->is('admin/pengguna/*/impersonate') || ! $event->user) {
            return;
        }

        $agent = mb_substr((string) $this->request->userAgent(), 0, 500);
        $fingerprint = hash('sha256', $agent.'|'.$this->request->header('accept-language', ''));
        $knownCount = UserLoginDevice::where('user_id', $event->user->id)->count();
        $device = UserLoginDevice::firstOrCreate(
            ['user_id' => $event->user->id, 'fingerprint' => $fingerprint],
            [
                'ip_hash' => hash('sha256', (string) $this->request->ip().'|'.config('app.key')),
                'user_agent' => $agent,
                'last_used_at' => now(),
            ],
        );

        if (! $device->wasRecentlyCreated) {
            $device->forceFill([
                'ip_hash' => hash('sha256', (string) $this->request->ip().'|'.config('app.key')),
                'last_used_at' => now(),
            ])->save();

            return;
        }

        // The first device belongs to account creation/onboarding and does not
        // need a second email next to welcome + verification.
        if ($knownCount === 0) {
            return;
        }

        $this->notifications->send($event->user, [
            'type' => 'security.new_device',
            'category' => 'security',
            'priority' => 'high',
            'title' => 'Login dari perangkat baru',
            'message' => 'Kami mendeteksi browser atau perangkat yang belum pernah dipakai untuk akun ini.',
            'url' => route($event->user->is_creator ? 'creator.settings' : 'notifications.index'),
            'action_label' => 'Periksa akun',
            'action_required' => true,
            'group_key' => 'security:device:'.$device->id,
            'tone' => 'warning',
            'meta' => ['device_id' => $device->id],
        ]);
    }
}
