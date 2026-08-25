<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\DigitalAccess;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Notifications\OrderReceipt;
use App\Notifications\ProductFileUpdated;
use App\Payments\PaymentResult;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Delivery to a guest buyer.
 *
 * Most people who buy a digital product never make an account — they type an
 * email and pay. Everything here exists to guarantee the file actually reaches
 * that person, without a login and without the seller lifting a finger.
 */
class GuestDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        Storage::fake('local');
        $this->store = $this->makeStore();
    }

    /** A guest purchase, paid for, exactly as Luna would make it. */
    private function paidGuestOrder(string $key = 'luna-1'): Order
    {
        $product = $this->makeProduct($this->store, ['type' => 'DIGITAL', 'price' => 50000]);

        $order = app(CheckoutService::class)->createOrder(
            $this->store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Luna Sari', 'email' => 'luna@example.test'],
            ['idempotency_key' => $key],
        );

        $payments = app(PaymentService::class);
        $payment = $payments->createPayment($order, 'mock', 'qris', 'qris');

        $payments->applyResult(new PaymentResult(
            status: PaymentStatus::Paid,
            reference: $payment->reference,
            amount: (float) $payment->amount,
            paidAt: now(),
            eventId: $key.'-evt',
        ), 'mock');

        // makeProduct records the file row; put real bytes behind it so a
        // download actually streams.
        foreach ($product->files as $file) {
            Storage::disk('local')->put($file->path, 'isi file '.$file->name);
        }

        return $order->fresh();
    }

    public function test_every_order_gets_a_permanent_delivery_token(): void
    {
        $order = $this->paidGuestOrder();

        $this->assertNotNull($order->access_token);
        $this->assertSame(48, strlen($order->access_token));
    }

    public function test_a_guest_can_open_their_order_and_download_without_logging_in(): void
    {
        $order = $this->paidGuestOrder();
        $access = DigitalAccess::where('order_id', $order->id)->firstOrFail();

        $this->assertGuest();

        $this->get("/pesanan/{$order->access_token}")
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('order.number', $order->number)
                    ->where('order.is_paid', true)
                    ->has('downloads', 1)
                    ->where('downloads.0.available', true),
            );

        $this->get("/pesanan/{$order->access_token}/unduh/{$access->id}")
            ->assertOk()
            ->assertDownload();
    }

    public function test_the_receipt_points_at_the_delivery_page_not_a_login_wall(): void
    {
        Notification::fake();

        $order = $this->paidGuestOrder();

        Notification::assertSentOnDemand(OrderReceipt::class, function (OrderReceipt $notification) use ($order) {
            $mail = $notification->toMail($order);

            $this->assertSame($order->fresh()->deliveryUrl(), $mail->actionUrl);
            $this->assertStringContainsString('/pesanan/', $mail->actionUrl);
            $this->assertStringNotContainsString('/member/', $mail->actionUrl);

            return true;
        });
    }

    public function test_the_link_keeps_working_after_the_first_download(): void
    {
        $order = $this->paidGuestOrder();
        $access = DigitalAccess::where('order_id', $order->id)->firstOrFail();

        // The whole promise of a permanent link: come back months later.
        $this->get("/pesanan/{$order->access_token}/unduh/{$access->id}")->assertOk();
        $this->get("/pesanan/{$order->access_token}/unduh/{$access->id}")->assertOk();

        $this->assertSame(2, $access->fresh()->download_count);
        $this->get("/pesanan/{$order->access_token}")->assertOk();
    }

    public function test_the_download_quota_is_enforced(): void
    {
        $order = $this->paidGuestOrder();
        $access = DigitalAccess::where('order_id', $order->id)->firstOrFail();

        $access->update(['download_limit' => 2]);

        $this->get("/pesanan/{$order->access_token}/unduh/{$access->id}")->assertOk();
        $this->get("/pesanan/{$order->access_token}/unduh/{$access->id}")->assertOk();
        $this->get("/pesanan/{$order->access_token}/unduh/{$access->id}")->assertForbidden();

        // The page explains why rather than just hiding the button.
        $this->get("/pesanan/{$order->access_token}")
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('downloads.0.available', false)
                    ->where('downloads.0.blocked_reason', 'Kuota unduh habis.'),
            );
    }

    public function test_a_wrong_token_looks_the_same_as_one_that_never_existed(): void
    {
        $this->paidGuestOrder();

        $this->get('/pesanan/'.str_repeat('a', 48))->assertNotFound();
        $this->get('/pesanan/tebakan-pendek')->assertNotFound();
    }

    public function test_one_token_cannot_reach_another_buyers_files(): void
    {
        $mine = $this->paidGuestOrder('luna-1');
        $theirs = $this->paidGuestOrder('budi-1');

        $victimAccess = DigitalAccess::where('order_id', $theirs->id)->firstOrFail();

        $this->get("/pesanan/{$mine->access_token}/unduh/{$victimAccess->id}")
            ->assertForbidden();

        $this->assertSame(0, $victimAccess->fresh()->download_count);
    }

    public function test_an_unpaid_order_shows_no_files(): void
    {
        $product = $this->makeProduct($this->store, ['type' => 'DIGITAL', 'price' => 50000]);

        $order = app(CheckoutService::class)->createOrder(
            $this->store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Luna Sari', 'email' => 'luna@example.test'],
            ['idempotency_key' => 'unpaid-1'],
        );

        $this->get("/pesanan/{$order->access_token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('order.is_paid', false)->has('downloads', 0));
    }

    public function test_a_signed_in_buyer_can_claim_a_guest_purchase(): void
    {
        $order = $this->paidGuestOrder();
        $user = $this->makeUser([Role::CUSTOMER], ['email' => 'luna@example.test']);

        $this->assertNull($order->user_id);

        $this->actingAs($user)
            ->post("/pesanan/{$order->access_token}/simpan")
            ->assertRedirect();

        $this->assertSame($user->id, $order->fresh()->user_id);

        // Claiming is additive: the original link still works.
        $this->post('/logout');
        $this->get("/pesanan/{$order->access_token}")->assertOk();
    }

    public function test_claiming_twice_is_harmless(): void
    {
        $order = $this->paidGuestOrder();
        $user = $this->makeUser([Role::CUSTOMER], ['email' => 'luna@example.test']);

        $this->actingAs($user)->post("/pesanan/{$order->access_token}/simpan");
        $this->actingAs($user)->post("/pesanan/{$order->access_token}/simpan")->assertRedirect();

        $this->assertSame($user->id, $order->fresh()->user_id);
    }

    public function test_a_stranger_cannot_claim_someone_elses_order(): void
    {
        $order = $this->paidGuestOrder();
        $owner = $this->makeUser([Role::CUSTOMER], ['email' => 'luna@example.test']);
        $stranger = $this->makeUser([Role::CUSTOMER]);

        $this->actingAs($owner)->post("/pesanan/{$order->access_token}/simpan");

        $this->actingAs($stranger)
            ->post("/pesanan/{$order->access_token}/simpan")
            ->assertRedirect();

        $this->assertSame(
            $owner->id,
            $order->fresh()->user_id,
            'A claimed order must not change hands.',
        );
    }

    public function test_past_buyers_are_told_when_the_seller_ships_a_new_version(): void
    {
        Notification::fake();

        $order = $this->paidGuestOrder();
        $access = DigitalAccess::where('order_id', $order->id)->firstOrFail();
        $product = $order->items->first()->product;

        $this->actingAs($this->store->owner)
            ->post("/dashboard/produk/{$product->id}/files/{$access->product_file_id}/replace", [
                'file' => \Illuminate\Http\UploadedFile::fake()->create('edisi-2.pdf', 60, 'application/pdf'),
            ])
            ->assertRedirect();

        // This is what a chat-based handover cannot do: reach people who already bought.
        Notification::assertSentOnDemand(
            ProductFileUpdated::class,
            function (ProductFileUpdated $notification) use ($order) {
                $mail = $notification->toMail($order);

                $this->assertStringContainsString('Versi baru', $mail->subject);
                $this->assertSame($order->fresh()->deliveryUrl(), $mail->actionUrl);

                return true;
            },
        );
    }

    public function test_the_updated_file_is_what_the_original_link_now_serves(): void
    {
        $order = $this->paidGuestOrder();
        $access = DigitalAccess::where('order_id', $order->id)->firstOrFail();
        $product = $order->items->first()->product;

        $this->actingAs($this->store->owner)
            ->post("/dashboard/produk/{$product->id}/files/{$access->product_file_id}/replace", [
                'file' => \Illuminate\Http\UploadedFile::fake()->create('edisi-2.pdf', 60, 'application/pdf'),
            ]);

        $this->post('/logout');

        // Same link the buyer was given at purchase, now serving the new edition.
        $this->get("/pesanan/{$order->access_token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('downloads.0.version', '1.1'));

        $this->get("/pesanan/{$order->access_token}/unduh/{$access->id}")->assertOk();
    }

    public function test_the_delivery_page_is_kept_out_of_search_engines(): void
    {
        $order = $this->paidGuestOrder();

        // The URL is the credential; an indexed copy would hand it to everyone.
        // Asserted on the header, not a meta tag: the tag is client-rendered and
        // a crawler without JavaScript would never see it.
        $this->get("/pesanan/{$order->access_token}")
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
