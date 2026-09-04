<?php

namespace Tests\Feature;

use App\Enums\PlanPaymentStatus;
use App\Models\Plan;
use App\Models\PlanPayment;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\PlanPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IpaymuPlanPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const VA = '1179000000000001';

    private const API_KEY = 'sandbox-api-key-for-plan-tests';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        config([
            // iPaymu has to reach us to report a payment, so a working setup is
            // always on a public host — the tests model that, not localhost.
            'app.url' => 'https://jualanyok.id',
            'payments.providers.ipaymu.enabled' => true,
            'payments.providers.ipaymu.va' => self::VA,
            'payments.providers.ipaymu.api_key' => self::API_KEY,
            'payments.providers.ipaymu.production' => false,
            'payments.providers.ipaymu.fee_direction' => 'MERCHANT',
            // These cover the in-app QRIS path, which needs an iPaymu account
            // approved for API charging. Redirect mode is covered separately.
            'payments.providers.ipaymu.mode' => 'direct',
            'payments.qris.enabled' => false,
        ]);
    }

    public function test_paid_plan_opens_a_signed_ipaymu_charge(): void
    {
        Http::fake(fn (ClientRequest $request) => $this->directResponse($request));

        $user = $this->creator();
        $plan = $this->paidPlan();
        $payment = app(PlanPaymentService::class)->open($user, $plan, 'monthly');

        $this->assertSame('ipaymu', $payment->provider);
        $this->assertSame('qris', $payment->method);
        $this->assertSame('mpm', $payment->channel);
        $this->assertSame(PlanPaymentStatus::Pending, $payment->status);
        $this->assertSame((int) $plan->price_monthly, (int) $payment->amount);
        $this->assertSame(0, $payment->unique_suffix);
        $this->assertSame('551122', $payment->gateway_transaction_id);
        $this->assertSame('redirect', $payment->instructions['type']);
        $this->assertSame('https://sandbox.ipaymu.com/payment/subscription', $payment->redirect_url);

        Http::assertSent(function (ClientRequest $request) use ($payment, $plan) {
            $body = $request->body();
            $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
            $bodyHash = strtolower(hash('sha256', $body));
            $expected = hash_hmac(
                'sha256',
                'POST:'.self::VA.':'.$bodyHash.':'.self::API_KEY,
                self::API_KEY,
            );

            return $request->url() === 'https://sandbox.ipaymu.com/api/v2/payment/direct'
                && $request->hasHeader('signature', $expected)
                && $payload['referenceId'] === $payment->reference
                && $payload['amount'] === (int) $plan->price_monthly
                && $payload['paymentMethod'] === 'qris'
                && $payload['paymentChannel'] === 'mpm'
                && $payload['feeDirection'] === 'MERCHANT'
                && $payload['notifyUrl'] === route('webhooks.payments', ['provider' => 'ipaymu'])
                && $payload['successUrl'] === route('creator.subscription.pay', $payment->reference);
        });

        $this->actingAs($user)
            ->get(route('creator.subscription.pay', $payment->reference))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('automatic', true)
                ->where('payment.provider', 'ipaymu')
                ->where('payment.redirect_url', 'https://sandbox.ipaymu.com/payment/subscription'));
    }

    public function test_verified_callback_activates_the_plan_and_creates_one_invoice(): void
    {
        Http::fake(fn (ClientRequest $request) => $this->directResponse($request));

        $user = $this->creator();
        $plan = $this->paidPlan();
        $payment = app(PlanPaymentService::class)->open($user, $plan);
        $payload = $this->paidCallback($payment);
        $headers = [
            'X-Signature' => $this->callbackSignature($payload),
            'X-External-ID' => 'subscription-callback-551122',
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

        $payment->refresh();
        $subscription = $user->fresh()->activeSubscription();

        $this->assertSame(PlanPaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($subscription);
        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame('ipaymu', $subscription->provider);
        $this->assertSame('551122', $subscription->provider_reference);
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
        $this->assertSame(1, SubscriptionInvoice::where('subscription_id', $subscription->id)->count());
        $this->assertDatabaseHas('subscription_invoices', [
            'subscription_id' => $subscription->id,
            'status' => 'PAID',
        ]);
        $this->assertDatabaseCount('financial_journals', 1);
        $this->assertDatabaseHas('financial_journals', [
            'event_type' => 'SUBSCRIPTION_PAID',
            'reference_type' => $payment->getMorphClass(),
            'reference_id' => $payment->id,
        ]);
        $this->assertEquals(
            0.0,
            (float) \DB::table('financial_postings')
                ->selectRaw("SUM(CASE WHEN direction = 'DEBIT' THEN amount ELSE -amount END) AS balance")
                ->value('balance'),
        );
    }

    public function test_status_check_recovers_a_paid_subscription_when_callback_is_delayed(): void
    {
        Http::fake(function (ClientRequest $request) {
            if (str_ends_with($request->url(), '/api/v2/transaction')) {
                $payload = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);

                return Http::response([
                    'Status' => 200,
                    'Success' => true,
                    'Data' => [[
                        'transactionId' => $payload['transactionId'],
                        'status' => 1,
                        'statusDesc' => 'Success',
                        'paidStatus' => 'paid',
                        'fee' => 1225,
                        'successDate' => now()->format('Y-m-d H:i:s'),
                    ]],
                ]);
            }

            return $this->directResponse($request);
        });

        $user = $this->creator();
        $plan = $this->paidPlan();
        $payment = app(PlanPaymentService::class)->open($user, $plan);

        $this->actingAs($user)
            ->post(route('creator.subscription.pay.check-status', $payment->reference))
            ->assertRedirect();

        $this->assertSame(PlanPaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame($plan->id, $user->fresh()->activeSubscription()?->plan_id);
    }

    public function test_subscription_page_advertises_automatic_ipaymu_billing(): void
    {
        $this->actingAs($this->creator())
            ->get(route('creator.subscription'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('billing.enabled', true)
                ->where('billing.provider', 'ipaymu')
                ->where('billing.automatic', true)
                // Named for the experience, not the processor behind it.
                ->where('billing.provider_name', 'Pembayaran Online'));
    }

    private function creator(): User
    {
        $user = $this->makeStore()->owner;
        $user->forceFill(['phone' => '081234567890'])->save();

        return $user;
    }

    private function paidPlan(): Plan
    {
        return Plan::where('price_monthly', '>', 0)->orderBy('price_monthly')->firstOrFail();
    }

    private function directResponse(ClientRequest $request)
    {
        $payload = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);

        return Http::response([
            'Status' => 200,
            'Success' => true,
            'Message' => 'Success',
            'Data' => [
                'TransactionId' => 551122,
                'ReferenceId' => $payload['referenceId'],
                'Via' => 'qris',
                'Channel' => 'mpm',
                'PaymentNo' => '',
                'PaymentName' => 'QRIS Dynamic',
                'Total' => $payload['amount'],
                'Fee' => 1225,
                'Expired' => now()->addMinutes(5)->format('Y-m-d H:i:s'),
                'Url' => 'https://sandbox.ipaymu.com/payment/subscription',
            ],
        ]);
    }

    private function paidCallback(PlanPayment $payment): array
    {
        return [
            'buyer_email' => $payment->user->email,
            'buyer_name' => $payment->user->name,
            'buyer_phone' => $payment->user->phone,
            'fee' => '1225',
            'paid_at' => now()->format('Y-m-d H:i:s'),
            'reference_id' => $payment->reference,
            'status' => 'berhasil',
            'status_code' => '1',
            'transaction_status_code' => '1',
            'paid_off' => (string) ((int) $payment->amount - 1225),
            'trx_id' => '551122',
            'merchant' => self::VA,
            'amount' => (string) (int) $payment->amount,
            'total' => (string) (int) $payment->amount,
            'url' => route('webhooks.payments', ['provider' => 'ipaymu']),
            'is_escrow' => '0',
            'additional_info' => '[]',
        ];
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
