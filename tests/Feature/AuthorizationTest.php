<?php

namespace Tests\Feature;

use App\Enums\BalanceBucket;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Models\Block;
use App\Models\DigitalAccess;
use App\Models\Plan;
use App\Models\ProductFile;
use App\Models\Role;
use App\Payments\PaymentResult;
use App\Services\CheckoutService;
use App\Services\DigitalDeliveryService;
use App\Services\LedgerService;
use App\Services\PaymentService;
use App\Services\PlanService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_a_creator_cannot_edit_another_creators_product(): void
    {
        $mine = $this->makeStore();
        $theirs = $this->makeStore();
        $theirProduct = $this->makeProduct($theirs);

        $this->actingAs($mine->owner)
            ->put("/dashboard/produk/{$theirProduct->id}", [
                'type' => 'DIGITAL',
                'name' => 'Dibajak',
                'price' => 1,
                'status' => 'ACTIVE',
                'visibility' => 'public',
                'min_quantity' => 1,
            ])
            ->assertForbidden();

        $this->assertSame($theirProduct->name, $theirProduct->fresh()->name);
    }

    public function test_a_creator_cannot_edit_another_creators_block(): void
    {
        $mine = $this->makeStore();
        $theirs = $this->makeStore();

        $block = Block::create([
            'store_id' => $theirs->id,
            'type' => 'TEXT',
            'position' => 0,
            'content' => ['body' => 'Punya orang'],
        ]);

        $this->actingAs($mine->owner)
            ->put("/dashboard/blocks/{$block->id}", ['content' => ['body' => 'Dibajak']])
            ->assertForbidden();
    }

    public function test_reordering_cannot_touch_blocks_from_another_store(): void
    {
        $mine = $this->makeStore();
        $theirs = $this->makeStore();

        $theirBlock = Block::create([
            'store_id' => $theirs->id,
            'type' => 'TEXT',
            'position' => 7,
        ]);

        $this->actingAs($mine->owner)
            ->post('/dashboard/blocks/reorder', ['ids' => [$theirBlock->id]]);

        $this->assertSame(7, $theirBlock->fresh()->position, 'Position is untouched.');
    }

    public function test_a_creator_cannot_open_another_creators_order(): void
    {
        $mine = $this->makeStore();
        $theirs = $this->makeStore();
        $product = $this->makeProduct($theirs);

        $order = app(CheckoutService::class)->createOrder(
            $theirs,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => 'authz-1'],
        );

        $this->actingAs($mine->owner)
            ->get("/dashboard/pesanan/{$order->number}")
            ->assertForbidden();
    }

    public function test_a_customer_cannot_open_someone_elses_purchase(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => 'authz-2'],
        );

        $stranger = $this->makeUser([Role::CUSTOMER]);

        $this->actingAs($stranger)
            ->get("/member/pembelian/{$order->number}")
            ->assertForbidden();
    }

    public function test_a_non_admin_cannot_reach_the_admin_panel(): void
    {
        $creator = $this->makeStore()->owner;

        $this->actingAs($creator)->get('/admin')->assertForbidden();
        $this->actingAs($creator)->get('/admin/penarikan')->assertForbidden();
    }

    public function test_support_admin_cannot_process_withdrawals(): void
    {
        $support = $this->makeUser([Role::SUPPORT_ADMIN]);
        $creator = $this->makeStore()->owner;
        $method = $this->makeVerifiedPayoutMethod($creator);

        app(LedgerService::class)->record(
            $creator->walletOrCreate(),
            LedgerEntryType::SellerRevenue,
            BalanceBucket::Available,
            300000,
        );

        $withdrawal = app(WithdrawalService::class)->request($creator, 100000, $method);

        // Support can see the queue…
        $this->actingAs($support)->get('/admin/penarikan')->assertOk();

        // …but cannot move the money.
        $this->actingAs($support)
            ->post("/admin/penarikan/{$withdrawal->number}/setujui")
            ->assertForbidden();
    }

    public function test_only_a_super_admin_can_impersonate(): void
    {
        $support = $this->makeUser([Role::SUPPORT_ADMIN]);
        $target = $this->makeUser([Role::CUSTOMER]);

        $this->actingAs($support)
            ->post("/admin/pengguna/{$target->id}/impersonate")
            ->assertForbidden();

        $super = $this->makeUser([Role::SUPER_ADMIN]);

        $this->actingAs($super)
            ->post("/admin/pengguna/{$target->id}/impersonate")
            ->assertRedirect();

        $this->assertAuthenticatedAs($target);
    }

    public function test_a_download_link_must_be_signed(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('demo/file.pdf', 'isi file');

        $access = $this->grantAccess();

        // Unsigned request is refused by the `signed` middleware.
        $this->get("/downloads/{$access->token}")->assertStatus(403);

        $url = app(DigitalDeliveryService::class)->signedUrl($access);
        $this->get($url)->assertOk();

        $this->assertSame(1, $access->fresh()->download_count);
    }

    public function test_a_revoked_access_cannot_be_downloaded_even_with_a_valid_signature(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('demo/file.pdf', 'isi file');

        $access = $this->grantAccess();
        $url = app(DigitalDeliveryService::class)->signedUrl($access);

        $access->update(['is_revoked' => true]);

        $this->get($url)->assertStatus(403);
    }

    public function test_download_limit_is_enforced(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('demo/file.pdf', 'isi file');

        $access = $this->grantAccess();
        $access->update(['download_limit' => 1]);

        $this->get(app(DigitalDeliveryService::class)->signedUrl($access))->assertOk();
        $this->get(app(DigitalDeliveryService::class)->signedUrl($access->fresh()))->assertStatus(403);
    }

    public function test_plan_product_limit_is_enforced(): void
    {
        $store = $this->makeStore();
        $plans = app(PlanService::class);

        $limit = $plans->limit($store->owner, PlanService::PRODUCTS_LIMIT);
        $this->assertSame(10, $limit, 'Free plan allows ten products.');

        for ($i = 0; $i < $limit; $i++) {
            $this->makeProduct($store, ['name' => "Produk {$i}"]);
        }

        $this->expectException(ValidationException::class);
        $plans->ensureWithinLimit($store->owner, PlanService::PRODUCTS_LIMIT, $limit, 'produk');
    }

    public function test_upgrading_a_plan_unlocks_gated_features(): void
    {
        $store = $this->makeStore();
        $plans = app(PlanService::class);

        $this->assertFalse($plans->allows($store->owner, PlanService::CUSTOM_DOMAIN));

        $plans->subscribe($store->owner, Plan::where('slug', Plan::PRO)->firstOrFail());

        $this->assertTrue($plans->allows($store->owner->fresh(), PlanService::CUSTOM_DOMAIN));
        $this->assertNull($plans->limit($store->owner->fresh(), PlanService::PRODUCTS_LIMIT), 'Pro is unlimited.');
    }

    public function test_webhook_creation_is_blocked_on_the_free_plan(): void
    {
        $store = $this->makeStore();

        $this->actingAs($store->owner)
            ->post('/dashboard/integrasi/webhooks', [
                'url' => 'https://example.test/hook',
                'events' => ['order.paid'],
            ])
            ->assertSessionHasErrors('plan');
    }

    private function grantAccess(): DigitalAccess
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);

        $file = ProductFile::create([
            'product_id' => $product->id,
            'name' => 'file.pdf',
            'disk' => 'local',
            'path' => 'demo/file.pdf',
        ]);

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => 'dl-'.uniqid()],
        );

        $payments = app(PaymentService::class);
        $payment = $payments->createPayment($order, 'mock', 'qris', 'qris');

        $payments->applyResult(new PaymentResult(
            status: PaymentStatus::Paid,
            reference: $payment->reference,
            amount: (float) $payment->amount,
            paidAt: now(),
            eventId: 'evt-dl-'.uniqid(),
        ), 'mock');

        return DigitalAccess::where('product_file_id', $file->id)->firstOrFail();
    }
}
