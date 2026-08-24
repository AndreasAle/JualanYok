<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Payments\PaymentManager;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IpaymuPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const VA = '1179000000000001';

    private const API_KEY = 'sandbox-api-key-for-tests';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        config([
            'payments.providers.ipaymu.enabled' => true,
            'payments.providers.ipaymu.va' => self::VA,
            'payments.providers.ipaymu.api_key' => self::API_KEY,
            'payments.providers.ipaymu.production' => false,
            'payments.providers.ipaymu.fee_direction' => 'MERCHANT',
        ]);
    }

    public function test_direct_payment_is_signed_and_qris_uses_ipaymu_hosted_page(): void
    {
        Http::fake(function (ClientRequest $request) {
            $payload = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);

            return Http::response([
                'Status' => 200,
                'Success' => true,
                'Message' => 'Success',
                'Data' => [
                    'TransactionId' => 991122,
                    'ReferenceId' => $payload['referenceId'],
                    'Via' => 'qris',
                    'Channel' => 'mpm',
                    'PaymentNo' => '',
                    'PaymentName' => 'QRIS Dynamic',
                    'Total' => $payload['amount'],
                    'Fee' => 700,
                    'Expired' => now()->addMinutes(5)->format('Y-m-d H:i:s'),
                    'Note' => null,
                    'Url' => 'https://sandbox.ipaymu.com/payment/991122',
                ],
            ]);
        });

        $order = $this->makeOrder();
        $payment = app(PaymentService::class)->createPayment($order, 'ipaymu', 'qris', 'mpm');

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame($order->number.'-'.$payment->id, $payment->reference);
        $this->assertSame('redirect', $payment->instructions['type']);
        $this->assertSame('https://sandbox.ipaymu.com/payment/991122', $payment->redirect_url);
        $this->assertEquals(700, (float) $payment->fee);

        Http::assertSent(function (ClientRequest $request) use ($order) {
            $body = $request->body();
            $bodyHash = strtolower(hash('sha256', $body));
            $stringToSign = 'POST:'.self::VA.':'.$bodyHash.':'.self::API_KEY;
            $expectedSignature = hash_hmac('sha256', $stringToSign, self::API_KEY);
            $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

            return $request->url() === 'https://sandbox.ipaymu.com/api/v2/payment/direct'
                && $request->hasHeader('va', self::VA)
                && $request->hasHeader('signature', $expectedSignature)
                && $payload['amount'] === 100000
                && $payload['paymentMethod'] === 'qris'
                && $payload['paymentChannel'] === 'mpm'
                && $payload['feeDirection'] === 'MERCHANT'
                && $payload['notifyUrl'] === route('webhooks.payments', ['provider' => 'ipaymu'])
                && $payload['successUrl'] === route('checkout.status', $order->number)
                && ! str_contains($body, self::API_KEY);
        });
    }

    public function test_verified_callback_settles_once_and_replay_is_idempotent(): void
    {
        $order = $this->makeOrder();
        $payment = $this->makeRemotePayment($order);

        $payload = [
            'buyer_email' => $order->customer_email,
            'buyer_name' => $order->customer_name,
            'buyer_phone' => $order->customer_phone,
            'fee' => '700',
            'paid_at' => now()->format('Y-m-d H:i:s'),
            'reference_id' => $payment->reference,
            'status' => 'berhasil',
            'status_code' => '1',
            'transaction_status_code' => '1',
            'paid_off' => '99300',
            'trx_id' => '88112233',
            'merchant' => self::VA,
            'amount' => '100000',
            'total' => '100000',
            'url' => 'https://jualanyok.test/webhooks/payments/ipaymu',
            'is_escrow' => '0',
            'additional_info' => '[]',
        ];
        $signature = $this->callbackSignature($payload);

        $headers = [
            'X-Signature' => $signature,
            'X-External-ID' => 'callback-88112233',
            'X-Timestamp' => now()->toIso8601String(),
        ];

        $this->withHeaders($headers)
            ->post('/webhooks/payments/ipaymu', $payload)
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->withHeaders($headers)
            ->post('/webhooks/payments/ipaymu', $payload)
            ->assertOk()
            ->assertJson(['status' => 'duplicate']);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertTrue($order->fresh()->status->isSettled());
        $this->assertSame(1, DB::table('ledger_entries')
            ->where('idempotency_key', 'order-revenue:'.$order->id)
            ->count());
    }

    public function test_callback_with_invalid_signature_or_merchant_is_rejected(): void
    {
        $payload = [
            'merchant' => 'different-merchant',
            'reference_id' => 'JY-NOPE-1',
            'status' => 'berhasil',
            'status_code' => '1',
            'transaction_status_code' => '1',
            'paid_off' => '100000',
            'trx_id' => '777',
            'amount' => '100000',
            'is_escrow' => '0',
            'additional_info' => '[]',
        ];

        $this->withHeader('X-Signature', $this->callbackSignature($payload))
            ->post('/webhooks/payments/ipaymu', $payload)
            ->assertUnauthorized();
    }

    public function test_json_callback_normalises_null_boolean_and_escaped_url(): void
    {
        $payload = [
            'merchant' => self::VA,
            'reference_id' => 'JY-UNKNOWN-JSON',
            'status' => 'pending',
            'status_code' => 0,
            'transaction_status_code' => 0,
            'paid_off' => 0,
            'trx_id' => 778899,
            'amount' => '100000',
            'is_escrow' => false,
            'additional_info' => [],
            'settlement_date' => null,
            'url' => 'https://jualanyok.test/webhooks/payments/ipaymu',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/webhooks/payments/ipaymu',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => $this->callbackSignature($payload),
                'HTTP_X_EXTERNAL_ID' => 'callback-json-778899',
            ],
            $body,
        )->assertOk()->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('payment_webhooks', [
            'provider' => 'ipaymu',
            'event_id' => 'callback-json-778899',
            'signature_valid' => true,
            'processed' => true,
        ]);
    }

    public function test_phone_is_required_during_store_checkout_when_ipaymu_is_enabled(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);

        $this->from('/'.$store->username)
            ->post('/'.$store->username.'/checkout', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'name' => 'Rina',
                'email' => 'rina@example.test',
                'terms' => true,
                'idempotency_key' => 'ipaymu-phone-required',
            ])
            ->assertRedirect('/'.$store->username)
            ->assertSessionHasErrors('phone');
    }

    public function test_manager_exposes_documented_ipaymu_methods(): void
    {
        $methods = collect(app(PaymentManager::class)->availableMethods())
            ->where('provider', 'ipaymu');

        $this->assertTrue($methods->contains(fn (array $method) => $method['method'] === 'qris' && $method['channel'] === 'mpm'));
        $this->assertTrue($methods->contains(fn (array $method) => $method['method'] === 'va' && $method['channel'] === 'bca'));
        $this->assertTrue($methods->contains(fn (array $method) => $method['method'] === 'ewallet' && $method['channel'] === 'dana'));
    }

    private function makeOrder(): Order
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['price' => 100000]);

        return app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test', 'phone' => '081234567890'],
            ['idempotency_key' => 'ipaymu-'.str()->random(12)],
        );
    }

    private function makeRemotePayment(Order $order)
    {
        Http::fake(function (ClientRequest $request) {
            $payload = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);

            return Http::response([
                'Status' => 200,
                'Success' => true,
                'Data' => [
                    'ReferenceId' => $payload['referenceId'],
                    'Via' => 'va',
                    'Channel' => 'bca',
                    'PaymentNo' => '1234567890123456',
                    'PaymentName' => 'BCA Virtual Account',
                    'Total' => $payload['amount'],
                    'Fee' => 0,
                    'Expired' => now()->addHours(12)->format('Y-m-d H:i:s'),
                    'Url' => 'https://sandbox.ipaymu.com/payment/test',
                ],
            ]);
        });

        return app(PaymentService::class)->createPayment($order, 'ipaymu', 'va', 'bca');
    }

    private function callbackSignature(array $payload): string
    {
        $normalised = [];

        foreach ($payload as $key => $value) {
            $normalised[$key] = match (true) {
                $key === 'is_escrow' => in_array($value, [true, 1, '1', 'true'], true),
                in_array($key, ['trx_id', 'status_code', 'transaction_status_code', 'paid_off'], true) => (int) $value,
                $key === 'additional_info' && $value === '[]' => [],
                $key === 'additional_info' => $value,
                $value === null => 'null',
                $value === true => 'true',
                $value === false => 'false',
                default => (string) $value,
            };
        }

        $normalised['additional_info'] ??= [];
        ksort($normalised, SORT_STRING);

        return hash_hmac('sha256', json_encode($normalised, JSON_THROW_ON_ERROR), self::VA);
    }
}
