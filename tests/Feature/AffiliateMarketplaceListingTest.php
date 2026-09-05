<?php

namespace Tests\Feature;

use App\Models\AffiliateProgram;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the affiliate marketplace lists.
 *
 * A product reaches this page through three separate switches on two different
 * screens, which is why creators keep asking why theirs is missing. These
 * assertions pin down each one, so the answer the page gives stays true.
 */
class AffiliateMarketplaceListingTest extends TestCase
{
    use RefreshDatabase;

    private Store $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        $this->seller = $this->makeStore();
        $this->seller->forceFill(['is_published' => true])->save();

        AffiliateProgram::create([
            'store_id' => $this->seller->id,
            'product_id' => null,
            'commission_type' => 'percentage',
            'commission_value' => 20,
            'cookie_days' => 30,
            'auto_approve' => true,
            'is_active' => true,
        ]);
    }

    private function listed(Store $viewerStore): array
    {
        $response = $this->actingAs($viewerStore->owner)->get('/affiliate/marketplace')->assertOk();

        return $response->viewData('page')['props']['products']['data'];
    }

    public function test_a_product_with_affiliate_enabled_is_listed_to_other_creators(): void
    {
        $product = $this->makeProduct($this->seller, ['affiliate_enabled' => true]);

        $viewer = $this->makeStore(null, ['username' => 'penonton']);

        $names = array_column($this->listed($viewer), 'name');

        $this->assertContains($product->name, $names);
    }

    public function test_a_product_without_the_switch_stays_out(): void
    {
        $this->makeProduct($this->seller, ['affiliate_enabled' => false, 'name' => 'Tidak Diaffiliatekan']);

        $viewer = $this->makeStore(null, ['username' => 'penonton']);

        $this->assertNotContains('Tidak Diaffiliatekan', array_column($this->listed($viewer), 'name'));
    }

    public function test_your_own_products_are_not_offered_back_to_you(): void
    {
        // Joining your own program is refused, so listing it is a dead end that
        // only makes the grid look fuller than it is.
        $this->makeProduct($this->seller, ['affiliate_enabled' => true, 'name' => 'Produk Sendiri']);

        $this->assertNotContains('Produk Sendiri', array_column($this->listed($this->seller), 'name'));
    }

    public function test_the_commission_a_promoter_would_earn_is_computed_not_guessed(): void
    {
        $this->makeProduct($this->seller, ['affiliate_enabled' => true, 'price' => 100000]);

        $viewer = $this->makeStore(null, ['username' => 'penonton']);
        $row = $this->listed($viewer)[0];

        $this->assertSame('20%', $row['commission_label'], 'Rate tanpa desimal jangan ditulis 20,00%');
        $this->assertEqualsWithDelta(20000, $row['commission_amount'], 0.01);
        $this->assertSame(30, $row['cookie_days']);
    }

    public function test_the_page_knows_whether_to_explain_how_to_get_listed(): void
    {
        $viewer = $this->makeStore(null, ['username' => 'penonton']);

        $this->actingAs($viewer->owner)
            ->get('/affiliate/marketplace')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('hasStore', true));
    }
}
