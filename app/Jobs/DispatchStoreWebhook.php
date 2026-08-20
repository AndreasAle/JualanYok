<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Delivers a store event to every endpoint the creator registered, signed with
 * that endpoint's secret. Retries with exponential backoff; each attempt is
 * recorded so the creator can debug failures from the dashboard.
 */
class DispatchStoreWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 60, 300, 900, 3600];

    public function __construct(
        public readonly int $storeId,
        public readonly string $event,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        $endpoints = WebhookEndpoint::where('store_id', $this->storeId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $e) => $e->listensTo($this->event));

        foreach ($endpoints as $endpoint) {
            $this->deliver($endpoint);
        }
    }

    private function deliver(WebhookEndpoint $endpoint): void
    {
        $body = json_encode([
            'event' => $this->event,
            'sent_at' => now()->toIso8601String(),
            'data' => $this->payload,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => $this->event,
            'payload' => $body,
            'attempt' => $this->attempts(),
            'status' => 'pending',
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-JualanYok-Event' => $this->event,
                    'X-JualanYok-Signature' => $endpoint->sign($body),
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $delivery->update([
                'response_status' => $response->status(),
                'response_body' => substr($response->body(), 0, 2000),
                'status' => $response->successful() ? 'success' : 'failed',
                'delivered_at' => now(),
            ]);

            if ($response->successful()) {
                $endpoint->update(['failure_count' => 0, 'last_success_at' => now()]);

                return;
            }

            $endpoint->increment('failure_count');
            $this->release($this->backoff[min($this->attempts(), count($this->backoff)) - 1]);
        } catch (Throwable $e) {
            $delivery->update(['status' => 'failed', 'error' => $e->getMessage()]);
            $endpoint->increment('failure_count');

            throw $e;
        }
    }
}
