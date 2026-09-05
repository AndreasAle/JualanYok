<?php

namespace Tests\Feature;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Handing a buyer over to a marketplace.
 *
 * The storefront asks before it jumps, and the dialog it shows is only honest
 * if the page is given the two facts it states: where the buyer is going, and
 * which product opens when they arrive. The destination itself stays behind
 * the tracked route, so a raw affiliate link is never printed into the page.
 */
class ExternalHandoffTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        $this->store = $this->makeStore();
        $this->store->forceFill(['is_published' => true])->save();
    }

    private function affiliateProduct()
    {
        return $this->makeProduct($this->store, [
            'type' => 'EXTERNAL',
            'name' => 'Mini kipas Strong Wind High Speed',
            'external_url' => 'https://shopee.co.id/product/123/456?af_id=abc',
            'without_files' => true,
        ]);
    }

    public function test_the_page_knows_which_marketplace_it_is_about_to_open(): void
    {
        $product = $this->affiliateProduct();

        $this->get("/{$this->store->username}/p/{$product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('product.external_provider', 'Shopee')
                ->where('product.external_cta', 'Beli di Shopee'));
    }

    public function test_the_affiliate_link_itself_never_reaches_the_page(): void
    {
        $product = $this->affiliateProduct();

        $response = $this->get("/{$this->store->username}/p/{$product->slug}")->assertOk();

        // The dialog navigates to the tracked route, which records the click
        // and only then forwards. The real destination — tag and all — stays
        // on the server.
        $exposed = $response->viewData('page')['props']['product']['external_url'];

        $this->assertStringEndsWith("/{$this->store->username}/go/{$product->slug}", $exposed);
        $this->assertStringNotContainsString('af_id', $exposed);
        $response->assertDontSee('af_id=abc', escape: false);
    }

    public function test_confirming_lands_on_the_marketplace(): void
    {
        $product = $this->affiliateProduct();

        $this->get("/{$this->store->username}/go/{$product->slug}")
            ->assertRedirect('https://shopee.co.id/product/123/456?af_id=abc');
    }
}
