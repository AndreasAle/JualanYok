<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSocialPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_product_social_metadata_is_rendered_in_the_initial_html(): void
    {
        $store = $this->makeStore(attributes: [
            'username' => 'toko-preview',
            'name' => 'Toko Preview',
        ]);
        $product = $this->makeProduct($store, [
            'name' => 'Sepatu Premium',
            'short_description' => 'Sepatu nyaman untuk dipakai setiap hari.',
            'thumbnail_path' => 'stores/preview/products/sepatu.jpg',
            'price' => 350000,
        ]);

        $response = $this->get(route('storefront.product', [$store->username, $product->slug]));

        $response->assertOk();
        $response->assertSee('<meta property="og:type" content="product">', false);
        $response->assertSee('<meta property="og:title" content="Sepatu Premium — Toko Preview">', false);
        $response->assertSee('<meta property="og:description" content="Sepatu nyaman untuk dipakai setiap hari.">', false);
        $response->assertSee('<meta property="og:image" content="'.url('/storage/stores/preview/products/sepatu.jpg').'">', false);
        $response->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
        $response->assertSee('<meta property="product:price:amount" content="350000.00">', false);
    }

    public function test_product_social_metadata_escapes_creator_content(): void
    {
        $store = $this->makeStore(attributes: ['username' => 'toko-aman']);
        $product = $this->makeProduct($store, [
            'name' => 'Produk Aman',
            'short_description' => '\"><script>alert(1)</script>',
            'thumbnail_path' => 'stores/preview/products/aman.jpg',
        ]);

        $response = $this->get(route('storefront.product', [$store->username, $product->slug]));

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
    }
}
