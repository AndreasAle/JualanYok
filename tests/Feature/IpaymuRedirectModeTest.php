<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Store;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Charging through iPaymu's own checkout page.
 *
 * `payment/direct` renders the QR inside JualanYok but needs the merchant
 * account to be approved for API charging. Without that approval every attempt
 * comes back "Suspicious buyer" — a message about the payer for a permission
 * that was never granted. The hosted page works on any account, so it is the
 * default.
 */
class IpaymuRedirectModeTest extends TestCase
{
    use RefreshDatabase;

    private const VA = '1179000899';

    private const API_KEY = 'SANDBOX-CONTOH-API-KEY';

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        config([
            'app.url' => 'https://jualanyok.id',
            'payments.providers.ipaymu.enabled' => true,
            'payments.providers.ipaymu.va' => self::VA,
            'payments.providers.ipaymu.api_key' => self::API_KEY,
            'payments.providers.ipaymu.production' => false,
            'payments.providers.ipaymu.fee_direction' => 'MERCHANT',
            'payments.providers.ipaymu.mode' => 'redirect',
        ]);

        $this->store = $this->makeStore();
    }

    private function order(float $price = 49000): Order
    {
        $product = $this->makeProduct($this->store, ['price' => $price]);

        return app(CheckoutService::class)->createOrder(
            $this->store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Andreas', 'email' => 'andreas@example.test', 'phone' => '081532963501'],
            ['idempotency_key' => 'redirect-1'],
        );
    }

    /** The shape a live account actually returns from `/payment`. */
    private function fakeHostedSuccess(): void
    {
        Http::fake([
            '*/api/v2/payment' => Http::response([
                'Status' => 200,
                'Success' => true,
                'Message' => 'Success',
                'Data' => [
                    'SessionID' => 'a10763dd-a657-471b-91c7-9233e152c574',
                    'Url' => 'https://payment.ipaymu.com/#/a10763dd-a657-471b-91c7-9233e152c574',
                ],
            ]),
        ]);
    }

    public function test_the_hosted_page_is_used_instead_of_direct_charging(): void
    {
        $this->fakeHostedSuccess();

        $payment = app(PaymentService::class)->createPayment($this->order(), 'ipaymu', 'qris', 'mpm');

        $this->assertSame(PaymentStatus::Pending, $payment->status);

        Http::assertSent(function (ClientRequest $request) {
            $this->assertStringEndsWith('/api/v2/payment', $request->url());
            $this->assertStringNotContainsString('/payment/direct', $request->url());

            return true;
        });
    }

    public function test_the_buyer_is_given_the_checkout_link(): void
    {
        $this->fakeHostedSuccess();

        $payment = app(PaymentService::class)->createPayment($this->order(), 'ipaymu', 'qris', 'mpm');

        $this->assertSame(
            'https://payment.ipaymu.com/#/a10763dd-a657-471b-91c7-9233e152c574',
            $payment->redirect_url,
        );

        // The hosted page is the whole instruction; there is no QR to render.
        $this->assertSame('redirect', $payment->instructions['type']);
        $this->assertNotEmpty($payment->instructions['steps']);
    }

    public function test_the_cart_is_itemised_because_the_hosted_page_expects_that(): void
    {
        $this->fakeHostedSuccess();

        app(PaymentService::class)->createPayment($this->order(49000), 'ipaymu', 'qris', 'mpm');

        Http::assertSent(function (ClientRequest $request) {
            $body = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);

            // `/payment` prices per line rather than taking a single total, and
            // picks the method on its own screen.
            $this->assertSame([1], $body['qty']);
            $this->assertSame([49000], $body['price']);
            $this->assertCount(1, $body['product']);

            $this->assertArrayNotHasKey('amount', $body);
            $this->assertArrayNotHasKey('paymentMethod', $body);
            $this->assertArrayNotHasKey('paymentChannel', $body);

            return true;
        });
    }

    public function test_the_request_is_still_signed_and_carries_the_callback(): void
    {
        $this->fakeHostedSuccess();

        app(PaymentService::class)->createPayment($this->order(), 'ipaymu', 'qris', 'mpm');

        Http::assertSent(function (ClientRequest $request) {
            $body = $request->body();

            $expected = hash_hmac(
                'sha256',
                'POST:'.self::VA.':'.strtolower(hash('sha256', $body)).':'.self::API_KEY,
                self::API_KEY,
            );

            $this->assertSame($expected, $request->header('signature')[0]);
            $this->assertSame(self::VA, $request->header('va')[0]);

            $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
            $this->assertStringContainsString('/webhooks/payments/ipaymu', $payload['notifyUrl']);

            return true;
        });
    }

    public function test_a_reply_without_a_link_fails_rather_than_showing_an_empty_page(): void
    {
        Http::fake([
            '*/api/v2/payment' => Http::response([
                'Status' => 200,
                'Success' => true,
                'Message' => 'Success',
                'Data' => ['SessionID' => 'abc'],
            ]),
        ]);

        $payment = app(PaymentService::class)->createPayment($this->order(), 'ipaymu', 'qris', 'mpm');

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertStringContainsString(
            'tautan pembayaran',
            (string) $payment->attempts()->latest('id')->value('error'),
        );
    }

    public function test_switching_to_direct_mode_changes_the_endpoint_back(): void
    {
        config(['payments.providers.ipaymu.mode' => 'direct']);

        Http::fake([
            '*/api/v2/payment/direct' => Http::response([
                'Status' => 200,
                'Success' => true,
                'Message' => 'Success',
                'Data' => [
                    'TransactionId' => 991122,
                    'ReferenceId' => 'JY-1',
                    'Via' => 'qris',
                    'Channel' => 'mpm',
                    'QrString' => '00020101021226',
                    'QrImage' => 'https://sandbox.ipaymu.com/qr/991122.png',
                    'Total' => 49000,
                    'Expired' => now()->addMinutes(5)->format('Y-m-d H:i:s'),
                ],
            ]),
        ]);

        app(PaymentService::class)->createPayment($this->order(), 'ipaymu', 'qris', 'mpm');

        Http::assertSent(fn (ClientRequest $request) => str_ends_with($request->url(), '/api/v2/payment/direct'));
    }
}
