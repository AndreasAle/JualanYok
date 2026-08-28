<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Services\AnalyticsService;
use App\Services\MarketplaceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceController extends Controller
{
    public function __construct(
        private readonly MarketplaceService $marketplace,
        private readonly AnalyticsService $analytics,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Marketplace/Explore', [
            'products' => $this->marketplace->search($request),
            'categories' => $this->marketplace->categories(),
            'filters' => $request->only(['q', 'category', 'type', 'min_price', 'max_price', 'promo', 'affiliate', 'free', 'sort']),
            'category' => null,
        ]);
    }

    public function category(Request $request, ProductCategory $category): Response
    {
        abort_unless($category->is_active, 404);

        $this->marketplace->recordEvent($request, 'marketplace_category_view', [
            'product_category_id' => $category->id,
        ]);

        return Inertia::render('Marketplace/Explore', [
            'products' => $this->marketplace->search($request, $category),
            'categories' => $this->marketplace->categories(),
            'filters' => $request->only(['q', 'type', 'min_price', 'max_price', 'promo', 'affiliate', 'free', 'sort']),
            'category' => $category->only(['id', 'slug', 'name', 'description', 'seo_title', 'seo_description']),
        ]);
    }

    public function product(Request $request, Store $store, Product $product): Response
    {
        abort_unless($product->store_id === $store->id, 404);
        $product->loadMissing('store');
        abort_unless($product->isMarketplaceVisible(), 404);

        $product->load(['media', 'files', 'variants', 'inventories', 'marketplaceCategory']);
        $product->loadCount(['files', 'activeVariants']);
        $this->analytics->record($store, AnalyticsEvent::PRODUCT_VIEW, $product, $this->analytics->contextFrom($request) + [
            'meta' => ['source' => 'marketplace'],
        ]);
        $product->increment('view_count');
        $this->marketplace->recordEvent($request, 'marketplace_product_click', [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_category_id' => $product->marketplace_category_id,
        ]);

        $more = $this->marketplace->visibleProducts()
            ->where('store_id', $store->id)
            ->where('products.id', '!=', $product->id)
            ->limit(4)
            ->get()
            ->map(fn (Product $item) => $this->marketplace->card($item));

        $similar = $this->marketplace->visibleProducts()
            ->where('products.id', '!=', $product->id)
            ->when($product->marketplace_category_id, fn ($q) => $q->where('marketplace_category_id', $product->marketplace_category_id))
            ->limit(4)
            ->get()
            ->map(fn (Product $item) => $this->marketplace->card($item));

        return Inertia::render('Marketplace/Product', [
            'product' => $this->marketplace->card($product) + [
                'description' => $product->description,
                'terms' => $product->terms,
                'media' => $product->media->map(fn ($media) => [
                    'url' => asset('storage/'.$media->path),
                    'alt' => $media->alt ?: $product->name,
                ])->values(),
                'variants' => $product->variants->where('is_active', true)->values(),
                'is_buyable' => $product->isBuyable(),
                'storefront_url' => route('storefront.product', [$store->username, $product->slug]),
                'refund_policy_url' => route('pages.refund.policy'),
            ],
            'moreFromCreator' => $more,
            'similarProducts' => $similar,
        ]);
    }

    public function creator(Request $request, Store $store)
    {
        abort_unless($store->isLive(), 404);

        $this->marketplace->recordEvent($request, 'marketplace_creator_click', ['store_id' => $store->id]);

        return redirect()->route('storefront.show', $store->username);
    }
}
