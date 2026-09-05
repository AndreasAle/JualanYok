<?php

namespace App\Services;

use App\Enums\BlockType;
use App\Models\AffiliateProgram;
use App\Models\Block;
use App\Models\Store;
use App\Models\StorefrontTemplate;
use App\Models\StoreTheme;
use App\Models\User;
use App\Support\BlockStyle;
use Illuminate\Support\Facades\DB;

/**
 * Creates a store and lays down the blocks from a template blueprint, so a new
 * creator lands on a storefront that already looks finished rather than an
 * empty page.
 */
class StoreProvisionService
{
    public function create(User $user, array $attributes, ?StorefrontTemplate $template = null): Store
    {
        return DB::transaction(function () use ($user, $attributes, $template) {
            $store = Store::create([
                'user_id' => $user->id,
                'storefront_template_id' => $template?->id,
                'username' => $attributes['username'] ?? $user->username,
                'name' => $attributes['name'] ?? $user->name,
                'tagline' => $attributes['tagline'] ?? null,
                'bio' => $attributes['bio'] ?? null,
                'whatsapp' => $attributes['whatsapp'] ?? $user->phone,
                'socials' => $attributes['socials'] ?? [],
                'avatar_path' => $attributes['avatar_path'] ?? null,
                'cover_path' => $attributes['cover_path'] ?? null,
                'seo_title' => $attributes['name'] ?? $user->name,
                'is_published' => false,
            ]);

            StoreTheme::create(array_merge(
                ['store_id' => $store->id],
                $template->theme ?? [],
            ));

            if ($template) {
                $this->applyBlueprint($store, $template);
            }

            // Every store gets a default affiliate program so the seller can
            // switch affiliates on without extra setup.
            AffiliateProgram::firstOrCreate(
                ['store_id' => $store->id, 'product_id' => null],
                [
                    'commission_type' => 'percentage',
                    'commission_value' => config('jualanyok.affiliate.default_commission_percent'),
                    'cookie_days' => config('jualanyok.affiliate.default_cookie_days'),
                    'auto_approve' => true,
                    'is_active' => false,
                ],
            );

            $user->forceFill(['is_creator' => true])->save();

            return $store->fresh(['theme', 'blocks']);
        });
    }

    /** Replaces the store's blocks with the template's blueprint. */
    public function applyBlueprint(Store $store, StorefrontTemplate $template, bool $replaceExisting = true): void
    {
        DB::transaction(function () use ($store, $template, $replaceExisting) {
            if ($replaceExisting) {
                $store->blocks()->delete();
            }

            $position = $replaceExisting ? 0 : ($store->blocks()->max('position') + 1);

            foreach ($template->blueprint as $definition) {
                Block::create([
                    'store_id' => $store->id,
                    'type' => BlockType::from($definition['type'])->value,
                    'title' => $definition['title'] ?? null,
                    'content' => $definition['content'] ?? [],
                    'draft_content' => $definition['content'] ?? [],
                    // Sanitised even though the blueprint is ours: templates are
                    // data, and data that reaches a rendered page should never
                    // be trusted just because of where it happens to live today.
                    'style' => isset($definition['style'])
                        ? BlockStyle::sanitise($definition['style'])
                        : null,
                    'position' => $position++,
                    'is_published' => true,
                ]);
            }

            if ($template->theme) {
                $store->theme()->updateOrCreate(['store_id' => $store->id], $template->theme);
            }

            $store->update(['storefront_template_id' => $template->id]);
        });
    }

    public function publish(Store $store): Store
    {
        // Publishing promotes every block's draft to live in one step, so the
        // creator never ships a half-updated page.
        DB::transaction(function () use ($store) {
            foreach ($store->blocks()->whereNotNull('draft_content')->get() as $block) {
                $block->update(['content' => $block->draft_content]);
            }

            $store->update([
                'is_published' => true,
                'published_at' => $store->published_at ?? now(),
            ]);
        });

        return $store->fresh();
    }

    public function unpublish(Store $store): Store
    {
        $store->update(['is_published' => false]);

        return $store->fresh();
    }
}
