<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\CampaignBanner;
use App\Models\HomepageSection;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceSearchTerm;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MarketplaceService
{
    public function recordEvent(Request $request, string $name, array $attributes = []): MarketplaceEvent
    {
        $agent = (string) $request->userAgent();
        $key = (string) config('app.key');

        return MarketplaceEvent::create([
            'name' => $name,
            'store_id' => $attributes['store_id'] ?? null,
            'product_id' => $attributes['product_id'] ?? null,
            'product_category_id' => $attributes['product_category_id'] ?? null,
            'visitor_hash' => hash('sha256', implode('|', [$request->ip(), $agent, now()->toDateString(), $key])),
            'session_hash' => hash('sha256', $request->session()->getId().$key),
            'referrer' => $request->headers->get('referer'),
            'device' => match (true) {
                (bool) preg_match('/tablet|ipad/i', $agent) => 'tablet',
                (bool) preg_match('/mobile|android|iphone/i', $agent) => 'mobile',
                default => 'desktop',
            },
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'affiliate_click_id' => $request->query('aff') ?: $request->query('ref'),
            'meta' => $attributes['meta'] ?? null,
            'created_at' => now(),
        ]);
    }

    public function visibleProducts(): Builder
    {
        return Product::query()
            ->marketplaceVisible()
            ->with([
                'store:id,user_id,username,name,avatar_path,is_verified,creator_category',
                'marketplaceCategory:id,slug,name,icon',
                'inventories:id,product_id,product_variant_id,quantity,reserved,track_stock,allow_backorder',
            ])
            ->withCount(['files', 'activeVariants']);
    }

    public function categories(): Collection
    {
        return Cache::remember('marketplace.categories', now()->addMinutes(15), fn () => ProductCategory::query()
            ->where('is_active', true)
            ->withCount(['marketplaceProducts as products_count' => fn (Builder $q) => $q->marketplaceVisible()])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ProductCategory $category) => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
                'icon' => $category->icon,
                'description' => $category->description,
                'image_url' => $category->image_path ? asset('storage/'.$category->image_path) : null,
                'products_count' => (int) $category->products_count,
            ])
        );
    }

    public function homepage(): array
    {
        $sections = HomepageSection::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return [
            'banners' => CampaignBanner::running()->orderBy('sort_order')->limit(4)->get()->map(fn ($banner) => [
                'id' => $banner->id,
                'eyebrow' => $banner->eyebrow,
                'title' => $banner->title,
                'description' => $banner->description,
                'desktop_image_url' => $banner->desktop_image_path ? asset('storage/'.$banner->desktop_image_path) : null,
                'mobile_image_url' => $banner->mobile_image_path ? asset('storage/'.$banner->mobile_image_path) : null,
                'cta_label' => $banner->cta_label,
                'cta_url' => $banner->cta_url,
                'tone' => $banner->tone,
            ])->values(),
            'categories' => $this->categories(),
            'sections' => $sections->map(fn (HomepageSection $section) => [
                'key' => $section->key,
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'products' => $this->productsForStrategy($section->strategy, $section->item_limit, $section->settings ?? []),
            ])->values(),
            'creators' => $this->featuredCreators(),
            'popular_searches' => MarketplaceSearchTerm::query()
                ->where('search_count', '>', 0)
                ->orderByDesc('search_count')
                ->limit(6)
                ->pluck('term'),
        ];
    }

    public function search(Request $request, ?ProductCategory $category = null): LengthAwarePaginator
    {
        $query = $this->visibleProducts();
        $term = trim((string) $request->query('q'));

        if ($term !== '') {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $term);
            $query->where(function (Builder $q) use ($escaped) {
                $like = '%'.$escaped.'%';
                $q->where('products.name', 'like', $like)
                    ->orWhere('products.short_description', 'like', $like)
                    ->orWhere('products.description', 'like', $like)
                    ->orWhereJsonContains('products.tags', $escaped)
                    ->orWhereHas('store', fn (Builder $store) => $store
                        ->where('name', 'like', $like)
                        ->orWhere('username', 'like', $like))
                    ->orWhereHas('marketplaceCategory', fn (Builder $cat) => $cat->where('name', 'like', $like));
            });

            $search = MarketplaceSearchTerm::query()->firstOrCreate(
                ['term' => mb_strtolower($term)],
                ['search_count' => 0, 'last_searched_at' => now()],
            );
            $search->increment('search_count', 1, ['last_searched_at' => now()]);
        }

        $categoryId = $category?->id ?: $request->integer('category');
        $query->when($categoryId, fn (Builder $q) => $q->where('marketplace_category_id', $categoryId));
        $query->when($request->filled('type'), fn (Builder $q) => $q->whereIn('type', (array) $request->query('type')));
        $query->when($request->boolean('promo'), fn (Builder $q) => $q->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price'));
        $query->when($request->boolean('affiliate'), fn (Builder $q) => $q->where('affiliate_enabled', true));
        $query->when($request->boolean('free'), fn (Builder $q) => $q->where('price', 0));
        $query->when($request->filled('min_price'), fn (Builder $q) => $q->where('price', '>=', max(0, $request->integer('min_price'))));
        $query->when($request->filled('max_price'), fn (Builder $q) => $q->where('price', '<=', max(0, $request->integer('max_price'))));

        match ($request->query('sort', 'relevance')) {
            'latest' => $query->latest('products.created_at'),
            'bestselling' => $query->orderByDesc('sales_count')->latest('products.id'),
            'price_low' => $query->orderBy('price')->latest('products.id'),
            'price_high' => $query->orderByDesc('price')->latest('products.id'),
            default => $query
                ->orderByRaw('CASE WHEN featured_at IS NOT NULL AND (featured_until IS NULL OR featured_until >= ?) THEN 0 ELSE 1 END', [now()])
                ->orderByDesc('marketplace_quality_score')
                ->orderByDesc('sales_count')
                ->latest('products.id'),
        };

        $products = $query->paginate(20)->withQueryString()->through(fn (Product $product) => $this->card($product));

        $this->recordEvent($request, $term !== '' ? 'marketplace_search' : 'marketplace_explore_view', [
            'product_category_id' => $categoryId ?: null,
            'meta' => [
                'term' => $term !== '' ? mb_substr($term, 0, 120) : null,
                'result_count' => $products->total(),
                'page' => $products->currentPage(),
                'sort' => $request->query('sort', 'relevance'),
            ],
        ]);

        return $products;
    }

    public function card(Product $product): array
    {
        $stock = null;
        if ($product->type === ProductType::Physical && $product->relationLoaded('inventories')) {
            $stock = $product->inventories->sum(fn ($inventory) => $inventory->allow_backorder || ! $inventory->track_stock
                ? 1000000
                : $inventory->availableQuantity());
        }

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'short_description' => $product->short_description,
            'type' => $product->type->value,
            'type_label' => $product->type->label(),
            'thumbnail_url' => $product->thumbnailUrl(),
            'price' => (float) $product->price,
            'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
            'discount_percent' => $product->discountPercent(),
            'sales_count' => (int) $product->sales_count,
            'stock' => $stock,
            'affiliate_enabled' => (bool) $product->affiliate_enabled,
            'external_provider' => $product->externalProvider(),
            'is_cartable' => app(CartService::class)->isCartable($product),
            'url' => route('marketplace.products.show', [$product->store->username, $product->slug]),
            'store' => [
                'name' => $product->store->name,
                'username' => $product->store->username,
                'avatar_url' => $product->store->avatarUrl(),
                'is_verified' => (bool) $product->store->is_verified,
                'url' => route('marketplace.creators.show', $product->store->username),
            ],
            'category' => $product->marketplaceCategory ? [
                'name' => $product->marketplaceCategory->name,
                'slug' => $product->marketplaceCategory->slug,
            ] : null,
        ];
    }

    private function productsForStrategy(string $strategy, int $limit, array $settings): Collection
    {
        $query = $this->visibleProducts();

        match ($strategy) {
            'featured' => $query->whereNotNull('featured_at')
                ->where(fn (Builder $q) => $q->whereNull('featured_until')->orWhere('featured_until', '>=', now()))
                ->orderByDesc('featured_at'),
            'popular', 'bestselling' => $query->orderByDesc('sales_count')->orderByDesc('view_count'),
            'digital' => $query->where('type', ProductType::Digital->value)->orderByDesc('sales_count'),
            'learning' => $query->whereIn('type', [ProductType::Course->value, ProductType::Event->value])->latest(),
            'services' => $query->where('type', ProductType::Service->value)->latest(),
            'promo' => $query->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price')->latest(),
            'affiliate' => $query->where('affiliate_enabled', true)->latest(),
            default => $query->latest('products.created_at'),
        };

        if (! empty($settings['type'])) {
            $query->whereIn('type', (array) $settings['type']);
        }

        return $query->limit(min(12, max(1, $limit)))->get()->map(fn (Product $product) => $this->card($product));
    }

    private function featuredCreators(): Collection
    {
        return Store::query()
            ->live()
            ->where('is_featured', true)
            ->withCount(['products' => fn (Builder $q) => $q->marketplaceVisible()])
            ->with(['products' => fn ($query) => $query->marketplaceVisible()->limit(3)])
            ->orderByDesc('featured_at')
            ->limit(6)
            ->get()
            ->map(fn (Store $store) => [
                'name' => $store->name,
                'username' => $store->username,
                'tagline' => $store->tagline,
                'category' => $store->creator_category,
                'is_verified' => (bool) $store->is_verified,
                'avatar_url' => $store->avatarUrl(),
                'cover_url' => $store->coverUrl(),
                'products_count' => (int) $store->products_count,
                'url' => route('marketplace.creators.show', $store->username),
                'previews' => $store->products->map(fn (Product $product) => [
                    'name' => $product->name,
                    'thumbnail_url' => $product->thumbnailUrl(),
                ])->values(),
            ]);
    }
}
