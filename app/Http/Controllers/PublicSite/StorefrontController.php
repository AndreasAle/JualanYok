<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\AffiliateLink;
use App\Models\AnalyticsEvent;
use App\Models\Block;
use App\Models\Product;
use App\Models\Store;
use App\Services\AffiliateService;
use App\Services\AnalyticsService;
use App\Services\CheckoutService;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly AffiliateService $affiliates,
        private readonly CheckoutService $checkout,
    ) {}

    public function show(Request $request, Store $store): Response
    {
        abort_unless($store->isLive(), 404);

        $this->captureAffiliateClick($request, $store);

        $context = $this->analytics->contextFrom($request);
        $this->analytics->record($store, AnalyticsEvent::STORE_VIEW, $store, $context);
        $store->increment('view_count');

        return Inertia::render('Storefront/Show', [
            'store' => $this->storePayload($store),
            'blocks' => $this->blocksPayload($store, published: true),
            'isPreview' => false,
        ]);
    }

    /** Authenticated draft preview for the owner — draft content, no tracking. */
    public function preview(Request $request, Store $store): Response
    {
        abort_unless($request->user()?->id === $store->user_id || $request->user()?->isAdmin(), 403);

        return Inertia::render('Storefront/Show', [
            'store' => $this->storePayload($store),
            'blocks' => $this->blocksPayload($store, published: false),
            'isPreview' => true,
        ]);
    }

    public function product(Request $request, Store $store, Product $product): Response
    {
        abort_unless($store->isLive(), 404);
        abort_unless($product->store_id === $store->id, 404);
        abort_unless($product->status->isBuyable() && $product->visibility !== 'private', 404);

        $this->captureAffiliateClick($request, $store);

        $context = $this->analytics->contextFrom($request);
        $this->analytics->record($store, AnalyticsEvent::PRODUCT_VIEW, $product, $context);
        $product->increment('view_count');

        $product->load(['media', 'variants', 'course.sections.lessons', 'event.tickets', 'service.availabilityRules', 'membershipPlans']);

        return Inertia::render('Storefront/Product', [
            'store' => $this->storePayload($store),
            'product' => $this->productPayload($product, detailed: true),
            'related' => $store->products()
                ->publiclyListed()
                ->whereKeyNot($product->id)
                ->limit(4)
                ->get()
                ->map(fn ($p) => $this->productPayload($p)),
        ]);
    }

    public function trackClick(Request $request, Store $store, Block $block)
    {
        abort_unless($block->store_id === $store->id, 404);

        $block->increment('clicks');

        $this->analytics->record(
            $store,
            AnalyticsEvent::BLOCK_CLICK,
            $block,
            $this->analytics->contextFrom($request),
        );

        return response()->noContent();
    }

    public function submitLead(Request $request, Store $store)
    {
        abort_unless($store->isLive(), 404);

        $data = $request->validate([
            'block_id' => ['nullable', 'integer', 'exists:blocks,id'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
            'fields' => ['nullable', 'array'],
            'consent' => ['accepted'],
        ]);

        if (empty($data['email']) && empty($data['phone'])) {
            return back()->withErrors(['email' => 'Isi email atau nomor WhatsApp ya.']);
        }

        $store->leads()->create([
            'block_id' => $data['block_id'] ?? null,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'fields' => $data['fields'] ?? null,
            'consent' => true,
            'source' => 'storefront',
            'utm' => $request->only(['utm_source', 'utm_medium', 'utm_campaign']),
            'ip_address' => $request->ip(),
        ]);

        // Consent is explicit and recorded; nothing is mailed without it.
        if (! empty($data['email'])) {
            $store->marketingConsents()->firstOrCreate(
                ['email' => strtolower($data['email'])],
                [
                    'subscribed' => true,
                    'subscribed_at' => now(),
                    'unsubscribe_token' => Str::random(40),
                    'source' => 'lead_form',
                ],
            );
        }

        $this->analytics->record($store, AnalyticsEvent::LEAD, $store, $this->analytics->contextFrom($request));

        return back()->with('success', 'Makasih! Datamu sudah kami terima.');
    }

    /** Creates the order, then hands off to the checkout page. */
    public function checkout(Request $request, Store $store)
    {
        abort_unless($store->isLive(), 404);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.meta' => ['nullable', 'array'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
            'note' => ['nullable', 'string', 'max:1000'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'custom_fields' => ['nullable', 'array'],
            'shipping_address' => ['nullable', 'array'],
            'marketing_consent' => ['nullable', 'boolean'],
            'terms' => ['accepted'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $this->analytics->record(
            $store,
            AnalyticsEvent::BEGIN_CHECKOUT,
            $store,
            $this->analytics->contextFrom($request),
        );

        $order = $this->checkout->createOrder(
            $store,
            $data['items'],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'marketing_consent' => $data['marketing_consent'] ?? false,
                'user_id' => $request->user()?->id,
            ],
            [
                'idempotency_key' => $data['idempotency_key'],
                'coupon_code' => $data['coupon_code'] ?? null,
                'note' => $data['note'] ?? null,
                'custom_fields' => $data['custom_fields'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'affiliate_code' => $request->cookie('jy_ref'),
                'user_id' => $request->user()?->id,
                'utm' => $request->only(['utm_source', 'utm_medium', 'utm_campaign']),
                'ip' => $request->ip(),
            ],
        );

        return redirect()->route('checkout.show', $order->number);
    }

    /**
     * Stores the referral in a cookie on first sight. Last valid click wins,
     * which is what the affiliate terms promise.
     */
    private function captureAffiliateClick(Request $request, Store $store): void
    {
        $code = $request->query('ref');

        if (! $code) {
            return;
        }

        $link = AffiliateLink::where('code', $code)->where('is_active', true)->first();

        if (! $link || $link->program->store_id !== $store->id) {
            return;
        }

        $context = $this->analytics->contextFrom($request);

        $this->affiliates->trackClick($link, $context + [
            'utm' => array_merge($context['utm'], array_filter([
                'campaign' => $request->query('campaign'),
                'sub_id' => $request->query('sub_id'),
            ])),
        ]);

        $this->analytics->record($store, AnalyticsEvent::AFFILIATE_CLICK, $link, $context);

        Cookie::queue(cookie(
            'jy_ref',
            $code,
            minutes: $link->program->cookie_days * 24 * 60,
            httpOnly: true,
        ));
    }

    private function storePayload(Store $store): array
    {
        $store->loadMissing(['theme', 'template']);

        return [
            'id' => $store->id,
            'username' => $store->username,
            'name' => $store->name,
            'tagline' => $store->tagline,
            'bio' => $store->bio,
            'avatar_url' => $store->avatarUrl(),
            'cover_url' => $store->coverUrl(),
            'socials' => $store->socials ?? [],
            'whatsapp' => $store->whatsapp,
            'seo_title' => $store->seo_title ?? $store->name,
            'seo_description' => $store->seo_description,
            'show_branding' => (bool) $store->show_platform_branding,
            'public_url' => $store->publicUrl(),
            'template_slug' => $store->template?->slug,
            'theme' => $store->theme?->only([
                'primary_color', 'accent_color', 'background_type', 'background_value',
                'font_family', 'button_style', 'card_style', 'product_layout', 'color_scheme',
            ]) ?? [],
        ];
    }

    private function blocksPayload(Store $store, bool $published): array
    {
        $query = $store->blocks();

        if ($published) {
            $query->visibleNow();
        }

        return $query->get()
            ->map(function (Block $block) use ($published, $store) {
                $content = $published ? ($block->content ?? []) : $block->editorContent();

                return [
                    'id' => $block->id,
                    'type' => $block->type->value,
                    'title' => $block->title,
                    'content' => $this->hydrateBlock($store, $block, $content),
                    'style' => $block->style ?? [],
                    'visible_mobile' => (bool) $block->visible_mobile,
                    'visible_desktop' => (bool) $block->visible_desktop,
                    'animation' => $block->animation,
                ];
            })
            ->values()
            ->all();
    }

    /** Resolves product ids referenced by a block into full product payloads. */
    private function hydrateBlock(Store $store, Block $block, array $content): array
    {
        $ids = array_filter(array_merge(
            isset($content['product_id']) ? [$content['product_id']] : [],
            $content['product_ids'] ?? [],
        ));

        if ($ids) {
            $products = Product::whereIn('id', $ids)
                ->where('store_id', $store->id)
                ->active()
                ->get()
                ->map(fn ($p) => $this->productPayload($p));

            $content['products'] = $products->values()->all();
        }

        if (($block->type->value === 'FEATURED_PRODUCTS') && empty($content['products'])) {
            $content['products'] = $store->products()
                ->publiclyListed()
                ->latest()
                ->limit((int) ($content['limit'] ?? 4))
                ->get()
                ->map(fn ($p) => $this->productPayload($p))
                ->all();
        }

        return $content;
    }

    private function productPayload(Product $product, bool $detailed = false): array
    {
        $base = [
            'id' => $product->id,
            'slug' => $product->slug,
            'type' => $product->type->value,
            'type_label' => $product->type->label(),
            'name' => $product->name,
            'short_description' => $product->short_description,
            'thumbnail_url' => $product->thumbnailUrl(),
            'price' => (float) $product->price,
            'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
            'discount_percent' => $product->discountPercent(),
            'is_pay_what_you_want' => (bool) $product->is_pay_what_you_want,
            'minimum_price' => $product->minimum_price ? (float) $product->minimum_price : null,
            'external_url' => $product->external_url,
            'is_buyable' => $product->isBuyable(),
            'sales_count' => (int) $product->sales_count,
        ];

        if (! $detailed) {
            return $base;
        }

        return $base + [
            'description' => $product->description,
            'terms' => $product->terms,
            'checkout_message' => $product->checkout_message,
            'custom_fields' => $product->custom_fields ?? [],
            'min_quantity' => $product->min_quantity,
            'max_quantity' => $product->max_quantity,
            'media' => $product->media->map(fn ($m) => [
                'url' => Media::url($m->path),
                'alt' => $m->alt,
            ]),
            'variants' => $product->variants->where('is_active', true)->values()->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'options' => $v->options,
                'price' => $v->effectivePrice(),
                'stock' => $v->stock,
            ]),
            'course' => $product->course ? [
                'level' => $product->course->level,
                'outcome' => $product->course->outcome,
                'lesson_count' => $product->course->lessons()->count(),
                'duration_minutes' => $product->course->totalDurationMinutes(),
                'sections' => $product->course->sections->map(fn ($s) => [
                    'title' => $s->title,
                    'lessons' => $s->lessons->map(fn ($l) => [
                        'title' => $l->title,
                        'duration_minutes' => $l->duration_minutes,
                        'is_free_preview' => (bool) $l->is_free_preview,
                    ]),
                ]),
            ] : null,
            'event' => $product->event ? [
                'starts_at' => $product->event->starts_at->toIso8601String(),
                'mode' => $product->event->mode,
                'location' => $product->event->location,
                'seats_left' => $product->event->seatsLeft(),
                'tickets' => $product->event->tickets->where('is_active', true)->values(),
            ] : null,
            'service' => $product->service ? [
                'duration_minutes' => $product->service->duration_minutes,
                'timezone' => $product->service->timezone,
                'rules' => $product->service->availabilityRules->where('is_active', true)->values(),
            ] : null,
            'membership_plans' => $product->membershipPlans->where('is_active', true)->values(),
        ];
    }
}
