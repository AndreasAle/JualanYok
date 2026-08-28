<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

class MarketplaceSeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'lastmod' => now()->toDateString(), 'priority' => '1.0'],
            ['loc' => route('marketplace.index'), 'lastmod' => now()->toDateString(), 'priority' => '0.9'],
        ]);

        ProductCategory::query()
            ->where('is_active', true)
            ->whereHas('marketplaceProducts', fn (Builder $query) => $query->marketplaceVisible())
            ->orderBy('id')
            ->each(fn (ProductCategory $category) => $urls->push([
                'loc' => route('marketplace.categories.show', $category->slug),
                'lastmod' => $category->updated_at?->toDateString(),
                'priority' => '0.8',
            ]));

        Store::query()
            ->live()
            ->whereHas('owner', fn (Builder $query) => $query->where('status', 'active'))
            ->whereHas('products', fn (Builder $query) => $query->marketplaceVisible())
            ->orderBy('id')
            ->each(fn (Store $store) => $urls->push([
                'loc' => route('marketplace.creators.show', $store->username),
                'lastmod' => $store->updated_at?->toDateString(),
                'priority' => '0.7',
            ]));

        Product::query()
            ->marketplaceVisible()
            ->with('store:id,username')
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($urls) {
                foreach ($products as $product) {
                    $urls->push([
                        'loc' => route('marketplace.products.show', [$product->store->username, $product->slug]),
                        'lastmod' => $product->updated_at?->toDateString(),
                        'priority' => '0.8',
                    ]);
                }
            });

        $xml = view('seo.sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $body = "User-agent: *\nAllow: /\nDisallow: /dashboard\nDisallow: /admin\nDisallow: /checkout\nDisallow: /member\nDisallow: /login\nDisallow: /register\nSitemap: ".route('sitemap')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
