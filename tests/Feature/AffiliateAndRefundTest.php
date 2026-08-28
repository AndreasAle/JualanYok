<?php

namespace Tests\Feature;

use App\Enums\CommissionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AffiliateApplication;
use App\Models\Commission;
use App\Models\DigitalAccess;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Payments\PaymentResult;
use App\Services\AffiliateService;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AffiliateAndRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    /** @return array{0: Store, 1: User, 2: Product, 3: string} */
    private function scenario(): array
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['price' => 200000, 'affiliate_enabled' => true]);
        $program = $this->makeAffiliateProgram($store, 20);

        $affiliate = $this->makeUser([Role::AFFILIATE]);

        AffiliateApplication::create([
            'affiliate_program_id' => $program->id,
            'user_id' => $affiliate->id,
            'status' => 'APPROVED',
            'reviewed_at' => now(),
        ]);

        $link = app(AffiliateService::class)->linkFor($program, $affiliate->id, $product);

        return [$store, $affiliate, $product, $link->code];
    }

    private function payFor($order): void
    {
        $payments = app(PaymentService::class);
        $payment = $payments->createPayment($order, 'mock', 'qris', 'qris');

        $payments->applyResult(new PaymentResult(
            status: PaymentStatus::Paid,
            reference: $payment->reference,
            amount: (float) $payment->amount,
            paidAt: now(),
            eventId: 'evt-'.$order->id,
        ), 'mock');
    }

    public function test_commission_accrues_to_the_affiliate_pending_balance(): void
    {
        [$store, $affiliate, $product, $code] = $this->scenario();

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => 'aff-1', 'affiliate_code' => $code],
        );

        $this->assertEquals(40000, (float) $order->affiliate_commission, '20% of 200.000');

        $this->payFor($order);

        $commission = Commission::where('user_id', $affiliate->id)->firstOrFail();

        $this->assertSame(CommissionStatus::Pending, $commission->status);
        $this->assertEquals(40000, (float) $commission->amount);
        $this->assertEquals(40000, (float) $affiliate->walletOrCreate()->pending_balance);

        // The seller's net is reduced by both the platform fee and the commission.
        $sellerWallet = $store->owner->walletOrCreate();
        $order->refresh();
        $this->assertEquals(
            (float) $order->seller_net - (float) $order->reserve_amount,
            (float) $sellerWallet->pending_balance,
        );
        $this->assertGreaterThan(0, (float) $sellerWallet->reserve_balance);
    }

    public function test_self_purchase_through_own_link_earns_nothing(): void
    {
        [$store, $affiliate, $product, $code] = $this->scenario();

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => $affiliate->name, 'email' => $affiliate->email],
            ['idempotency_key' => 'aff-self', 'affiliate_code' => $code],
        );

        $this->assertEquals(0, (float) $order->affiliate_commission);
        $this->assertNull($order->affiliate_user_id);
    }

    public function test_store_owner_cannot_earn_commission_on_their_own_store(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['price' => 100000, 'affiliate_enabled' => true]);
        $program = $this->makeAffiliateProgram($store);

        $link = app(AffiliateService::class)->linkFor($program, $store->user_id, $product);

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => 'aff-own', 'affiliate_code' => $link->code],
        );

        $this->assertEquals(0, (float) $order->affiliate_commission);
    }

    public function test_products_with_affiliate_disabled_pay_no_commission(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['price' => 100000, 'affiliate_enabled' => false]);
        $program = $this->makeAffiliateProgram($store);
        $affiliate = $this->makeUser([Role::AFFILIATE]);

        $link = app(AffiliateService::class)->linkFor($program, $affiliate->id, $product);

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => 'aff-off', 'affiliate_code' => $link->code],
        );

        $this->assertEquals(0, (float) $order->affiliate_commission);
    }

    public function test_matured_commissions_move_to_the_available_bucket(): void
    {
        [$store, $affiliate, $product, $code] = $this->scenario();

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => 'aff-mature', 'affiliate_code' => $code],
        );

        $this->payFor($order);

        Commission::query()->update(['available_at' => now()->subDay()]);

        $released = app(AffiliateService::class)->releaseMatured();

        $wallet = $affiliate->walletOrCreate()->fresh();

        $this->assertSame(1, $released);
        $this->assertEquals(0, (float) $wallet->pending_balance);
        $this->assertEquals(40000, (float) $wallet->available_balance);
        $this->assertSame(CommissionStatus::Approved, Commission::first()->status);
    }

    public function test_a_full_refund_claws_back_the_seller_and_reverses_the_commission(): void
    {
        [$store, $affiliate, $product, $code] = $this->scenario();

        ProductFile::create([
            'product_id' => $product->id,
            'name' => 'file.pdf',
            'disk' => 'local',
            'path' => 'demo/file.pdf',
        ]);

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => 'refund-1', 'affiliate_code' => $code],
        );

        $this->payFor($order);
        $order->refresh();

        $admin = $this->makeUser([Role::FINANCE_ADMIN]);
        $refunds = app(RefundService::class);

        $refund = $refunds->request($order, (float) $order->grand_total, 'Produk tidak sesuai.');
        $refunds->approve($refund, $admin);

        $order->refresh();

        $this->assertSame(OrderStatus::Refunded, $order->status);
        $this->assertEquals(0, (float) $store->owner->walletOrCreate()->fresh()->pending_balance);
        $this->assertEquals(0, (float) $affiliate->walletOrCreate()->fresh()->pending_balance);
        $this->assertSame(CommissionStatus::Reversed, Commission::first()->status);

        // Full refund revokes the delivered file.
        $this->assertTrue(DigitalAccess::where('order_id', $order->id)->first()->is_revoked);
    }

    public function test_a_partial_refund_only_claws_back_its_share(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['price' => 200000]);

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => 'refund-partial'],
        );

        $this->payFor($order);
        $order->refresh();

        $sellerNet = (float) $order->seller_net;
        $admin = $this->makeUser([Role::FINANCE_ADMIN]);

        $refunds = app(RefundService::class);
        $refund = $refunds->request($order, 100000, 'Sebagian rusak.');
        $refunds->approve($refund, $admin);

        $order->refresh();

        $this->assertSame(OrderStatus::PartiallyRefunded, $order->status);
        $this->assertEquals(100000, (float) $order->refunded_total);
        $this->assertEquals(
            round($sellerNet / 2, 2),
            (float) $store->owner->walletOrCreate()->fresh()->pending_balance,
        );
    }

    public function test_refund_cannot_exceed_the_order_total(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['price' => 100000]);

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => 'refund-over'],
        );

        $this->payFor($order);

        $this->expectException(ValidationException::class);
        app(RefundService::class)->request($order->fresh(), 999999, 'Kebanyakan.');
    }
}
