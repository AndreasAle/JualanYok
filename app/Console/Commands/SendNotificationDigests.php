<?php

namespace App\Console\Commands;

use App\Models\NotificationDigestState;
use App\Models\User;
use App\Notifications\NotificationDigest;
use App\Services\NotificationCenterService;
use App\Services\NotificationPreferenceService;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class SendNotificationDigests extends Command
{
    protected $signature = 'jualanyok:notification-digests {--user=}';

    protected $description = 'Kirim ringkasan email untuk kategori notifikasi berfrekuensi harian';

    public function handle(NotificationPreferenceService $preferences, NotificationCenterService $center): int
    {
        $query = User::query()->where('status', 'active')->whereNotNull('email');
        $query->when($this->option('user'), fn ($q, $id) => $q->whereKey($id));

        $sent = 0;
        $query->with('notificationPreferences')->chunkById(100, function ($users) use ($preferences, $center, &$sent) {
            foreach ($users as $user) {
                $daily = collect($preferences->categories())->keys()
                    ->filter(fn (string $category) => $preferences->frequency($user, $category) === 'daily')
                    ->values();

                if ($daily->isEmpty()) {
                    continue;
                }

                $state = NotificationDigestState::firstOrCreate(['user_id' => $user->id]);
                $since = $state->last_sent_at ?? now()->subDay();
                $notifications = $user->notifications()
                    ->whereNull('archived_at')
                    ->where('created_at', '>', $since)
                    ->latest()
                    ->limit(50)
                    ->get()
                    ->filter(fn (DatabaseNotification $notification) => $daily->contains($notification->data['category'] ?? 'system'))
                    ->map(fn (DatabaseNotification $notification) => $center->present($notification))
                    ->values();

                if ($notifications->isEmpty()) {
                    $state->forceFill(['last_sent_at' => now()])->save();

                    continue;
                }

                $user->notify(new NotificationDigest($notifications->all()));
                $state->forceFill(['last_sent_at' => now()])->save();
                $sent++;
            }
        });

        $this->info("Ringkasan notifikasi dijadwalkan untuk {$sent} pengguna.");

        return self::SUCCESS;
    }
}
