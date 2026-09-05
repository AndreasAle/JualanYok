<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Support\Tours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The in-app guided tours.
 *
 * Two things matter here. A tour has to stop appearing once it has been read,
 * or the product looks like it has forgotten you. And progress is written into
 * the user's stored profile, so the tour id has to be checked against the
 * server's own registry rather than trusted from the request.
 */
class GuidedTourTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->store = $this->makeStore();
    }

    public function test_a_new_creator_is_offered_the_dashboard_tour(): void
    {
        $this->actingAs($this->store->owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tour.id', 'creator-dashboard')
                ->where('tour.seen', false)
                ->has('tour.steps', 7));
    }

    public function test_every_step_carries_copy_a_creator_can_read(): void
    {
        foreach (Tours::all() as $id => $tour) {
            $this->assertNotEmpty($tour['title'], "{$id} tanpa judul");

            foreach (Tours::payload($id)['steps'] as $i => $step) {
                $this->assertNotEmpty(trim($step['title']), "{$id} langkah {$i} tanpa judul");
                $this->assertNotEmpty(trim($step['body']), "{$id} langkah {$i} tanpa penjelasan");
                $this->assertContains($step['placement'], ['top', 'bottom', 'left', 'right']);
            }
        }
    }

    public function test_every_tour_points_at_a_route_that_exists(): void
    {
        foreach (Tours::all() as $id => $tour) {
            $this->assertTrue(
                app('router')->has($tour['route']),
                "Tour {$id} menunjuk route yang tidak ada: {$tour['route']}",
            );
        }
    }

    public function test_a_finished_tour_is_still_shared_but_no_longer_opens_itself(): void
    {
        $creator = $this->store->owner;

        $this->actingAs($creator)
            ->post('/panduan/creator-dashboard', ['outcome' => 'completed', 'step' => 6])
            ->assertRedirect();

        $this->actingAs($creator)
            ->get('/dashboard')
            ->assertOk()
            // Still sent, so the help button can replay it without a round trip.
            ->assertInertia(fn ($page) => $page->where('tour.seen', true));
    }

    public function test_skipping_is_recorded_as_a_different_outcome_than_finishing(): void
    {
        $creator = $this->store->owner;

        $this->actingAs($creator)
            ->post('/panduan/creator-dashboard', ['outcome' => 'skipped', 'step' => 1])
            ->assertRedirect();

        $state = $creator->fresh()->profile->onboarding_state;

        $this->assertSame('skipped', $state['tours']['creator-dashboard']['outcome']);
        $this->assertSame(1, $state['tours']['creator-dashboard']['step']);
    }

    public function test_replaying_makes_the_tour_open_on_its_own_again(): void
    {
        $creator = $this->store->owner;

        $this->actingAs($creator)->post('/panduan/creator-dashboard', ['outcome' => 'completed']);
        $this->actingAs($creator)->post('/panduan/creator-dashboard/ulangi')->assertRedirect();

        $this->actingAs($creator)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tour.seen', false));
    }

    public function test_an_unknown_tour_id_cannot_write_into_the_stored_profile(): void
    {
        $creator = $this->store->owner;

        $this->actingAs($creator)
            ->post('/panduan/../../etc', ['outcome' => 'completed'])
            ->assertNotFound();

        $this->actingAs($creator)
            ->post('/panduan/dibuat-sendiri', ['outcome' => 'completed'])
            ->assertNotFound();

        $this->assertArrayNotHasKey(
            'tours',
            $creator->fresh()->profile?->onboarding_state ?? [],
        );
    }

    public function test_an_outcome_the_registry_does_not_define_is_refused(): void
    {
        $this->actingAs($this->store->owner)
            ->post('/panduan/creator-dashboard', ['outcome' => 'diretas'])
            ->assertSessionHasErrors('outcome');
    }

    public function test_a_guest_is_not_shown_a_tour(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->where('tour', null));
    }

    public function test_one_creator_cannot_end_another_creators_tour(): void
    {
        $other = $this->makeStore(null, ['username' => 'tokolain'])->owner;

        $this->actingAs($this->store->owner)
            ->post('/panduan/creator-dashboard', ['outcome' => 'completed']);

        // Progress is keyed off the signed-in user, never off anything sent.
        $this->assertFalse(Tours::seen($other->fresh(), 'creator-dashboard'));
    }
}
