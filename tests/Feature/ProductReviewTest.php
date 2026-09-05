<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Product reviews.
 *
 * A star rating is only information if it cost something to leave. Everything
 * here defends that: a review hangs off the order line it came from, so it can
 * only be written by whoever paid for that line, exactly once, and a shop can
 * neither review itself nor delete what someone said about it.
 */
class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Product $product;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        Storage::fake('public');

        $this->store = $this->makeStore();
        $this->store->forceFill(['is_published' => true])->save();
        $this->product = $this->makeProduct($this->store, ['name' => 'Piyama Busui']);
        $this->buyer = $this->makeUser();
    }

    private function order(OrderStatus $status = OrderStatus::Completed): Order
    {
        $order = Order::create([
            'number' => 'JY-'.uniqid(),
            'store_id' => $this->store->id,
            'customer_email' => $this->buyer->email,
            'customer_name' => $this->buyer->name,
            'status' => $status,
            'subtotal' => 110000,
            'grand_total' => 110000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_type' => $this->product->type->value,
            'name' => $this->product->name,
            'variant_name' => 'Bunga, All Size',
            'quantity' => 1,
            'unit_price' => 110000,
            'total' => 110000,
        ]);

        return $order->fresh('items');
    }

    private function write(Order $order, array $payload = [], array $files = [])
    {
        return $this->actingAs($this->buyer)->post("/member/pembelian/{$order->number}/ulasan", array_merge([
            'order_item_id' => $order->items->first()->id,
            'rating' => 5,
            'body' => 'Bahannya adem, ukurannya pas.',
        ], $payload, $files === [] ? [] : ['media' => $files]));
    }

    public function test_a_buyer_can_review_what_they_bought(): void
    {
        $order = $this->order();

        $this->write($order)->assertRedirect();

        $review = Review::firstOrFail();
        $this->assertSame(5, $review->rating);
        $this->assertSame($this->product->id, $review->product_id);
        $this->assertSame($this->buyer->id, $review->user_id);
    }

    public function test_photos_and_videos_are_stored_with_the_review(): void
    {
        $order = $this->order();

        $this->write($order, [], [
            UploadedFile::fake()->image('bukti.jpg'),
            UploadedFile::fake()->create('unboxing.mp4', 200, 'video/mp4'),
        ])->assertRedirect();

        $media = Review::firstOrFail()->media;

        $this->assertCount(2, $media);
        $this->assertSame(['image', 'video'], $media->pluck('kind')->all());

        foreach ($media as $item) {
            Storage::disk('public')->assertExists($item->path);
            // Never under the uploader's own filename.
            $this->assertStringNotContainsString('bukti.jpg', $item->path);
        }
    }

    public function test_a_disguised_file_is_refused(): void
    {
        $order = $this->order();

        // Named like an image, actually something else. Checked by real type.
        $this->write($order, [], [UploadedFile::fake()->create('payload.jpg', 10, 'application/x-httpd-php')])
            ->assertSessionHasErrors('media.0');

        $this->assertSame(0, Review::count());
    }

    public function test_nobody_can_review_a_product_they_did_not_buy(): void
    {
        $order = $this->order();
        $stranger = $this->makeUser();

        $this->actingAs($stranger)
            ->post("/member/pembelian/{$order->number}/ulasan", [
                'order_item_id' => $order->items->first()->id,
                'rating' => 1,
                'body' => 'Jelek banget',
            ])
            ->assertNotFound();

        $this->assertSame(0, Review::count());
    }

    public function test_an_unpaid_order_earns_no_review(): void
    {
        $order = $this->order(OrderStatus::PendingPayment);

        $this->write($order)->assertSessionHasErrors('rating');

        $this->assertSame(0, Review::count());
    }

    public function test_one_purchase_earns_exactly_one_review(): void
    {
        $order = $this->order();

        $this->write($order)->assertRedirect();
        $this->write($order)->assertSessionHasErrors('rating');

        $this->assertSame(1, Review::count());
    }

    public function test_the_products_average_is_recomputed_not_incremented(): void
    {
        $this->write($this->order(), ['rating' => 5]);
        $this->write($this->order(), ['rating' => 2]);

        $this->assertSame(2, (int) $this->product->fresh()->rating_count);
        $this->assertEqualsWithDelta(3.5, (float) $this->product->fresh()->rating_avg, 0.01);

        // Hiding one moves the average, which an incremental counter would miss.
        Review::latest('id')->first()->forceFill(['status' => Review::HIDDEN])->save();
        app(\App\Services\ReviewService::class)->recount($this->product->id);

        $this->assertSame(1, (int) $this->product->fresh()->rating_count);
        $this->assertEqualsWithDelta(5.0, (float) $this->product->fresh()->rating_avg, 0.01);
    }

    public function test_the_product_page_shows_the_summary_and_the_reviews(): void
    {
        $this->write($this->order(), ['rating' => 5]);
        $this->write($this->order(), ['rating' => 4]);

        $this->get("/{$this->store->username}/p/{$this->product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('reviewSummary.total', 2)
                // Halves are the whole point of showing one decimal.
                ->where('reviewSummary.average', 4.5)
                ->where('reviewSummary.breakdown.5', 1)
                ->has('reviews.data', 2));
    }

    public function test_reviews_can_be_filtered_the_way_buyers_read_them(): void
    {
        $this->write($this->order(), ['rating' => 5]);
        $this->write($this->order(), ['rating' => 1, 'body' => 'Warnanya beda']);

        $this->get("/{$this->store->username}/p/{$this->product->slug}?ulasan=1")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('reviews.data', 1)->where('reviews.data.0.rating', 1));
    }

    public function test_a_hidden_review_is_not_public(): void
    {
        $this->write($this->order());
        Review::firstOrFail()->forceFill(['status' => Review::HIDDEN])->save();

        $this->get("/{$this->store->username}/p/{$this->product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('reviews.data', 0));
    }

    public function test_an_anonymous_review_hides_the_buyers_identity(): void
    {
        $this->write($this->order(), ['is_anonymous' => true]);

        $this->get("/{$this->store->username}/p/{$this->product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('reviews.data.0.name', 'Pembeli')
                ->where('reviews.data.0.avatar_url', null));
    }

    public function test_a_named_review_shows_only_a_shortened_name(): void
    {
        $this->buyer->forceFill(['name' => 'Andreas Alessandro'])->save();
        $this->write($this->order());

        // A full name beside a public purchase history is more than a buyer
        // signed up for.
        $this->get("/{$this->store->username}/p/{$this->product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('reviews.data.0.name', 'Andreas A.'));
    }

    public function test_the_seller_may_reply_and_nothing_more(): void
    {
        $this->write($this->order());
        $review = Review::firstOrFail();

        $this->actingAs($this->store->owner)
            ->post("/dashboard/ulasan/{$review->id}/balas", ['body' => 'Makasih kak!'])
            ->assertRedirect();

        $this->assertSame('Makasih kak!', $review->fresh()->seller_reply);

        // There is no route that lets a shop remove criticism of itself.
        $this->assertFalse(app('router')->has('creator.reviews.destroy'));
    }

    public function test_one_seller_cannot_reply_to_another_shops_review(): void
    {
        $this->write($this->order());
        $review = Review::firstOrFail();

        $outsider = $this->makeStore(null, ['username' => 'tokolain'])->owner;

        $this->actingAs($outsider)
            ->post("/dashboard/ulasan/{$review->id}/balas", ['body' => 'Menyusup'])
            ->assertNotFound();

        $this->assertNull($review->fresh()->seller_reply);
    }

    public function test_the_rating_travels_with_every_product_tile(): void
    {
        $this->write($this->order(), ['rating' => 4]);

        // A rating only visible on the detail page does no work: the choice
        // between products is made on the tiles.
        $this->get("/{$this->store->username}")
            ->assertOk()
            ->assertInertia(function ($page) {
                $products = collect($page->toArray()['props']['blocks'] ?? [])
                    ->pluck('content.products')
                    ->filter()
                    ->flatten(1);

                $this->assertTrue(
                    $products->isEmpty() || $products->every(fn ($p) => array_key_exists('rating_avg', $p)),
                    'Kartu produk harus membawa rating.',
                );

                return true;
            });
    }

    public function test_the_seller_sees_unanswered_reviews_first(): void
    {
        $answered = $this->order();
        $this->write($answered, ['rating' => 5]);
        Review::firstOrFail()->forceFill(['seller_reply' => 'Makasih!'])->save();

        $this->write($this->order(), ['rating' => 2, 'body' => 'Ukurannya beda']);

        $props = $this->actingAs($this->store->owner)
            ->get('/dashboard/ulasan')
            ->assertOk()
            ->viewData('page')['props'];

        // The one that costs the shop money is the one on top.
        $this->assertSame(2, $props['reviews']['data'][0]['rating']);
        $this->assertSame(1, $props['stats']['unanswered']);
        $this->assertSame(1, $props['stats']['low']);
    }

    public function test_one_seller_never_sees_another_shops_reviews(): void
    {
        $this->write($this->order());

        $outsider = $this->makeStore(null, ['username' => 'tokolain'])->owner;

        $this->actingAs($outsider)
            ->get('/dashboard/ulasan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('reviews.data', 0)->where('stats.total', 0));
    }

    public function test_the_order_page_offers_a_form_only_while_one_is_owed(): void
    {
        $order = $this->order();

        $this->actingAs($this->buyer)
            ->get("/member/pembelian/{$order->number}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('reviewable', 1)->has('myReviews', 0));

        $this->write($order);

        $this->actingAs($this->buyer)
            ->get("/member/pembelian/{$order->number}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('reviewable', 0)->has('myReviews', 1));
    }
}
