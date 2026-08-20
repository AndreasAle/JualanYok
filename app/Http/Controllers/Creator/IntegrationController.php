<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchStoreWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    private const EVENTS = ['order.paid', 'order.refunded', 'lead.created', 'product.created'];

    public function __construct(private readonly PlanService $plans) {}

    public function index(Request $request): Response
    {
        $store = $request->user()->store;

        return Inertia::render('Creator/Integrations', [
            'pixels' => $store->pixels ?? [],
            'endpoints' => $store->webhookEndpoints()
                ->withCount(['deliveries as failed_count' => fn ($q) => $q->where('status', 'failed')])
                ->get()
                ->map(fn (WebhookEndpoint $e) => [
                    'id' => $e->id,
                    'url' => $e->url,
                    'events' => $e->events,
                    'is_active' => (bool) $e->is_active,
                    'failure_count' => $e->failure_count,
                    'failed_count' => $e->failed_count,
                    'last_success_at' => $e->last_success_at?->diffForHumans(),
                ]),
            'deliveries' => $store->webhookEndpoints()->exists()
                ? WebhookDelivery::whereIn('webhook_endpoint_id', $store->webhookEndpoints()->pluck('id'))
                    ->latest()
                    ->limit(20)
                    ->get(['id', 'event', 'status', 'response_status', 'attempt', 'created_at'])
                    ->map(fn ($d) => [
                        'id' => $d->id,
                        'event' => $d->event,
                        'status' => $d->status,
                        'response_status' => $d->response_status,
                        'attempt' => $d->attempt,
                        'created_at' => $d->created_at->diffForHumans(),
                    ])
                : [],
            'availableEvents' => self::EVENTS,
            'permissions' => [
                'pixels' => $this->plans->allows($request->user(), PlanService::PIXELS),
                'webhooks' => $this->plans->allows($request->user(), PlanService::WEBHOOKS),
            ],
        ]);
    }

    public function updatePixels(Request $request)
    {
        $this->plans->ensureAllowed($request->user(), PlanService::PIXELS, 'pixel & analytics');

        // Only IDs are accepted — never raw script tags, so nothing arbitrary
        // can be injected into a storefront page.
        $data = $request->validate([
            'meta_pixel_id' => ['nullable', 'string', 'regex:/^[0-9]{5,25}$/'],
            'tiktok_pixel_id' => ['nullable', 'string', 'regex:/^[A-Z0-9]{10,30}$/'],
            'ga4_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{6,15}$/'],
            'gtm_id' => ['nullable', 'string', 'regex:/^GTM-[A-Z0-9]{4,12}$/'],
        ], [
            '*.regex' => 'Format ID tidak valid. Masukkan ID-nya saja, bukan script.',
        ]);

        $request->user()->store->update(['pixels' => array_filter($data)]);

        return back()->with('success', 'Integrasi pixel disimpan.');
    }

    public function storeWebhook(Request $request)
    {
        $this->plans->ensureAllowed($request->user(), PlanService::WEBHOOKS, 'webhook');

        $data = $request->validate([
            'url' => ['required', 'url', 'max:500', 'starts_with:https://'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:'.implode(',', self::EVENTS)],
        ], [
            'url.starts_with' => 'URL webhook harus HTTPS.',
        ]);

        $secret = 'whsec_'.Str::random(40);

        $request->user()->store->webhookEndpoints()->create([
            'url' => $data['url'],
            'events' => $data['events'],
            'secret' => $secret,
        ]);

        // Shown once — it is encrypted at rest and never returned again.
        return back()->with('success', 'Webhook dibuat. Simpan secret ini: '.$secret);
    }

    public function testWebhook(Request $request, WebhookEndpoint $endpoint)
    {
        abort_unless($endpoint->store_id === $request->user()->store->id, 403);

        DispatchStoreWebhook::dispatch($endpoint->store_id, 'order.paid', [
            'test' => true,
            'order_number' => 'JY-TEST-000001',
            'total' => 150000,
        ]);

        return back()->with('info', 'Test webhook dikirim, cek log pengiriman sebentar lagi.');
    }

    public function destroyWebhook(Request $request, WebhookEndpoint $endpoint)
    {
        abort_unless($endpoint->store_id === $request->user()->store->id, 403);

        $endpoint->delete();

        return back()->with('success', 'Webhook dihapus.');
    }
}
