<?php

namespace App\Http\Controllers\Creator;

use App\Enums\BlockType;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\StorefrontTemplate;
use App\Services\PlanService;
use App\Services\StoreProvisionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreBuilderController extends Controller
{
    public function __construct(
        private readonly StoreProvisionService $stores,
        private readonly PlanService $plans,
    ) {}

    public function edit(Request $request): Response
    {
        $store = $request->user()->store->load(['theme', 'blocks', 'template']);

        return Inertia::render('Creator/Builder', [
            'store' => [
                'id' => $store->id,
                'storefront_template_id' => $store->storefront_template_id,
                'template_slug' => $store->template?->slug,
                'username' => $store->username,
                'name' => $store->name,
                'tagline' => $store->tagline,
                'bio' => $store->bio,
                'avatar_url' => $store->avatarUrl(),
                'cover_url' => $store->coverUrl(),
                'socials' => $store->socials ?? [],
                'whatsapp' => $store->whatsapp,
                'is_published' => (bool) $store->is_published,
                'public_url' => $store->publicUrl(),
                'show_branding' => (bool) $store->show_platform_branding,
                'seo_title' => $store->seo_title,
                'seo_description' => $store->seo_description,
            ],
            'theme' => $store->theme,
            'blocks' => $store->blocks->map(fn (Block $b) => $this->blockPayload($b)),
            'blockTypes' => collect(BlockType::cases())
                ->map(fn (BlockType $t) => [
                    'value' => $t->value,
                    'label' => $t->label(),
                    'group' => $t->group(),
                ])
                ->groupBy('group'),
            'products' => $store->products()
                ->active()
                ->get(['id', 'name', 'price', 'thumbnail_path', 'slug', 'type'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => (float) $p->price,
                    'thumbnail_url' => $p->thumbnailUrl(),
                    'type' => $p->type->value,
                ]),
            'templates' => StorefrontTemplate::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'slug', 'name', 'tagline', 'description', 'use_case', 'is_premium', 'theme', 'blueprint'])
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'slug' => $t->slug,
                    'name' => $t->name,
                    'tagline' => $t->tagline,
                    'description' => $t->description,
                    'use_case' => $t->use_case,
                    'is_premium' => (bool) $t->is_premium,
                    'theme' => $t->theme,
                    // Block order drives the miniature preview, so the picker
                    // shows each template's real structure instead of a swatch.
                    'blocks' => collect($t->blueprint ?? [])->pluck('type'),
                ]),
            'limits' => [
                'blocks' => $this->plans->limit($request->user(), PlanService::BLOCKS_LIMIT),
                'blocks_used' => $store->blocks()->count(),
                'can_remove_branding' => $this->plans->allows($request->user(), PlanService::REMOVE_BRANDING),
                'can_use_premium_templates' => $this->plans->allows($request->user(), PlanService::PREMIUM_TEMPLATES),
            ],
        ]);
    }

    public function publish(Request $request)
    {
        $store = $request->user()->store;

        if (! $store->blocks()->exists()) {
            return back()->with('error', 'Tambah minimal satu block dulu sebelum publish.');
        }

        /*
         * An unverified owner cannot go live.
         *
         * Not a formality: the receipt, the download link for a digital order,
         * and every "your buyer paid" alert are sent to that address. A shop
         * whose owner's email bounces takes money and then goes quiet, and the
         * buyer is the one left with nothing.
         */
        if (! $request->user()->hasVerifiedEmail()) {
            return back()->with('error', 'Verifikasi emailmu dulu sebelum toko bisa dipublikasikan.');
        }

        $this->stores->publish($store);

        return redirect()->route('creator.builder', ['published' => 1])
            ->with('success', 'Toko berhasil dipublikasikan. Link tokomu siap dibagikan.');
    }

    public function unpublish(Request $request)
    {
        $this->stores->unpublish($request->user()->store);

        return back()->with('info', 'Toko kamu sekarang tidak bisa diakses publik.');
    }

    public function applyTemplate(Request $request, StorefrontTemplate $template)
    {
        if ($template->is_premium) {
            $this->plans->ensureAllowed($request->user(), PlanService::PREMIUM_TEMPLATES, 'template premium');
        }

        $replace = $request->boolean('replace', true);

        $this->stores->applyBlueprint($request->user()->store, $template, $replace);

        return back()->with('success', "Template {$template->name} berhasil dipasang.");
    }

    private function blockPayload(Block $block): array
    {
        return [
            'id' => $block->id,
            'type' => $block->type->value,
            'type_label' => $block->type->label(),
            'title' => $block->title,
            'content' => $block->editorContent(),
            'style' => $block->style ?? [],
            'position' => $block->position,
            'is_published' => (bool) $block->is_published,
            'visible_mobile' => (bool) $block->visible_mobile,
            'visible_desktop' => (bool) $block->visible_desktop,
            'starts_at' => $block->starts_at?->toIso8601String(),
            'ends_at' => $block->ends_at?->toIso8601String(),
            'animation' => $block->animation,
            'has_unpublished_changes' => $block->hasUnpublishedChanges(),
            'impressions' => $block->impressions,
            'clicks' => $block->clicks,
        ];
    }
}
