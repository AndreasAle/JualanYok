<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, mixed> $payload */
    public function __construct(public readonly array $payload)
    {
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        if ((bool) ($this->payload['email_required'] ?? false)) {
            return ['database', 'mail'];
        }

        return app(NotificationPreferenceService::class)->channels(
            $notifiable,
            (string) ($this->payload['category'] ?? 'system'),
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject((string) ($this->payload['email_subject'] ?? $this->payload['title'] ?? 'Notifikasi JualanYok'))
            ->greeting(filled($notifiable->name ?? null) ? 'Halo '.$notifiable->name.',' : 'Halo,')
            ->line((string) ($this->payload['message'] ?? 'Ada pembaruan penting di akun JualanYok kamu.'));

        foreach ((array) ($this->payload['email_lines'] ?? []) as $line) {
            if (filled($line)) {
                $mail->line((string) $line);
            }
        }

        if (filled($this->payload['url'] ?? null)) {
            $mail->action(
                (string) ($this->payload['action_label'] ?? 'Lihat Detail'),
                (string) $this->payload['url'],
            );
        }

        return $mail->line('Email ini dikirim karena berkaitan dengan aktivitas akun atau preferensi notifikasimu.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => (string) ($this->payload['type'] ?? 'system.info'),
            'category' => (string) ($this->payload['category'] ?? 'system'),
            'priority' => (string) ($this->payload['priority'] ?? 'normal'),
            'title' => (string) ($this->payload['title'] ?? 'Notifikasi'),
            'message' => (string) ($this->payload['message'] ?? ''),
            'url' => $this->payload['url'] ?? null,
            'action_label' => $this->payload['action_label'] ?? null,
            'action_required' => (bool) ($this->payload['action_required'] ?? false),
            'group_key' => $this->payload['group_key'] ?? null,
            'tone' => (string) ($this->payload['tone'] ?? 'info'),
            'meta' => (array) ($this->payload['meta'] ?? []),
        ];
    }
}
