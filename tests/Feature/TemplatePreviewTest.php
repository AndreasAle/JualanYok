<?php

namespace Tests\Feature;

use Database\Seeders\StorefrontTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The template preview is the template.
 *
 * It renders the real blueprint through the real storefront components, so the
 * page has to be handed the whole thing — types, content and style. A preview
 * built from a summary would drift from what applying the template actually
 * produces, which is exactly what the hand-drawn mockups it replaces did.
 */
class TemplatePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->seed(StorefrontTemplateSeeder::class);
    }

    public function test_the_catalogue_hands_over_whole_blocks_not_a_summary(): void
    {
        $props = $this->get('/templates')->assertOk()->viewData('page')['props'];

        $template = collect($props['templates'])->firstWhere('slug', 'creator-digital');

        $this->assertNotEmpty($template['blocks']);
        $this->assertSame(count($template['blueprint']), count($template['blocks']));

        foreach ($template['blocks'] as $block) {
            $this->assertArrayHasKey('type', $block);
            $this->assertArrayHasKey('content', $block);
        }

        // The palette travels too, or every preview renders in the default one.
        $this->assertSame('#4F46E5', $template['theme']['primary_color']);
    }

    public function test_product_blocks_are_filled_with_real_listings(): void
    {
        $store = $this->makeStore();
        $store->forceFill(['is_published' => true])->save();
        $product = $this->makeProduct($store, ['name' => 'Contoh Nyata']);
        $product->forceFill(['thumbnail_path' => 'products/thumbnails/a.jpg'])->save();

        $props = $this->get('/templates')->assertOk()->viewData('page')['props'];

        $products = collect($props['templates'])
            ->pluck('blocks')
            ->flatten(1)
            ->firstWhere('type', 'FEATURED_PRODUCTS')['content']['products'] ?? [];

        // A preview of three empty product slots is a preview of an empty shop.
        $this->assertNotEmpty($products);
        $this->assertSame('Contoh Nyata', $products[0]['name']);
    }

    public function test_a_preview_never_offers_something_that_can_be_bought(): void
    {
        $store = $this->makeStore();
        $store->forceFill(['is_published' => true])->save();
        $product = $this->makeProduct($store);
        $product->forceFill(['thumbnail_path' => 'products/thumbnails/a.jpg'])->save();

        $props = $this->get('/templates')->assertOk()->viewData('page')['props'];

        $sample = collect($props['templates'])
            ->pluck('blocks')
            ->flatten(1)
            ->firstWhere('type', 'FEATURED_PRODUCTS')['content']['products'][0] ?? null;

        $this->assertNotNull($sample);

        // Sample content, not a shopfront: nothing here leads to a checkout.
        $this->assertFalse($sample['is_cartable']);
        $this->assertSame('#', $sample['share_url']);
        $this->assertNull($sample['external_url']);
    }

    public function test_the_onboarding_picker_gets_the_blueprint_too(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get('/onboarding')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('templates.0.blueprint'));
    }
}
