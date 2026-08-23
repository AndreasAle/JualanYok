<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\StorefrontTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Query-count budgets for the pages that get hit most.
 *
 * These are guardrails, not micro-optimisation: a page whose query count grows
 * with the number of products or blocks will keep working in development and
 * fall over once a real store has a full catalogue.
 */
class PerformanceAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->seed(StorefrontTemplateSeeder::class);
        $this->seed(DemoSeeder::class);
    }

    private function countQueries(callable $action): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $action();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_the_public_storefront_stays_within_its_query_budget(): void
    {
        $store = Store::where('username', 'kreatorkita')->firstOrFail();

        $queries = $this->countQueries(function () use ($store) {
            $this->get("/{$store->username}")->assertOk();
        });

        $this->assertLessThan(
            40,
            $queries,
            "Storefront ran {$queries} queries — likely an N+1 when hydrating blocks or products.",
        );
    }

    public function test_the_storefront_does_not_scale_queries_with_block_count(): void
    {
        $store = Store::where('username', 'kreatorkita')->firstOrFail();

        $before = $this->countQueries(fn () => $this->get("/{$store->username}"));

        // Duplicate the product blocks; query count should stay roughly flat.
        foreach ($store->blocks()->whereIn('type', ['FEATURED_PRODUCTS', 'PRODUCT_COLLECTION'])->get() as $block) {
            $copy = $block->replicate();
            $copy->position = $block->position + 100;
            $copy->save();
        }

        $after = $this->countQueries(fn () => $this->get("/{$store->username}"));

        $this->assertLessThanOrEqual(
            $before + 6,
            $after,
            "Query count jumped from {$before} to {$after} after adding blocks — the block hydration is N+1.",
        );
    }

    /**
     * A product page must not query once per variant.
     *
     * Variants inherit the parent price when their own is null, and resolving
     * that through the relation made every variant fetch its own copy of the
     * product — an N+1 in production and a hard 500 under strict local mode.
     */
    public function test_the_product_page_does_not_scale_queries_with_variant_count(): void
    {
        $store = Store::where('username', 'racunstyle')->firstOrFail();

        $product = $store->products()
            ->publiclyListed()
            ->whereHas('variants')
            ->firstOrFail();

        $url = "/{$store->username}/p/{$product->slug}";

        $before = $this->countQueries(fn () => $this->get($url)->assertOk());

        // Same product, four times the options.
        $template = $product->variants()->firstOrFail();

        for ($i = 0; $i < 12; $i++) {
            $copy = $template->replicate();
            $copy->name = "Varian tambahan {$i}";
            $copy->sku = null;
            // Null price is the case that triggered the parent lookup.
            $copy->price = null;
            $copy->position = 100 + $i;
            $copy->save();
        }

        $after = $this->countQueries(fn () => $this->get($url)->assertOk());

        $this->assertLessThanOrEqual(
            $before + 3,
            $after,
            "Product page went from {$before} to {$after} queries after adding 12 variants — each variant is fetching its own product.",
        );
    }

    public function test_the_creator_dashboard_stays_within_its_query_budget(): void
    {
        $creator = User::where('email', 'kreator@jualanyok.test')->firstOrFail();

        $queries = $this->countQueries(function () use ($creator) {
            $this->actingAs($creator)->get('/dashboard')->assertOk();
        });

        $this->assertLessThan(50, $queries, "Dashboard ran {$queries} queries.");
    }

    public function test_the_product_list_does_not_scale_queries_with_product_count(): void
    {
        $creator = User::where('email', 'kreator@jualanyok.test')->firstOrFail();
        $store = $creator->store;

        $before = $this->countQueries(
            fn () => $this->actingAs($creator)->get('/dashboard/produk'),
        );

        for ($i = 0; $i < 8; $i++) {
            $this->makeProduct($store, ['name' => "Produk Tambahan {$i}"]);
        }

        $after = $this->countQueries(
            fn () => $this->actingAs($creator)->get('/dashboard/produk'),
        );

        $this->assertLessThanOrEqual(
            $before + 4,
            $after,
            "Query count grew from {$before} to {$after} with 8 more products — N+1 in the product list.",
        );
    }

    public function test_the_affiliate_marketplace_stays_within_its_query_budget(): void
    {
        $affiliate = User::where('email', 'affiliate@jualanyok.test')->firstOrFail();

        $queries = $this->countQueries(function () use ($affiliate) {
            $this->actingAs($affiliate)->get('/affiliate/marketplace')->assertOk();
        });

        $this->assertLessThan(60, $queries, "Affiliate marketplace ran {$queries} queries.");
    }
}
