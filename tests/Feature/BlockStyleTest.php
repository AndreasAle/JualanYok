<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Models\Block;
use App\Models\Store;
use App\Support\BlockStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The block design vocabulary and the showcase blocks built on top of it.
 *
 * `style` used to be an open array rendered straight into an inline style
 * attribute. These tests pin that it is now a closed set of tokens, because a
 * creator who can write arbitrary CSS onto their own storefront can also hide
 * their own checkout button while the builder preview still looks fine.
 */
class BlockStyleTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->store = $this->makeStore();
    }

    private function block(array $attributes = []): Block
    {
        return $this->store->blocks()->create(array_merge([
            'type' => BlockType::Text,
            'content' => ['body' => 'Halo'],
            'position' => 0,
            'is_published' => true,
        ], $attributes));
    }

    public function test_arbitrary_css_cannot_be_saved_onto_a_block(): void
    {
        $block = $this->block();

        $this->actingAs($this->store->owner)
            ->put("/dashboard/blocks/{$block->id}", [
                'style' => [
                    // The shape that would let a block cover its own storefront.
                    'position' => 'fixed',
                    'inset' => '0',
                    'zIndex' => '9999',
                    'display' => 'none',
                    'background' => 'subtle',
                ],
            ])
            ->assertRedirect();

        $saved = $block->fresh()->style;

        $this->assertSame(['background' => 'subtle'], $saved);
        $this->assertArrayNotHasKey('position', $saved);
        $this->assertArrayNotHasKey('zIndex', $saved);
        $this->assertArrayNotHasKey('display', $saved);
    }

    public function test_an_unrecognised_token_value_is_dropped_not_stored(): void
    {
        $block = $this->block();

        $this->actingAs($this->store->owner)
            ->put("/dashboard/blocks/{$block->id}", [
                'style' => [
                    'background' => 'url(https://evil.test/x.png)',
                    'padding' => 'lg',
                    'animation' => 'spin-forever',
                ],
            ])
            ->assertRedirect();

        $this->assertSame(['padding' => 'lg'], $block->fresh()->style);
    }

    public function test_valid_tokens_are_kept_intact(): void
    {
        $block = $this->block();

        $style = [
            'background' => 'gradient',
            'padding' => 'xl',
            'radius' => 'xl',
            'align' => 'center',
            'width' => 'narrow',
            'shadow' => 'glow',
            'animation' => 'slide-up',
            'animation_delay' => '200',
        ];

        $this->actingAs($this->store->owner)
            ->put("/dashboard/blocks/{$block->id}", ['style' => $style])
            ->assertRedirect();

        $this->assertSame($style, $block->fresh()->style);
    }

    public function test_every_advertised_option_actually_survives_a_save(): void
    {
        // A control the builder offers but the server strips would look like a
        // setting that silently refuses to stick.
        foreach (BlockStyle::OPTIONS as $key => $values) {
            foreach ($values as $value) {
                $this->assertSame(
                    [$key => $value],
                    BlockStyle::sanitise([$key => $value]),
                    "Option {$key}={$value} is offered but not accepted.",
                );
            }
        }
    }

    public function test_resolve_fills_the_gaps_a_renderer_relies_on(): void
    {
        $resolved = BlockStyle::resolve(['background' => 'dark']);

        $this->assertSame('dark', $resolved['background']);
        $this->assertSame(BlockStyle::DEFAULTS['align'], $resolved['align']);
        $this->assertSame(BlockStyle::DEFAULTS['animation'], $resolved['animation']);
    }

    public function test_a_non_array_style_is_handled_rather_than_crashing(): void
    {
        $this->assertSame([], BlockStyle::sanitise('position:fixed'));
        $this->assertSame([], BlockStyle::sanitise(null));
        $this->assertSame([], BlockStyle::sanitise(42));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function showcaseTypes(): array
    {
        return [['CAROUSEL'], ['MARQUEE'], ['STATS'], ['LOGO_CLOUD'], ['BEFORE_AFTER'], ['STEPS']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('showcaseTypes')]
    public function test_a_showcase_block_can_be_added_and_renders_on_the_storefront(string $type): void
    {
        $this->actingAs($this->store->owner)
            ->post('/dashboard/blocks', ['type' => $type, 'content' => []])
            ->assertRedirect();

        $block = Block::where('store_id', $this->store->id)->where('type', $type)->firstOrFail();

        $this->assertSame($type, $block->type->value);
        $this->assertNotSame('', $block->type->label());

        // The public page must render it without the renderer falling over.
        $this->get("/{$this->store->username}")->assertOk();
    }

    public function test_showcase_blocks_are_offered_in_the_builder(): void
    {
        $this->actingAs($this->store->owner)
            ->get('/dashboard/toko')
            ->assertOk()
            ->assertInertia(function ($page) {
                $groups = collect($page->toArray()['props']['blockTypes']);
                $all = $groups->flatten(1)->pluck('value');

                foreach (['CAROUSEL', 'MARQUEE', 'STATS', 'LOGO_CLOUD', 'BEFORE_AFTER', 'STEPS'] as $type) {
                    $this->assertContains($type, $all, "{$type} is missing from the block picker.");
                }

                return true;
            });
    }

    public function test_a_creator_cannot_style_another_stores_block(): void
    {
        $other = $this->makeStore();
        $victim = $other->blocks()->create([
            'type' => BlockType::Text,
            'content' => ['body' => 'Punya orang'],
            'position' => 0,
            'is_published' => true,
        ]);

        $this->actingAs($this->store->owner)
            ->put("/dashboard/blocks/{$victim->id}", ['style' => ['background' => 'dark']])
            ->assertForbidden();

        $this->assertNull($victim->fresh()->style);
    }
}
