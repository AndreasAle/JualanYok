<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Notifications\BusinessNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotificationCenterService
{
    /** @param array<string, mixed> $payload */
    public function send(User $user, array $payload): void
    {
        $this->assertPayload($payload);
        $user->notify(new BusinessNotification($payload));
    }

    /** @param array<string, mixed> $payload */
    public function sendOnce(User $user, array $payload, int $hours = 24): bool
    {
        $groupKey = (string) ($payload['group_key'] ?? '');

        if ($groupKey !== '') {
            $alreadySent = $user->notifications()
                ->where('created_at', '>=', now()->subHours(max(1, $hours)))
                ->latest()
                ->limit(200)
                ->get()
                ->contains(fn (DatabaseNotification $notification) => ($notification->data['group_key'] ?? null) === $groupKey
                );

            if ($alreadySent) {
                return false;
            }
        }

        $this->send($user, $payload);

        return true;
    }

    /** @param array<string, mixed> $payload */
    public function sendToMail(string $email, array $payload): void
    {
        if (blank($email)) {
            return;
        }

        $this->assertPayload($payload);
        Notification::route('mail', $email)->notify(new BusinessNotification($payload));
    }

    /** @param array<int, string> $roles @param array<string, mixed> $payload */
    public function sendToAdmins(array $roles, array $payload): void
    {
        $allowed = array_values(array_intersect($roles, [
            Role::SUPPORT_ADMIN,
            Role::FINANCE_ADMIN,
            Role::SUPER_ADMIN,
        ]));

        if ($allowed === []) {
            return;
        }

        $admins = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', $allowed))
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new BusinessNotification($payload));
        }
    }

    /** @return array<string, mixed> */
    public function present(DatabaseNotification $notification): array
    {
        $data = (array) $notification->data;

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? 'system.info',
            'category' => $data['category'] ?? 'system',
            'priority' => $data['priority'] ?? 'normal',
            'title' => $data['title'] ?? 'Notifikasi',
            'message' => $data['message'] ?? '',
            'url' => $data['url'] ?? null,
            'action_label' => $data['action_label'] ?? null,
            'action_required' => (bool) ($data['action_required'] ?? false),
            'group_key' => $data['group_key'] ?? null,
            'tone' => $data['tone'] ?? 'info',
            'meta' => (array) ($data['meta'] ?? []),
            'is_read' => $notification->read_at !== null,
            'is_resolved' => $notification->resolved_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_human' => $notification->created_at?->diffForHumans(),
            'open_url' => route('notifications.open', $notification->id),
        ];
    }

    /** @return array<string, mixed> */
    public function header(User $user): array
    {
        $base = $user->notifications()->whereNull('archived_at');
        $unread = (clone $base)->whereNull('read_at');
        $actions = (clone $base)
            ->where('data->action_required', true)
            ->whereNull('resolved_at');
        $items = (clone $base)
            ->where(function ($query) {
                $query->whereNull('read_at')
                    ->orWhere(function ($required) {
                        $required->where('data->action_required', true)
                            ->whereNull('resolved_at');
                    });
            })
            ->latest()
            ->limit(30)
            ->get();

        // Collapse repeated low-stock/order-style events in the dropdown. The
        // full center keeps the individual audit trail intact.
        $grouped = $items->groupBy(fn (DatabaseNotification $notification) => $notification->data['group_key'] ?? $notification->id
        )->map(function (Collection $group) {
            $latest = $group->first();
            $item = $this->present($latest);
            $item['group_count'] = $group->count();

            return $item;
        })->take(10)->values();

        return [
            'items' => $grouped,
            'unread_count' => (clone $unread)->count(),
            'action_count' => (clone $actions)->count(),
            'poll_seconds' => (int) config('notifications.poll_seconds', 45),
            'index_url' => route('notifications.index'),
            'read_all_url' => route('notifications.read-all'),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function assertPayload(array $payload): void
    {
        foreach (['type', 'category', 'title', 'message'] as $required) {
            if (blank($payload[$required] ?? null)) {
                throw new \InvalidArgumentException("Notification payload requires {$required}.");
            }
        }

        if (! array_key_exists((string) $payload['category'], config('notifications.categories', []))) {
            throw new \InvalidArgumentException('Unknown notification category.');
        }
    }
}
