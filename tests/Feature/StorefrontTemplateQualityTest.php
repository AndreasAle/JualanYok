<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Models\Block;
use App\Models\Role;
use App\Models\StorefrontTemplate;
use App\Services\StoreProvisionService;
use App\Support\BlockStyle;
use Database\Seeders\StorefrontTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What makes a template worth starting from.
 *
 * A creator applying one should land on a page that already looks finished. The
 * checks here are the difference between that and a stack of empty sections:
 * every block type real, styling used deliberately rather than everywhere, a
 * palette that commits, and copy in the language the shop is written in.
 */
class StorefrontTemplateQualityTest extends TestCase
{
    use RefreshDatabase;

    private const SHOWCASE = ['CAROUSEL', 'MARQUEE', 'STATS', 'LOGO_CLOUD', 'BEFORE_AFTER', 'STEPS'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->seed(StorefrontTemplateSeeder::class);
    }

    /** @return \Illuminate\Support\Collection<int, StorefrontTemplate> */
    private function templates()
    {
        return StorefrontTemplate::orderBy('sort_order')->get();
    }

    public function test_every_block_in_every_template_is_a_real_block(): void
    {
        foreach ($this->templates() as $template) {
            foreach ($template->blueprint as $block) {
                $this->assertNotNull(
                    BlockType::tryFrom($block['type']),
                    "{$template->slug} memakai block yang tidak ada: {$block['type']}",
                );
            }
        }
    }

    public function test_every_style_token_is_one_the_renderer_accepts(): void
    {
        foreach ($this->templates() as $template) {
            foreach ($template->blueprint as $block) {
                foreach ($block['style'] ?? [] as $key => $value) {
                    $this->assertArrayHasKey($key, BlockStyle::OPTIONS, "{$template->slug}: token {$key} tidak dikenal");
                    $this->assertContains(
                        (string) $value,
                        BlockStyle::OPTIONS[$key],
                        "{$template->slug}: nilai {$key}={$value} tidak valid",
                    );
                }
            }
        }
    }

    public function test_a_template_is_a_page_not_a_stack_of_two_sections(): void
    {
        foreach ($this->templates() as $template) {
            $this->assertGreaterThanOrEqual(
                6,
                count($template->blueprint),
                "{$template->slug} terlalu pendek untuk terasa jadi.",
            );
        }
    }

    public function test_styling_is_used_but_never_on_everything(): void
    {
        foreach ($this->templates() as $template) {
            $blocks = collect($template->blueprint);
            $styled = $blocks->filter(fn (array $b) => ! empty($b['style']))->count();

            $this->assertGreaterThan(0, $styled, "{$template->slug} tidak memakai style sama sekali.");

            // Bands are emphasis, and emphasis on every section is emphasis on
            // none. Only the tinted backgrounds are counted — alignment and
            // width are layout, not decoration.
            $bands = $blocks->filter(
                fn (array $b) => ! in_array($b['style']['background'] ?? 'none', ['none', 'outline'], true),
            )->count();

            $this->assertLessThanOrEqual(
                4,
                $bands,
                "{$template->slug} punya {$bands} blok berlatar warna — terlalu ramai.",
            );
        }
    }

    public function test_a_shop_template_shows_off_rather_than_only_listing(): void
    {
        foreach ($this->templates() as $template) {
            // The link-in-bio card is deliberately bare: on a page that short a
            // single styled band would be the only thing anyone saw.
            if ($template->slug === 'minimal-link') {
                continue;
            }

            $showcase = collect($template->blueprint)
                ->pluck('type')
                ->intersect(self::SHOWCASE)
                ->count();

            $this->assertGreaterThanOrEqual(
                2,
                $showcase,
                "{$template->slug} tidak memakai satu pun block showcase.",
            );
        }
    }

    public function test_each_template_commits_to_a_palette(): void
    {
        $primaries = [];

        foreach ($this->templates() as $template) {
            $theme = $template->theme;

            foreach (['primary_color', 'accent_color', 'font_family', 'color_scheme'] as $key) {
                $this->assertNotEmpty($theme[$key] ?? null, "{$template->slug} belum menetapkan {$key}");
            }

            $this->assertNotEmpty($theme['extras']['surface_color'] ?? null, "{$template->slug} tanpa surface_color");
            $this->assertNotEmpty($theme['extras']['contact_button_color'] ?? null, "{$template->slug} tanpa warna kontak");

            $primaries[] = $theme['primary_color'];
        }

        // Seven templates that differ only in copy are one template.
        $this->assertSame(count($primaries), count(array_unique($primaries)), 'Ada template dengan warna utama kembar.');
    }

    public function test_applying_a_template_lays_down_a_styled_page(): void
    {
        $template = StorefrontTemplate::where('slug', 'creator-digital')->firstOrFail();
        $user = $this->makeUser([Role::CREATOR], ['username' => 'kreatorbaru']);

        $store = app(StoreProvisionService::class)->create(
            $user,
            ['username' => 'kreatorbaru', 'name' => 'Kreator Baru'],
            $template,
        );

        $blocks = Block::where('store_id', $store->id)->orderBy('position')->get();

        $this->assertCount(count($template->blueprint), $blocks);
        $this->assertGreaterThan(0, $blocks->filter(fn (Block $b) => ! empty($b->style))->count());

        // The palette travels with the blocks; a template that only set colours
        // or only set layout would land as half a design.
        $this->assertSame('#4F46E5', $store->theme->primary_color);
        $this->assertSame('#FFFFFF', $store->theme->extras['surface_color']);
    }

    public function test_a_style_the_renderer_does_not_know_never_reaches_a_block(): void
    {
        $template = StorefrontTemplate::where('slug', 'creator-digital')->firstOrFail();

        // Templates are data. Data that ends up on a rendered page is not
        // trusted because of where it happens to be stored.
        $template->forceFill(['blueprint' => [[
            'type' => 'TEXT',
            'content' => ['body' => 'Halo'],
            'style' => ['background' => 'dark', 'padding' => 'lg', 'position' => 'fixed', 'z-index' => '9999'],
        ]]])->save();

        $user = $this->makeUser([Role::CREATOR], ['username' => 'kreatorlain']);
        $store = app(StoreProvisionService::class)->create($user, ['username' => 'kreatorlain', 'name' => 'Lain'], $template);

        $style = Block::where('store_id', $store->id)->firstOrFail()->style;

        $this->assertSame('dark', $style['background']);
        $this->assertArrayNotHasKey('position', $style);
        $this->assertArrayNotHasKey('z-index', $style);
    }
}
