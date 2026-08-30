<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationPreferenceService
{
    /** @return array<string, array<string, mixed>> */
    public function categories(): array
    {
        return (array) config('notifications.categories', []);
    }

    public function frequency(User $user, string $category): string
    {
        $definition = $this->categories()[$category] ?? null;

        if (! $definition) {
            return 'off';
        }

        if ((bool) ($definition['email_locked'] ?? false)) {
            return 'immediate';
        }

        $stored = $user->relationLoaded('notificationPreferences')
            ? $user->notificationPreferences->firstWhere('category', $category)?->email_frequency
            : $user->notificationPreferences()->where('category', $category)->value('email_frequency');

        return in_array($stored, ['immediate', 'daily', 'off'], true)
            ? $stored
            : (string) ($definition['email_default'] ?? 'off');
    }

    /** @return array<int, string> */
    public function channels(User $user, string $category): array
    {
        $channels = ['database'];

        if ($this->frequency($user, $category) === 'immediate' && filled($user->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function options(User $user): Collection
    {
        $user->loadMissing('notificationPreferences');

        return collect($this->categories())->map(fn (array $definition, string $category) => [
            'category' => $category,
            'label' => $definition['label'],
            'description' => $definition['description'],
            'email_frequency' => $this->frequency($user, $category),
            'email_locked' => (bool) ($definition['email_locked'] ?? false),
        ])->values();
    }

    /** @param array<string, string> $preferences */
    public function update(User $user, array $preferences): void
    {
        foreach ($preferences as $category => $frequency) {
            $definition = $this->categories()[$category] ?? null;

            if (! $definition || (bool) ($definition['email_locked'] ?? false)) {
                continue;
            }

            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'category' => $category],
                ['email_frequency' => $frequency],
            );
        }
    }
}
