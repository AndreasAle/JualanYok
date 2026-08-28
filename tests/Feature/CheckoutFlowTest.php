<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Models\Coupon;
use App\Models\DigitalAccess;
use App\Models\Inventory;
use App\Models\Order;
use App\Payments\PaymentResult;
use App\Services\CheckoutService;
use App\Services\LedgerService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_guest_can_checkout_and_order_is_created_with_correct_totals(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['price' => 150000]);

        $response = $this->post("/{$store->username}/checkout", [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'name' => 'Rina',
            'email' => 'rina@example.test',
            'terms' => true,
            'idempotency_key' => 'test-key-1',
        ]);

        $order = Order::firstOrFail();

        $response->assertRedirect("/checkout/{$order->number}");

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertEquals(300000, (float) $order->subtotal);
        // Free plan charges 7.5%, so the seller nets the rest.
        $this->assertEquals(22500, (float) $order->platform_fee);
        $this->assertEquals(300000, (float) $order->grand_total);
        $this->assertMatchesRegularExpression('/^JY-\d{8}-[A-Z0-9]{6}$/', $order->number);
    }

    public function test_repeated_submit_with_same_idempotency_key_creates_one_order(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);

        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'name' => 'Rina',
            'email' => 'rina@example.test',
            'terms' => true,
            'idempotency_key' => 'same-key',
        ];

        $this->post("/{$store->username}/checkout", $payload);
        $this->post("/{$store->username}/checkout", $payload);

        $this->assertSame(1, Order::count());
    }

    public function test_price_comes_from_the_database_not_the_request(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['price' => 200000]);

        $this->post("/{$store->username}/checkout", [
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 1]],
            'name' => 'Rina',
            'email' => 'rina@example.test',
            'terms' => true,
            'idempotency_key' => 'cheat-key',
        ]);

        $this->assertEquals(200000, (float) Order::firstOrFail()->grand_total);
    }

    public function test_coupon_reduces_the_total(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['price' => 100000]);

        Coupon::create([
            'store_id' => $store->id,
            'code' => 'HEMAT20',
            'type' => 'percentage',
            'value' => 20,
            'is_active' => true,
        ]);

        $this->post("/{$store->username}/checkout", [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'name' => 'Rina',
            'email' => 'rina@example.test',
            'coupon_code' => 'HEMAT20',
            'terms' => true,
            'idempotency_key' => 'coupon-key',
        ]);

        $order = Order::firstOrFail();

        $this->assertEquals(20000, (float) $order->discount_total);
        $this->assertEquals(80000, (float) $order->grand_total);
    }

    public function test_stock_is_reserved_and_cannot_be_oversold(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, [
            'type' => ProductType::Physical,
            'price' => 50000,
            'stock' => 2,
        ]);

        $checkout = app(CheckoutService::class);

        $checkout->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 2]],
            ['name' => 'A', 'email' => 'a@example.test'],
            ['idempotency_key' => 'stock-1'],
        );

        $this->assertSame(2, Inventory::where('product_id', $product->id)->value('reserved'));

        $this->expectException(ValidationException::class);

        $checkout->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'B', 'email' => 'b@example.test'],
            ['idempotency_key' => 'stock-2'],
        );
    }

    public function test_paying_settles_the_order_grants_access_and_credits_the_ledger(): void
    {
        $store = $this->makeStore();
        // makeProduct already attaches the one file a digital product needs.
        $product = $this->makeProduct($store, ['price' => 100000]);

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => 'pay-key'],
        );

        $payments = app(PaymentService::class);
        $payment = $payments->createPayment($order, 'mock', 'qris', 'qris');

        $payments->applyResult(new PaymentResult(
            status: PaymentStatus::Paid,
            reference: $payment->reference,
            amount: (float) $payment->amount,
            paidAt: now(),
            eventId: 'evt-1',
        ), 'mock');

        $order->refresh();

        $this->assertTrue($order->status->isSettled());
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);

        // Digital access is created by the queued listener (sync in tests).
        $this->assertSame(1, DigitalAccess::where('order_id', $order->id)->count());

        $wallet = $store->owner->walletOrCreate();
        $this->assertEquals(
            (float) $order->seller_net - (float) $order->reserve_amount,
            (float) $wallet->pending_balance,
        );
        $this->assertEquals((float) $order->reserve_amount, (float) $wallet->reserve_balance);
        $this->assertSame([], app(LedgerService::class)->reconcile($wallet));
    }

    public function test_replayed_webhook_does_not_credit_the_seller_twice(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['price' => 100000]);

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => 'replay-key'],
        );

        $payments = app(PaymentService::class);
        $payment = $payments->createPayment($order, 'mock', 'qris', 'qris');

        $result = new PaymentResult(
            status: PaymentStatus::Paid,
            reference: $payment->reference,
            amount: (float) $payment->amount,
            paidAt: now(),
            eventId: 'evt-replay',
        );

        $payments->applyResult($result, 'mock');
        $payments->applyResult($result, 'mock');

        $wallet = $store->owner->walletOrCreate();

        $order->refresh();
        $this->assertEquals(
            (float) $order->seller_net - (float) $order->reserve_amount,
            (float) $wallet->pending_balance,
        );
        $this->assertSame(1, DB::table('ledger_entries')
            ->where('idempotency_key', 'order-revenue:'.$order->id)
            ->count());
    }

    public function test_callback_with_mismatched_amount_is_rejected(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['price' => 100000]);

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => 'amount-key'],
        );

        $payments = app(PaymentService::class);
        $payment = $payments->createPayment($order, 'mock', 'qris', 'qris');

        $this->expectException(\RuntimeException::class);

        $payments->applyResult(new PaymentResult(
            status: PaymentStatus::Paid,
            reference: $payment->reference,
            amount: 1000.0,          // gateway claims a much smaller amount
            paidAt: now(),
            eventId: 'evt-bad-amount',
        ), 'mock');
    }

    public function test_webhook_endpoint_rejects_an_unsigned_payload(): void
    {
        $response = $this->postJson('/webhooks/payments/mock', [
            'reference' => 'MOCK-NOPE',
            'status' => 'paid',
            'amount' => 100000,
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseHas('payment_webhooks', ['signature_valid' => false]);
    }

    public function test_signed_webhook_settles_the_payment(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['price' => 100000]);

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => 'signed-key'],
        );

        $payment = app(PaymentService::class)->createPayment($order, 'mock', 'qris', 'qris');

        $body = json_encode([
            'reference' => $payment->reference,
            'status' => 'paid',
            'amount' => (float) $payment->amount,
            'event_id' => 'evt-signed',
        ]);

        $signature = hash_hmac('sha256', $body, config('payments.providers.mock.secret'));

        $this->call(
            'POST',
            '/webhooks/payments/mock',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_JUALANYOK_SIGNATURE' => $signature],
            $body,
        )->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }
}
