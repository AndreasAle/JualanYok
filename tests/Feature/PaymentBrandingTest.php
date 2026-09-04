<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Store;
use App\Payments\PaymentManager;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The payment processor stays behind the curtain.
 *
 * Which gateway settles the money is an arrangement between the platform and
 * that gateway. A buyer meets a brand they do not recognise on a payment screen
 * and reads it as a redirect somewhere they did not intend to go — and a
 * diagnostic about signatures or callback URLs is something they can do
 * absolutely nothing with.
 */
class PaymentBrandingTest extends TestCase
{
    use RefreshDatabase;

    private const BRANDS = ['ipaymu', 'midtrans', 'xendit'];

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        config([
            'app.url' => 'https://jualanyok.id',
            'payments.providers.ipaymu.enabled' => true,
            'payments.providers.ipaymu.va' => '1179000899',
            'payments.providers.ipaymu.api_key' => 'CONTOH-API-KEY',
            'payments.providers.ipaymu.production' => false,
            'payments.providers.ipaymu.mode' => 'redirect',
        ]);

        $this->store = $this->makeStore();
    }

    private function assertUnbranded(?string $text, string $context): void
    {
        foreach (self::BRANDS as $brand) {
            $this->assertStringNotContainsStringIgnoringCase(
                $brand,
                (string) $text,
                "{$context} menyebut nama penyedia pembayaran: {$text}",
            );
        }
    }

    public function test_no_payment_method_shown_at_checkout_names_its_processor(): void
    {
        foreach (app(PaymentManager::class)->availableMethods() as $method) {
            $this->assertUnbranded($method['label'] ?? null, 'Label metode');
            $this->assertUnbranded($method['provider_name'] ?? null, 'Nama provider');
        }
    }

    public function test_a_refused_charge_tells_the_buyer_nothing_about_the_processor(): void
    {
        // The exact refusal the live account returns for an unapproved account.
        Http::fake([
            '*' => Http::response(
                ['Status' => 406, 'Success' => false, 'Message' => 'Suspicious buyer', 'Data' => null],
                406,
            ),
        ]);

        $product = $this->makeProduct($this->store, ['price' => 49000]);

        $order = app(CheckoutService::class)->createOrder(
            $this->store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Andreas', 'email' => 'andreas@example.test', 'phone' => '081532963501'],
            ['idempotency_key' => 'branding-1'],
        );

        $payment = app(PaymentService::class)->createPayment($order, 'ipaymu', 'qris', 'mpm');

        $this->assertSame(PaymentStatus::Failed, $payment->status);

        $error = (string) $payment->attempts()->latest('id')->value('error');

        $this->assertUnbranded($error, 'Pesan error checkout');

        // Nor the internal diagnosis the payer can do nothing with.
        $this->assertStringNotContainsStringIgnoringCase('APP_URL', $error);
        $this->assertStringNotContainsStringIgnoringCase('signature', $error);
        $this->assertStringNotContainsStringIgnoringCase('API Key', $error);

        // It still has to tell them what to do next.
        $this->assertNotSame('', trim($error));

        // The real reason survives where the operator can reach it.
        $this->get(route('checkout.status', $order->number))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('payment.error', $error));
    }

    public function test_the_subscription_screen_does_not_name_the_processor(): void
    {
        $creator = $this->store->owner;

        $this->actingAs($creator)
            ->get('/dashboard/langganan')
            ->assertOk()
            ->assertInertia(function ($page) {
                $this->assertUnbranded(
                    $page->toArray()['props']['billing']['provider_name'] ?? null,
                    'Nama provider langganan',
                );

                return true;
            });
    }

    public function test_the_hosted_checkout_steps_stay_unbranded(): void
    {
        Http::fake([
            '*/api/v2/payment' => Http::response([
                'Status' => 200,
                'Success' => true,
                'Message' => 'Success',
                'Data' => ['SessionID' => 'abc', 'Url' => 'https://payment.example.test/#/abc'],
            ]),
        ]);

        $product = $this->makeProduct($this->store, ['price' => 49000]);

        $order = app(CheckoutService::class)->createOrder(
            $this->store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Andreas', 'email' => 'andreas@example.test', 'phone' => '081532963501'],
            ['idempotency_key' => 'branding-2'],
        );

        $payment = app(PaymentService::class)->createPayment($order, 'ipaymu', 'qris', 'mpm');

        foreach ($payment->instructions['steps'] ?? [] as $step) {
            $this->assertUnbranded($step, 'Langkah pembayaran');
        }
    }
}
