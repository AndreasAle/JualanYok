<?php

namespace Tests\Feature;

use App\Enums\PlanPaymentStatus;
use App\Models\Plan;
use App\Models\PlanPayment;
use App\Models\Role;
use App\Models\User;
use App\Services\PlanPaymentService;
use App\Services\PlanService;
use App\Support\Qris;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Manual QRIS billing for plans.
 *
 * With no callback from the wallet, the exact rupiah amount is the only link
 * between a transfer and a subscriber. Everything here exists to make sure that
 * link cannot be ambiguous, replayed, or forged.
 */
class PlanPaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A fabricated static QRIS: correct EMVCo shape and a real checksum, but
     * the merchant details belong to nobody. A live merchant code must never
     * sit in the repository.
     */
    private const TEST_STATIC = '00020101021126620013ID.CONTOH.WWW011800000000000000000002120000000000000303UMI51440014ID.CO.QRIS.WWW0215ID00000000000000303UMI5204737253033605802ID5911Toko Contoh6007Jakarta6105123456304F4A5';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        config([
            'payments.providers.ipaymu.enabled' => false,
            'payments.qris.enabled' => true,
            'payments.qris.static_payload' => self::TEST_STATIC,
            'payments.qris.window_minutes' => 30,
        ]);
    }

    private function creator(): User
    {
        return $this->makeStore()->owner;
    }

    private function admin(): User
    {
        return $this->makeUser([Role::SUPER_ADMIN]);
    }

    private function paidPlan(): Plan
    {
        return Plan::where('price_monthly', '>', 0)->orderBy('price_monthly')->firstOrFail();
    }

    private function service(): PlanPaymentService
    {
        return app(PlanPaymentService::class);
    }

    public function test_the_generated_code_is_a_dynamic_qris_carrying_the_exact_amount(): void
    {
        $payment = $this->service()->open($this->creator(), $this->paidPlan());

        $this->assertTrue(Qris::looksValid($payment->qris_payload), 'Checksum must verify.');

        // Tag 01 = 12 marks a single-use dynamic code, tag 54 carries the amount.
        $this->assertStringContainsString('010212', $payment->qris_payload);
        $this->assertStringContainsString(
            '54'.str_pad((string) strlen((string) $payment->amount), 2, '0', STR_PAD_LEFT).$payment->amount,
            $payment->qris_payload,
        );
    }

    public function test_the_amount_is_the_plan_price_plus_an_identifying_suffix(): void
    {
        $plan = $this->paidPlan();
        $payment = $this->service()->open($this->creator(), $plan);

        $this->assertSame((int) $plan->price_monthly, (int) $payment->base_amount);
        $this->assertGreaterThanOrEqual(1, $payment->unique_suffix);
        $this->assertLessThanOrEqual(999, $payment->unique_suffix);
        $this->assertSame((int) $payment->base_amount + $payment->unique_suffix, (int) $payment->amount);
    }

    public function test_two_open_payments_can_never_share_an_amount(): void
    {
        $plan = $this->paidPlan();

        $amounts = [];

        for ($i = 0; $i < 25; $i++) {
            $amounts[] = (int) $this->service()->open($this->creator(), $plan)->amount;
        }

        $this->assertSame(
            count($amounts),
            count(array_unique($amounts)),
            'An amount shared by two payers would make an incoming transfer ambiguous.',
        );
    }

    public function test_the_database_refuses_a_duplicate_open_amount(): void
    {
        $payment = $this->service()->open($this->creator(), $this->paidPlan());

        // Bypasses the service entirely — the guarantee has to live in the schema.
        $this->expectException(UniqueConstraintViolationException::class);

        PlanPayment::create([
            'reference' => PlanPayment::generateReference(),
            'user_id' => $this->creator()->id,
            'plan_id' => $payment->plan_id,
            'billing_interval' => 'monthly',
            'base_amount' => $payment->base_amount,
            'unique_suffix' => $payment->unique_suffix,
            'amount' => $payment->amount,
            'claimable_amount' => $payment->amount,
            'status' => PlanPaymentStatus::Pending,
            'qris_payload' => $payment->qris_payload,
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    public function test_a_settled_amount_is_released_for_reuse(): void
    {
        $plan = $this->paidPlan();
        $first = $this->service()->open($this->creator(), $plan);

        $this->service()->confirm($first);
        $this->service()->approve($first->fresh(), $this->admin());

        $this->assertNull($first->fresh()->claimable_amount, 'A closed payment must not hold its amount.');

        // The same amount can now legitimately be issued again.
        PlanPayment::create([
            'reference' => PlanPayment::generateReference(),
            'user_id' => $this->creator()->id,
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'base_amount' => $first->base_amount,
            'unique_suffix' => $first->unique_suffix,
            'amount' => $first->amount,
            'claimable_amount' => $first->amount,
            'status' => PlanPaymentStatus::Pending,
            'qris_payload' => $first->qris_payload,
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->assertSame(2, PlanPayment::where('amount', $first->amount)->count());
    }

    public function test_opening_a_new_payment_releases_the_users_previous_one(): void
    {
        $user = $this->creator();
        $plan = $this->paidPlan();

        $first = $this->service()->open($user, $plan);
        $second = $this->service()->open($user, $plan);

        $this->assertSame(PlanPaymentStatus::Expired, $first->fresh()->status);
        $this->assertNull($first->fresh()->claimable_amount);
        $this->assertSame(PlanPaymentStatus::Pending, $second->status);
    }

    public function test_approval_activates_the_plan(): void
    {
        $user = $this->creator();
        $plan = $this->paidPlan();

        $this->assertNotSame($plan->slug, app(PlanService::class)->planFor($user)->slug);

        $payment = $this->service()->open($user, $plan);
        $this->service()->confirm($payment);
        $approved = $this->service()->approve($payment->fresh(), $this->admin());

        $this->assertSame(PlanPaymentStatus::Paid, $approved->status);
        $this->assertNotNull($approved->subscription_id);
        $this->assertSame($plan->slug, $user->fresh()->activeSubscription()->plan->slug);
    }

    public function test_approving_twice_does_not_create_a_second_subscription(): void
    {
        $user = $this->creator();
        $payment = $this->service()->open($user, $this->paidPlan());
        $admin = $this->admin();

        $this->service()->confirm($payment);
        $this->service()->approve($payment->fresh(), $admin);

        $subscriptionCount = $user->subscriptions()->count();

        $this->service()->approve($payment->fresh(), $admin);

        $this->assertSame($subscriptionCount, $user->fresh()->subscriptions()->count());
    }

    public function test_an_expired_payment_cannot_be_confirmed_or_approved(): void
    {
        $payment = $this->service()->open($this->creator(), $this->paidPlan());

        $payment->update(['expires_at' => now()->subMinute()]);

        try {
            $this->service()->confirm($payment->fresh());
            $this->fail('An expired window must not accept a confirmation.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(PlanPaymentStatus::Expired, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->claimable_amount);
    }

    public function test_a_payment_the_payer_says_they_sent_is_not_auto_expired(): void
    {
        $payment = $this->service()->open($this->creator(), $this->paidPlan());
        $this->service()->confirm($payment);

        $payment->update(['expires_at' => now()->subHour()]);

        $this->service()->expireLapsed();

        $this->assertSame(
            PlanPaymentStatus::AwaitingReview,
            $payment->fresh()->status,
            'Money may genuinely be in transit; hiding it from admins would lose it.',
        );
    }

    public function test_a_rejected_payment_leaves_the_plan_untouched(): void
    {
        $user = $this->creator();
        $before = app(PlanService::class)->planFor($user)->slug;

        $payment = $this->service()->open($user, $this->paidPlan());
        $this->service()->confirm($payment);
        $this->service()->reject($payment->fresh(), $this->admin(), 'Dana tidak masuk.');

        $this->assertSame(PlanPaymentStatus::Rejected, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->claimable_amount);
        $this->assertSame($before, app(PlanService::class)->planFor($user->fresh())->slug);
    }

    public function test_a_creator_cannot_open_someone_elses_payment(): void
    {
        $payment = $this->service()->open($this->creator(), $this->paidPlan());
        $stranger = $this->creator();

        $this->actingAs($stranger)
            ->get("/dashboard/langganan/bayar/{$payment->reference}")
            ->assertForbidden();

        $this->actingAs($stranger)
            ->post("/dashboard/langganan/bayar/{$payment->reference}/konfirmasi")
            ->assertForbidden();
    }

    public function test_a_creator_cannot_approve_their_own_payment(): void
    {
        $user = $this->creator();
        $payment = $this->service()->open($user, $this->paidPlan());

        $this->actingAs($user)
            ->post("/admin/pembayaran-langganan/{$payment->reference}/setujui")
            ->assertForbidden();

        $this->assertSame(PlanPaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_the_whole_flow_works_through_http(): void
    {
        $user = $this->creator();
        $plan = $this->paidPlan();

        $this->actingAs($user)
            ->post('/dashboard/langganan/bayar', ['plan' => $plan->slug, 'interval' => 'monthly'])
            ->assertRedirect();

        $payment = PlanPayment::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)
            ->get("/dashboard/langganan/bayar/{$payment->reference}")
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('payment.amount', (int) $payment->amount)
                    ->where('payment.status', PlanPaymentStatus::Pending->value)
                    // The QR is drawn locally and embedded, not fetched remotely.
                    ->where('payment.qr_svg', fn ($uri) => str_starts_with($uri, 'data:image/svg+xml;base64,')),
            );

        $this->actingAs($user)
            ->post("/dashboard/langganan/bayar/{$payment->reference}/konfirmasi", ['note' => 'Sudah transfer'])
            ->assertRedirect();

        $this->assertSame(PlanPaymentStatus::AwaitingReview, $payment->fresh()->status);

        $this->actingAs($this->admin())
            ->post("/admin/pembayaran-langganan/{$payment->reference}/setujui")
            ->assertRedirect();

        $this->assertSame(PlanPaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame($plan->slug, $user->fresh()->activeSubscription()->plan->slug);
    }

    public function test_the_admin_queue_renders_and_finds_a_payment_by_amount(): void
    {
        $payment = $this->service()->open($this->creator(), $this->paidPlan());
        $this->service()->confirm($payment);

        $this->actingAs($this->admin())
            ->get('/admin/pembayaran-langganan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('counts.awaiting', 1));

        // Searching by the exact amount is how an admin works from a wallet notification.
        $this->actingAs($this->admin())
            ->get('/admin/pembayaran-langganan?status=all&q='.$payment->amount)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('payments.data.0.reference', $payment->reference));
    }

    public function test_the_subscription_page_offers_the_qris_route(): void
    {
        $user = $this->creator();

        $this->actingAs($user)
            ->get('/dashboard/langganan')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('billing.enabled', true)
                    ->where('billing.provider', 'qris'),
            );
    }

    public function test_a_paid_plan_cannot_be_granted_through_the_instant_endpoint(): void
    {
        $user = $this->creator();
        $plan = $this->paidPlan();

        // The pre-QRIS endpoint would have activated the plan for free.
        $this->actingAs($user)
            ->from('/dashboard/langganan')
            ->post('/dashboard/langganan', ['plan' => $plan->slug, 'interval' => 'monthly'])
            ->assertSessionHasErrors('plan');

        $this->assertNull($user->fresh()->activeSubscription());
    }

    public function test_billing_is_refused_when_no_merchant_code_is_configured(): void
    {
        config(['payments.qris.static_payload' => '']);

        $this->expectException(ValidationException::class);

        $this->service()->open($this->creator(), $this->paidPlan());
    }

    public function test_a_corrupt_merchant_code_is_rejected_rather_than_used(): void
    {
        // Right shape, wrong checksum — must not produce a payable QR.
        config(['payments.qris.static_payload' => substr(self::TEST_STATIC, 0, -4).'0000']);

        $this->assertFalse($this->service()->enabled());

        $this->expectException(ValidationException::class);

        $this->service()->open($this->creator(), $this->paidPlan());
    }
}
