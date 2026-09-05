<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Counting a block click.
 *
 * This endpoint is a beacon, not a page visit. It answers 204 — no body, no
 * Inertia payload — which is exactly why the storefront must reach it with
 * fetch and never through Inertia's router: the router treats a response it
 * does not recognise as a server error and shows it full screen in its own
 * modal, and an empty body makes that modal a blank white rectangle over the
 * shop.
 */
class BlockClickBeaconTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Block $block;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        $this->store = $this->makeStore();
        $this->store->forceFill(['is_published' => true])->save();

        $this->block = $this->store->blocks()->create([
            'type' => 'TEXT',
            'content' => ['body' => 'Halo'],
            'position' => 0,
        ]);
    }

    public function test_a_click_is_counted_and_nothing_is_returned(): void
    {
        $response = $this->post("/{$this->store->username}/blocks/{$this->block->id}/click");

        $response->assertNoContent();
        $this->assertSame('', $response->getContent(), 'Beacon tidak boleh mengirim body.');

        // Nothing here for a page renderer to act on, by design.
        $this->assertNull($response->headers->get('X-Inertia'));

        $this->assertSame(1, (int) $this->block->fresh()->clicks);
    }

    public function test_a_block_from_another_store_is_not_counted(): void
    {
        $other = $this->makeStore(null, ['username' => 'tokolain']);

        $this->post("/{$other->username}/blocks/{$this->block->id}/click")->assertNotFound();

        $this->assertSame(0, (int) $this->block->fresh()->clicks);
    }
}
