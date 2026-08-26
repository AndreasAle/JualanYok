<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\AffiliateLink;
use App\Models\AnalyticsEvent;
use App\Models\Block;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Store;
use App\Services\AffiliateService;
use App\Services\AnalyticsService;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\ShippingService;
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
        private readonly CartService $carts,
        private readonly ShippingService $shipping,
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
            'cart' => $this->cartPayload($request, $store),
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
            // Draft preview shows an empty basket; nothing is bought from here.
            'cart' => null,
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

        $product->load(['media', 'variants', 'files', 'course.sections.lessons', 'event.tickets', 'service.availabilityRules', 'membershipPlans']);

        // The variants belong to the product we already have, so hand it to them
        // rather than letting each one fetch its own copy.
        $product->variants->each->setRelation('product', $product);

        return Inertia::render('Storefront/Product', [
            'store' => $this->storePayload($store),
            'product' => $this->productPayload($product, $store->username, detailed: true),
            'related' => $store->products()
                ->publiclyListed()
                ->whereKeyNot($product->id)
                ->withCount(['files', 'activeVariants'])
                ->limit(4)
                ->get()
                ->map(fn ($p) => $this->productPayload($p, $store->username)),
            'cart' => $this->cartPayload($request, $store),
        ]);
    }

    /** Track an outbound marketplace click before handing the visitor off. */
    public function externalRedirect(Request $request, Store $store, Product $product)
    {
        abort_unless($store->isLive(), 404);
        abort_unless($product->store_id === $store->id, 404);
        abort_unless($product->type === ProductType::External, 404);
        abort_unless($product->status->isBuyable() && $product->visibility !== 'private', 404);
        abort_unless(filled($product->external_url), 404);

        $context = $this->analytics->contextFrom($request);
        $context['meta'] = [
            'provider' => $product->externalProvider(),
            'destination_host' => parse_url($product->external_url, PHP_URL_HOST),
        ];

        $this->analytics->record(
            $store,
            AnalyticsEvent::AFFILIATE_CLICK,
            $product,
            $context,
        );

        return redirect()->away($product->external_url);
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
            'from_cart' => ['nullable', 'boolean'],
            // No min:1 here — a cart checkout legitimately sends an empty list,
            // and the real emptiness check happens after the cart is rebuilt.
            'items' => ['required_without:from_cart', 'array'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.meta' => ['nullable', 'array'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => [
                config('payments.providers.ipaymu.enabled') ? 'required' : 'nullable',
                'string',
                'max:32',
            ],
            'note' => ['nullable', 'string', 'max:1000'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'custom_fields' => ['nullable', 'array'],
            'shipping_address' => ['nullable', 'array'],
            'shipping_quote_token' => ['nullable', 'string', 'max:5000'],
            'marketing_consent' => ['nullable', 'boolean'],
            'terms' => ['accepted'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        // A cart checkout never trusts the browser for what is being bought —
        // the lines are rebuilt from the stored cart.
        $cart = $request->boolean('from_cart') ? $this->cartFromCookie($request, $store) : null;

        if ($request->boolean('from_cart')) {
            abort_unless($cart, 419, 'Keranjang tidak ditemukan. Muat ulang halaman.');
        }

        $items = $cart ? $this->carts->checkoutLines($cart) : $data['items'];

        if ($items === []) {
            return back()->withErrors(['items' => 'Keranjang kosong atau semua item sudah tidak tersedia.']);
        }

        $shipping = null;
        if ($this->shipping->requiresShipping($store, $items)) {
            if (blank($data['shipping_quote_token'] ?? null)) {
                return back()->withErrors(['shipping_quote_token' => 'Pilih alamat dan layanan pengiriman dulu.']);
            }

            $shipping = $this->shipping->verifyQuote($store, $items, $data['shipping_quote_token']);
        }

        $this->analytics->record(
            $store,
            AnalyticsEvent::BEGIN_CHECKOUT,
            $store,
            $this->analytics->contextFrom($request),
        );

        $order = $this->checkout->createOrder(
            $store,
            $items,
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
                'shipping_address' => $shipping['destination'] ?? null,
                'shipping_total' => (float) data_get($shipping, 'quote.amount', 0),
                'shipping_method' => data_get($shipping, 'quote.service_name'),
                'shipping_provider' => data_get($shipping, 'quote.provider'),
                'shipping_service' => data_get($shipping, 'quote.service_name'),
                'shipping_courier' => data_get($shipping, 'quote.courier_company'),
                'shipping_courier_type' => data_get($shipping, 'quote.courier_type'),
                'shipping_insurance' => (float) data_get($shipping, 'quote.insurance_fee', 0),
                'shipping_quote' => $shipping['quote'] ?? null,
                'affiliate_code' => $request->cookie('jy_ref'),
                'user_id' => $request->user()?->id,
                'utm' => $request->only(['utm_source', 'utm_medium', 'utm_campaign']),
                'ip' => $request->ip(),
            ],
        );

        if ($shipping) {
            $this->shipping->saveCustomerAddress($order);
        }

        // Emptied only once the order exists, so a failed checkout keeps the
        // basket the buyer spent time filling.
        if ($cart) {
            $this->carts->clear($cart);
        }

        return redirect()->route('checkout.show', $order->number);
    }

    private function cartFromCookie(Request $request, Store $store): ?Cart
    {
        $token = $request->cookie($this->carts->cookieName($store));

        return $token
            ? Cart::where('store_id', $store->id)->where('token', $token)->first()
            : null;
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
                'extras',
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
                ->withCount(['files', 'activeVariants'])
                ->get()
                ->map(fn ($p) => $this->productPayload($p, $store->username));

            $content['products'] = $products->values()->all();
        }

        if (($block->type->value === 'FEATURED_PRODUCTS') && empty($content['products'])) {
            $content['products'] = $store->products()
                ->publiclyListed()
                ->withCount(['files', 'activeVariants'])
                ->latest()
                ->limit((int) ($content['limit'] ?? 4))
                ->get()
                ->map(fn ($p) => $this->productPayload($p, $store->username))
                ->all();
        }

        return $content;
    }

    /**
     * Read-only view of the basket.
     *
     * Deliberately does not create a cart: a storefront gets far more visitors
     * than shoppers, and writing a row per page view would fill the table with
     * empty carts. The cart is created on the first "add".
     */
    private function cartPayload(Request $request, Store $store): ?array
    {
        $token = $request->cookie($this->carts->cookieName($store));

        if (! $token) {
            return null;
        }

        $cart = Cart::where('store_id', $store->id)->where('token', $token)->first();

        return $cart ? $this->carts->payload($cart) : null;
    }

    private function productPayload(Product $product, string $storeUsername, bool $detailed = false): array
    {
        $isExternal = $product->type === ProductType::External && filled($product->external_url);
        $externalProvider = $isExternal ? $product->externalProvider() : null;

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
            // Never expose a raw affiliate destination. The go route records
            // the click first, and digital delivery links remain private.
            'external_url' => $isExternal
                ? route('storefront.external.redirect', [$storeUsername, $product->slug])
                : null,
            'external_provider' => $externalProvider,
            'external_cta' => $externalProvider ? 'Beli di '.$externalProvider : null,
            'is_buyable' => $product->isBuyable(),
            'is_cartable' => $this->carts->isCartable($product),
            // Options have to be picked on the product page, not from a tile.
            'requires_variant' => $product->requiresVariant(),
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
