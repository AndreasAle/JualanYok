<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Store;
use App\Services\CheckoutService;
use App\Services\LedgerService;
use App\Services\PaymentService;
use App\Support\Qris;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Paying for a product with QRIS, and where that money ends up.
 *
 * The wallet sends no callback, so the rupiah figure is the only link between a
 * transfer and an order. These tests pin that the figure cannot be ambiguous,
 * that the buyer is charged exactly what the page promised, and that a manual
 * confirmation moves money the same way a gateway callback would.
 */
class QrisCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_STATIC = '00020101021126620013ID.CONTOH.WWW011800000000000000000002120000000000000303UMI51440014ID.CO.QRIS.WWW0215ID00000000000000303UMI5204737253033605802ID5911Toko Contoh6007Jakarta6105123456304F4A5';

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        config([
            'payments.qris.static_payload' => self::TEST_STATIC,
            'payments.qris.window_minutes' => 30,
            'payments.qris.fee_percent' => 0.7,
            'payments.qris.fee_fixed' => 0,
            'payments.providers.qris.enabled' => true,
        ]);

        $this->store = $this->makeStore();
    }

    private function order(float $price = 100000, string $key = 'qris-1'): Order
    {
        $product = $this->makeProduct($this->store, ['price' => $price]);

        return app(CheckoutService::class)->createOrder(
            $this->store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => $key],
        );
    }

    private function payments(): PaymentService
    {
        return app(PaymentService::class);
    }

    public function test_the_qr_locks_in_the_exact_amount_the_buyer_must_send(): void
    {
        $payment = $this->payments()->createPayment($this->order(), 'qris', 'qris', 'static');

        $this->assertNotNull($payment->unique_suffix);
        $this->assertSame((int) $payment->amount, (int) $payment->claimable_amount);

        $instructions = $payment->instructions;

        $this->assertSame('qris', $instructions['type']);
        $this->assertSame((int) $payment->amount, $instructions['amount']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $instructions['qr_svg']);

        // Rebuild the payload the QR encodes and check what it actually carries.
        $payload = Qris::dynamic(self::TEST_STATIC, (int) $payment->amount);

        $this->assertTrue(Qris::looksValid($payload));
        $this->assertStringContainsString('010212', $payload, 'Must be a single-use dynamic code.');
        $amount = (int) $payment->amount;
        $this->assertStringContainsString(
            '54'.str_pad((string) strlen((string) $amount), 2, '0', STR_PAD_LEFT).$amount,
            $payload,
        );
    }

    public function test_the_buyer_is_charged_the_total_the_page_showed_them(): void
    {
        $order = $this->order(100000);

        $this->assertEquals(0, (float) $order->payment_fee, 'No method chosen yet.');

        $payment = $this->payments()->createPayment($order, 'qris', 'qris', 'static');
        $order->refresh();

        // QRIS is never surcharged to the buyer. Its processing cost is part
        // of seller/platform unit economics instead.
        $this->assertEquals(0, (float) $order->payment_fee);
        $this->assertEquals(100000, (float) $order->grand_total);

        // The payable figure is the displayed total plus the identifying suffix.
        $this->assertSame(100000 + (int) $payment->unique_suffix, (int) $payment->amount);
    }

    public function test_switching_payment_method_does_not_stack_two_fees(): void
    {
        $order = $this->order(100000);

        $this->payments()->createPayment($order, 'qris', 'qris', 'static');
        $this->payments()->createPayment($order->fresh(), 'manual_transfer', 'bank_transfer', 'manual');
        $order->refresh();

        $this->assertEquals(0, (float) $order->payment_fee, 'Manual transfer is free.');
        $this->assertEquals(100000, (float) $order->grand_total);
    }

    public function test_two_open_payments_can_never_share_an_amount(): void
    {
        $amounts = [];

        for ($i = 0; $i < 20; $i++) {
            $payment = $this->payments()->createPayment(
                $this->order(100000, "dup-{$i}"),
                'qris',
                'qris',
                'static',
            );

            $amounts[] = (int) $payment->amount;
        }

        $this->assertSame(
            count($amounts),
            count(array_unique($amounts)),
            'A shared amount would make an incoming transfer ambiguous.',
        );
    }

    public function test_the_database_refuses_a_duplicate_open_amount(): void
    {
        $payment = $this->payments()->createPayment($this->order(), 'qris', 'qris', 'static');

        // Bypasses the provider: the guarantee has to live in the schema.
        $this->expectException(UniqueConstraintViolationException::class);

        Payment::create([
            'order_id' => $payment->order_id,
            'provider' => 'qris',
            'method' => 'qris',
            'channel' => 'static',
            'status' => PaymentStatus::Pending,
            'amount' => $payment->amount,
            'claimable_amount' => $payment->amount,
            'currency' => 'IDR',
        ]);
    }

    public function test_coming_back_to_qris_shows_the_same_qr_not_a_new_amount(): void
    {
        $order = $this->order();

        $first = $this->payments()->createPayment($order, 'qris', 'qris', 'static');

        // Buyer glances at bank transfer, then returns to QRIS.
        $this->payments()->createPayment($order->fresh(), 'manual_transfer', 'bank_transfer', 'manual');
        $second = $this->payments()->createPayment($order->fresh(), 'qris', 'qris', 'static');

        // Reusing the open attempt means a buyer who already scanned still has a
        // valid QR, and we do not burn a second amount for one order.
        $this->assertSame($first->id, $second->id);
        $this->assertSame((int) $first->amount, (int) $second->amount);
    }

    public function test_an_abandoned_checkout_gives_its_amount_back(): void
    {
        $payment = $this->payments()->createPayment($this->order(), 'qris', 'qris', 'static');
        $amount = (int) $payment->amount;

        // The window closes with nobody having paid.
        $payment->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertSame(1, $this->payments()->expireStale());
        $this->assertNull(
            $payment->fresh()->claimable_amount,
            'Otherwise this rupiah figure is reserved forever and the range slowly burns out.',
        );

        // Proof it is genuinely reusable: the schema now accepts it again.
        Payment::create([
            'order_id' => $payment->order_id,
            'provider' => 'qris',
            'method' => 'qris',
            'channel' => 'static',
            'status' => PaymentStatus::Pending,
            'amount' => $amount,
            'claimable_amount' => $amount,
            'currency' => 'IDR',
        ]);

        $this->assertSame(2, Payment::where('amount', $amount)->count());
    }

    public function test_admin_confirmation_moves_money_exactly_like_a_gateway_callback(): void
    {
        $order = $this->order(100000);
        $payment = $this->payments()->createPayment($order, 'qris', 'qris', 'static');

        $admin = $this->makeUser([Role::SUPER_ADMIN]);
        $wallet = $this->store->owner->walletOrCreate();

        $this->assertEquals(0, (float) $wallet->pending_balance);

        $this->actingAs($admin)
            ->post("/admin/pembayaran-qris/{$payment->id}/setujui")
            ->assertRedirect();

        $order->refresh();
        $wallet->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertTrue($order->status->isSettled());

        // The seller is paid on the goods, never on the buyer's gateway fee or
        // the identifying suffix — both of those stay with the platform.
        $expectedNet = 100000 - (float) $order->platform_fee;

        $this->assertEquals($expectedNet, (float) $order->seller_net);
        $this->assertEquals(
            $expectedNet - (float) $order->reserve_amount,
            (float) $wallet->pending_balance,
        );
        $this->assertEquals((float) $order->reserve_amount, (float) $wallet->reserve_balance);

        // And the ledger still adds up to the wallet.
        $this->assertSame([], app(LedgerService::class)->reconcile($wallet));
    }

    public function test_a_confirmed_amount_is_released_for_the_next_buyer(): void
    {
        $payment = $this->payments()->createPayment($this->order(), 'qris', 'qris', 'static');
        $admin = $this->makeUser([Role::SUPER_ADMIN]);

        $this->actingAs($admin)->post("/admin/pembayaran-qris/{$payment->id}/setujui");

        $this->assertNull($payment->fresh()->claimable_amount);
    }

    public function test_confirming_twice_does_not_pay_the_seller_twice(): void
    {
        $order = $this->order(100000);
        $payment = $this->payments()->createPayment($order, 'qris', 'qris', 'static');
        $admin = $this->makeUser([Role::SUPER_ADMIN]);

        $this->actingAs($admin)->post("/admin/pembayaran-qris/{$payment->id}/setujui");
        $balance = (float) $this->store->owner->walletOrCreate()->fresh()->pending_balance;

        $this->actingAs($admin)->post("/admin/pembayaran-qris/{$payment->id}/setujui");

        $this->assertEquals(
            $balance,
            (float) $this->store->owner->walletOrCreate()->fresh()->pending_balance,
        );
    }

    public function test_rejecting_leaves_the_order_unpaid_and_the_seller_uncredited(): void
    {
        $order = $this->order(100000);
        $payment = $this->payments()->createPayment($order, 'qris', 'qris', 'static');
        $admin = $this->makeUser([Role::SUPER_ADMIN]);

        $this->actingAs($admin)
            ->post("/admin/pembayaran-qris/{$payment->id}/tolak", ['reason' => 'Dana tidak masuk.'])
            ->assertRedirect();

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->claimable_amount);
        $this->assertFalse($order->fresh()->status->isSettled());
        $this->assertEquals(0, (float) $this->store->owner->walletOrCreate()->fresh()->pending_balance);
    }

    public function test_a_creator_cannot_confirm_payments(): void
    {
        $payment = $this->payments()->createPayment($this->order(), 'qris', 'qris', 'static');

        $this->actingAs($this->store->owner)
            ->post("/admin/pembayaran-qris/{$payment->id}/setujui")
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_checkout_is_refused_when_no_merchant_code_is_configured(): void
    {
        config(['payments.qris.static_payload' => '']);

        $this->expectException(ValidationException::class);

        $this->payments()->createPayment($this->order(), 'qris', 'qris', 'static');
    }

    public function test_the_admin_queue_finds_a_payment_by_its_exact_amount(): void
    {
        $payment = $this->payments()->createPayment($this->order(), 'qris', 'qris', 'static');
        $admin = $this->makeUser([Role::SUPER_ADMIN]);

        $this->actingAs($admin)
            ->get('/admin/pembayaran-qris?q='.(int) $payment->amount)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('payments.data.0.reference', $payment->reference));
    }
}
