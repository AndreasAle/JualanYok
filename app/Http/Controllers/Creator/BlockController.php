<?php

namespace App\Http\Controllers\Creator;

use App\Enums\BlockType;
use App\Support\BlockStyle;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BlockController extends Controller
{
    public function __construct(private readonly PlanService $plans) {}

    public function store(Request $request)
    {
        $store = $request->user()->store;

        $this->plans->ensureWithinLimit(
            $request->user(),
            PlanService::BLOCKS_LIMIT,
            $store->blocks()->count(),
            'block',
        );

        $data = $request->validate([
            'type' => ['required', Rule::enum(BlockType::class)],
            'title' => ['nullable', 'string', 'max:160'],
            'content' => ['nullable', 'array'],
        ]);

        $block = $store->blocks()->create([
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'content' => $data['content'] ?? [],
            'draft_content' => $data['content'] ?? [],
            'position' => ($store->blocks()->max('position') ?? -1) + 1,
            'is_published' => true,
        ]);

        return back()->with('success', $block->type->label().' ditambahkan.');
    }

    public function update(Request $request, Block $block)
    {
        $this->authorizeBlock($request, $block);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'content' => ['nullable', 'array'],
            'style' => ['nullable', 'array'],
            'is_published' => ['boolean'],
            'visible_mobile' => ['boolean'],
            'visible_desktop' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'animation' => ['nullable', 'string', 'max:40'],
            'publish_now' => ['boolean'],
        ]);

        // Keep a version snapshot before overwriting, so an accidental edit in
        // the builder is recoverable.
        $block->versions()->create([
            'user_id' => $request->user()->id,
            'snapshot' => $block->only(['title', 'content', 'draft_content', 'style']),
        ]);

        $block->fill(collect($data)->except(['content', 'publish_now', 'style'])->all());

        /*
         * Style is a fixed vocabulary, not free CSS. It used to be an open array
         * rendered straight into an inline style attribute, which let a creator
         * write anything — a fixed-position overlay covering their own checkout
         * button, for instance, that still looked fine in the preview.
         */
        if (array_key_exists('style', $data)) {
            $block->style = BlockStyle::sanitise($data['style']);
        }

        if (array_key_exists('content', $data)) {
            $block->draft_content = $data['content'];

            // The builder autosaves into draft; a live store keeps showing the
            // published version until the creator hits publish.
            if (($data['publish_now'] ?? false) || ! $block->store->is_published) {
                $block->content = $data['content'];
            }
        }

        $block->save();

        return back()->with('success', 'Tersimpan.');
    }

    public function duplicate(Request $request, Block $block)
    {
        $this->authorizeBlock($request, $block);

        $this->plans->ensureWithinLimit(
            $request->user(),
            PlanService::BLOCKS_LIMIT,
            $block->store->blocks()->count(),
            'block',
        );

        DB::transaction(function () use ($block) {
            // Push everything below down one slot so the copy sits directly
            // beneath the original.
            $block->store->blocks()
                ->where('position', '>', $block->position)
                ->increment('position');

            $copy = $block->replicate(['impressions', 'clicks']);
            $copy->position = $block->position + 1;
            $copy->title = $block->title ? $block->title.' (salinan)' : null;
            $copy->impressions = 0;
            $copy->clicks = 0;
            $copy->save();
        });

        return back()->with('success', 'Block diduplikasi.');
    }

    public function destroy(Request $request, Block $block)
    {
        $this->authorizeBlock($request, $block);

        $block->delete();

        return back()->with('success', 'Block dihapus.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $storeId = $request->user()->store->id;

        DB::transaction(function () use ($data, $storeId) {
            foreach ($data['ids'] as $position => $id) {
                Block::where('id', $id)
                    ->where('store_id', $storeId)   // scoped: cannot reorder another store
                    ->update(['position' => $position]);
            }
        });

        return back();
    }

    private function authorizeBlock(Request $request, Block $block): void
    {
        abort_unless($block->store_id === $request->user()->store->id, 403);
    }
}
