<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Role;
use App\Services\AffiliateService;
use Database\Seeders\StorefrontTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_a_creator_can_add_reorder_duplicate_and_delete_blocks(): void
    {
        $store = $this->makeStore();
        $creator = $store->owner;

        $this->actingAs($creator)
            ->post('/dashboard/blocks', ['type' => 'HEADING', 'content' => ['text' => 'Halo']])
            ->assertRedirect();

        $this->actingAs($creator)
            ->post('/dashboard/blocks', ['type' => 'TEXT', 'content' => ['body' => 'Isi']])
            ->assertRedirect();

        $blocks = $store->blocks()->get();
        $this->assertCount(2, $blocks);
        $this->assertSame([0, 1], $blocks->pluck('position')->all());

        // Reorder
        $this->actingAs($creator)->post('/dashboard/blocks/reorder', [
            'ids' => $blocks->pluck('id')->reverse()->values()->all(),
        ]);

        $this->assertSame(1, $blocks[0]->fresh()->position);

        // Duplicate
        $this->actingAs($creator)->post("/dashboard/blocks/{$blocks[0]->id}/duplicate");
        $this->assertSame(3, $store->blocks()->count());

        // Delete (soft)
        $this->actingAs($creator)->delete("/dashboard/blocks/{$blocks[1]->id}");
        $this->assertSame(2, $store->blocks()->count());
    }

    public function test_editing_a_published_store_writes_to_draft_until_published(): void
    {
        $store = $this->makeStore(attributes: ['is_published' => true]);

        $block = Block::create([
            'store_id' => $store->id,
            'type' => 'TEXT',
            'position' => 0,
            'content' => ['body' => 'Versi live'],
            'draft_content' => ['body' => 'Versi live'],
        ]);

        $this->actingAs($store->owner)->put("/dashboard/blocks/{$block->id}", [
            'content' => ['body' => 'Versi baru'],
        ]);

        $block->refresh();

        $this->assertSame('Versi live', $block->content['body'], 'Public content is untouched.');
        $this->assertSame('Versi baru', $block->draft_content['body']);
        $this->assertTrue($block->hasUnpublishedChanges());

        // Publishing promotes every draft at once.
        $this->actingAs($store->owner)->post('/dashboard/toko/publish');

        $this->assertSame('Versi baru', $block->fresh()->content['body']);
    }

    public function test_a_scheduled_block_is_hidden_outside_its_window(): void
    {
        $store = $this->makeStore();

        Block::create([
            'store_id' => $store->id,
            'type' => 'TEXT',
            'position' => 0,
            'content' => ['body' => 'Belum waktunya'],
            'starts_at' => now()->addWeek(),
        ]);

        $visible = $store->blocks()->visibleNow()->count();

        $this->assertSame(0, $visible);
    }

    public function test_applying_a_template_replaces_the_blocks(): void
    {
        $this->seed(StorefrontTemplateSeeder::class);

        $store = $this->makeStore();
        Block::create(['store_id' => $store->id, 'type' => 'TEXT', 'position' => 0]);

        $this->actingAs($store->owner)
            ->post('/dashboard/toko/template/creator-digital', ['replace' => true])
            ->assertRedirect();

        $types = $store->fresh()->blocks->pluck('type')->map(fn ($t) => $t->value)->all();

        $this->assertGreaterThan(5, count($types));
        $this->assertContains('FEATURED_PRODUCTS', $types);
    }

    public function test_a_premium_template_is_gated_by_plan(): void
    {
        $this->seed(StorefrontTemplateSeeder::class);

        $store = $this->makeStore();

        $this->actingAs($store->owner)
            ->post('/dashboard/toko/template/kelas-online')
            ->assertSessionHasErrors('plan');
    }

    public function test_a_creator_can_apply_a_premium_background_without_changing_the_template_layout(): void
    {
        $store = $this->makeStore();
        Block::create(['store_id' => $store->id, 'type' => 'TEXT', 'position' => 0]);
        $blockIds = $store->blocks()->pluck('id')->all();

        $background = 'linear-gradient(145deg, #F7F4FF 0%, #F0EDFF 52%, #FFF3F6 100%)';

        $this->actingAs($store->owner)->put('/dashboard/toko/tema', [
            'primary_color' => '#111827',
            'accent_color' => '#7C3AED',
            'background_type' => 'gradient',
            'background_value' => $background,
            'font_family' => 'jakarta',
            'button_style' => 'rounded',
            'card_style' => 'soft',
            'product_layout' => 'grid',
            'color_scheme' => 'light',
            'extras' => [
                'surface_color' => '#FFFDF8',
                'badge_background_color' => '#F3E8FF',
                'badge_text_color' => '#6B21A8',
                'contact_button_color' => '#0F766E',
                'spacing' => 'airy',
            ],
        ])->assertSessionHasNoErrors();

        $theme = $store->theme()->firstOrFail();
        $this->assertSame($background, $theme->background_value);
        $this->assertSame('#F3E8FF', $theme->extras['badge_background_color']);
        $this->assertSame('#0F766E', $theme->extras['contact_button_color']);
        $this->assertSame('airy', $theme->extras['spacing']);
        $this->assertSame($blockIds, $store->blocks()->pluck('id')->all());
    }

    public function test_publishing_requires_at_least_one_block(): void
    {
        $store = $this->makeStore(attributes: ['is_published' => false]);

        $this->actingAs($store->owner)
            ->post('/dashboard/toko/publish')
            ->assertSessionHas('error');

        $this->assertFalse($store->fresh()->is_published);
    }

    public function test_a_creator_can_create_and_delete_a_product(): void
    {
        $store = $this->makeStore();

        $this->actingAs($store->owner)->post('/dashboard/produk', [
            'type' => 'DIGITAL',
            'name' => 'E-book Baru',
            'price' => 120000,
            'status' => 'ACTIVE',
            'visibility' => 'public',
            'min_quantity' => 1,
        ])->assertRedirect();

        $product = Product::where('store_id', $store->id)->firstOrFail();

        $this->assertSame('e-book-baru', $product->slug);
        $this->assertEquals(120000, (float) $product->price);

        $this->actingAs($store->owner)->delete("/dashboard/produk/{$product->id}");

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_lead_form_records_consent_and_a_marketing_subscription(): void
    {
        $store = $this->makeStore();

        $this->post("/{$store->username}/leads", [
            'name' => 'Nadia',
            'email' => 'nadia@example.test',
            'consent' => true,
        ])->assertRedirect();

        $lead = Lead::firstOrFail();

        $this->assertTrue((bool) $lead->consent);
        $this->assertDatabaseHas('marketing_consents', [
            'store_id' => $store->id,
            'email' => 'nadia@example.test',
            'subscribed' => true,
        ]);
    }

    public function test_a_lead_without_consent_is_rejected(): void
    {
        $store = $this->makeStore();

        $this->post("/{$store->username}/leads", [
            'email' => 'nadia@example.test',
        ])->assertSessionHasErrors('consent');

        $this->assertSame(0, Lead::count());
    }

    public function test_visiting_with_a_referral_code_records_a_click_and_sets_the_cookie(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['affiliate_enabled' => true]);
        $program = $this->makeAffiliateProgram($store);
        $affiliate = $this->makeUser([Role::AFFILIATE]);

        $link = app(AffiliateService::class)->linkFor($program, $affiliate->id, $product);

        $this->get("/{$store->username}?ref={$link->code}")
            ->assertOk()
            ->assertCookie('jy_ref', $link->code);

        $this->assertSame(1, $link->fresh()->clicks);
        $this->assertSame(1, $link->clicks()->count());
    }

    public function test_a_referral_code_from_another_store_is_ignored(): void
    {
        $storeA = $this->makeStore();
        $storeB = $this->makeStore();

        $product = $this->makeProduct($storeA, ['affiliate_enabled' => true]);
        $program = $this->makeAffiliateProgram($storeA);
        $affiliate = $this->makeUser([Role::AFFILIATE]);

        $link = app(AffiliateService::class)->linkFor($program, $affiliate->id, $product);

        $this->get("/{$storeB->username}?ref={$link->code}")->assertOk();

        $this->assertSame(0, $link->fresh()->clicks);
    }

    public function test_block_clicks_are_counted(): void
    {
        $store = $this->makeStore();

        $block = Block::create([
            'store_id' => $store->id,
            'type' => 'LINK_BUTTON',
            'position' => 0,
            'content' => ['label' => 'Klik', 'url' => 'https://example.test'],
        ]);

        $this->post("/{$store->username}/blocks/{$block->id}/click")->assertNoContent();

        $this->assertSame(1, $block->fresh()->clicks);
    }
}
