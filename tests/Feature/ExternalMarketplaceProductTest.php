<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Models\AnalyticsEvent;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ExternalMarketplaceProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_a_marketplace_product_requires_a_destination_link(): void
    {
        $store = $this->makeStore();

        $this->actingAs($store->owner)
            ->post('/dashboard/produk', $this->payload())
            ->assertSessionHasErrors('external_url');
    }

    public function test_marketplace_products_ignore_internal_price_and_checkout_settings(): void
    {
        $store = $this->makeStore();

        $this->actingAs($store->owner)
            ->post('/dashboard/produk', $this->payload([
                'external_url' => 'https://shopee.co.id/produk-demo-i.123.456',
                'price' => 999000,
                'compare_at_price' => 1200000,
                'is_pay_what_you_want' => true,
                'minimum_price' => 50000,
                'affiliate_enabled' => true,
            ]))
            ->assertSessionHasNoErrors();

        $product = $store->products()->where('type', ProductType::External->value)->firstOrFail();

        $this->assertSame('Shopee', $product->externalProvider());
        $this->assertEquals(0, (float) $product->price);
        $this->assertNull($product->compare_at_price);
        $this->assertFalse($product->is_pay_what_you_want);
        $this->assertFalse($product->affiliate_enabled);
        $this->assertFalse($product->isBuyable());
    }

    public function test_marketplace_click_is_tracked_before_redirecting_to_shopee(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, [
            'type' => ProductType::External,
            'name' => 'Tas kerja pilihan',
            'external_url' => 'https://shopee.co.id/produk-demo-i.123.456',
            'price' => 0,
        ]);

        $this->get("/{$store->username}/go/{$product->slug}")
            ->assertRedirect($product->external_url);

        $this->assertDatabaseHas('analytics_events', [
            'store_id' => $store->id,
            'name' => AnalyticsEvent::AFFILIATE_CLICK,
            'subject_type' => $product->getMorphClass(),
            'subject_id' => $product->id,
        ]);
    }

    public function test_storefront_exposes_a_tracked_url_only_for_marketplace_products(): void
    {
        $store = $this->makeStore();
        $external = $this->makeProduct($store, [
            'type' => ProductType::External,
            'name' => 'Sepatu Shopee',
            'external_url' => 'https://s.shopee.co.id/demo-link',
            'price' => 0,
        ]);
        $digital = $this->makeProduct($store, [
            'name' => 'Template Digital',
            'external_url' => 'https://drive.example.test/private-delivery',
            'without_files' => true,
        ]);

        $this->get("/{$store->username}/p/{$external->slug}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('product.external_provider', 'Shopee')
                ->where('product.external_cta', 'Beli di Shopee')
                ->where('product.external_url', route('storefront.external.redirect', [$store->username, $external->slug]))
            );

        $this->get("/{$store->username}/p/{$digital->slug}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('product.external_url', null)
                ->where('product.external_provider', null)
            );
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => ProductType::External->value,
            'name' => 'Produk marketplace',
            'short_description' => 'Rekomendasi pilihan minggu ini.',
            'description' => '',
            'price' => 0,
            'status' => 'ACTIVE',
            'visibility' => 'public',
            'is_pay_what_you_want' => false,
            'affiliate_enabled' => false,
            'min_quantity' => 1,
        ], $overrides);
    }
}
