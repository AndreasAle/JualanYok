<?php

namespace Tests\Feature;

use App\Enums\BalanceBucket;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Models\FinancialJournal;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Payments\PaymentResult;
use App\Services\CheckoutService;
use App\Services\LedgerService;
use App\Services\PaymentEconomicsService;
use App\Services\PaymentService;
use App\Services\PricingService;
use App\Services\RefundService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplaceEconomicsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_platform_commission_never_taxes_shipping(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['price' => 100000]);

        $quote = app(PricingService::class)->quote($store, [[
            'product' => $product,
            'variant' => null,
            'quantity' => 1,
            'unit_price' => 100000.0,
        ]], shipping: 50000);

        $this->assertEquals(100000, $quote['commission_base']);
        $this->assertEquals(7500, $quote['platform_fee']);
        $this->assertEquals(150000, $quote['grand_total']);
    }

    public function test_checkout_recommends_the_cheapest_natural_channel_for_each_nominal(): void
    {
        $methods = [
            ['provider' => 'ipaymu', 'method' => 'qris', 'channel' => 'mpm', 'fee_percent' => 0, 'fee_fixed' => 0],
            ['provider' => 'ipaymu', 'method' => 'va', 'channel' => 'bri', 'fee_percent' => 0, 'fee_fixed' => 0],
        ];
        $economics = app(PaymentEconomicsService::class);

        $small = collect($economics->decorateMethods($methods, 100000));
        $large = collect($economics->decorateMethods($methods, 600000));

        $this->assertSame('qris', $small->firstWhere('recommended', true)['method']);
        $this->assertSame('va', $large->firstWhere('recommended', true)['method']);
        $this->assertEquals(700, $small->firstWhere('method', 'qris')['processing_fee_estimate']);
        $this->assertEquals(3500, $large->firstWhere('method', 'va')['processing_fee_estimate']);
    }

    public function test_creator_can_see_the_complete_settlement_and_wallet_buckets(): void
    {
        [$store, $order] = $this->paidOrder(175000, 'economics-creator-transparency', 1225);
        $seller = $store->owner;

        $this->actingAs($seller)
            ->get(route('creator.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Creator/Orders/Show')
                ->where('order.gateway_fee_actual', fn ($value) => (float) $value === 1225.0)
                ->where('order.gateway_fee_bearer', 'SELLER')
                ->where('order.settlement_version', 2)
                ->has('order.reserve_amount')
                ->has('order.debt_offset')
                ->has('order.funds_release_at')
                ->where('order.payments.0.fee', fn ($value) => (float) $value === 1225.0)
                ->where('order.payments.0.fee_source', 'PROVIDER'));

        $wallet = $seller->walletOrCreate()->fresh();

        $this->actingAs($seller)
            ->get(route('creator.balance'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Creator/Balance')
                ->where('wallet.reserve', fn ($value) => (float) $value === (float) $wallet->reserve_balance)
                ->where('wallet.negative', fn ($value) => (float) $value === (float) $wallet->negative_balance));
    }

    public function test_refund_after_withdrawal_creates_recoverable_debt_instead_of_failing(): void
    {
        [$store, $order] = $this->paidOrder(200000, 'economics-debt');
        $order->update(['funds_release_at' => now()->subMinute()]);
        app(WithdrawalService::class)->releaseMaturedRevenue();

        $wallet = $store->owner->walletOrCreate()->fresh();
        $available = (float) $wallet->available_balance;
        app(LedgerService::class)->move(
            $wallet,
            BalanceBucket::Available,
            BalanceBucket::Withdrawn,
            $available,
            LedgerEntryType::Withdrawal,
            $order,
            'Simulasi payout yang sudah selesai',
            'test-economics-withdrawn',
        );

        $admin = $this->makeUser([Role::FINANCE_ADMIN]);
        $refund = app(RefundService::class)->request($order->fresh(), (float) $order->grand_total, 'Refund penuh');
        $refund = app(RefundService::class)->approve($refund, $admin)->fresh();
        $wallet = $wallet->fresh();

        $this->assertSame('COMPLETED', $refund->status);
        $this->assertGreaterThan(0, (float) $refund->seller_debt_created);
        $this->assertEquals((float) $refund->seller_debt_created, (float) $wallet->negative_balance);
        $this->assertSame([], app(LedgerService::class)->reconcile($wallet));
        $this->assertJournalsBalanced();
    }

    public function test_only_the_unclawed_order_reserve_is_released(): void
    {
        [$store, $order] = $this->paidOrder(100000, 'economics-reserve');
        $originalReserve = (float) $order->reserve_amount;
        $admin = $this->makeUser([Role::FINANCE_ADMIN]);
        $refund = app(RefundService::class)->request($order, 1000, 'Refund kecil');
        $refund = app(RefundService::class)->approve($refund, $admin)->fresh();
        $remaining = round($originalReserve - (float) $refund->reserve_clawback, 2);

        $order->update(['reserve_release_at' => now()->subMinute()]);
        $released = app(WithdrawalService::class)->releaseMaturedReserves();
        $wallet = $store->owner->walletOrCreate()->fresh();

        $this->assertSame(1, $released);
        $this->assertEquals(0, (float) $wallet->reserve_balance);
        $this->assertGreaterThanOrEqual($remaining, (float) $wallet->available_balance);
        $this->assertSame([], app(LedgerService::class)->reconcile($wallet));
    }

    public function test_manual_refund_never_changes_money_before_finance_confirms_the_transfer(): void
    {
        [$store, $order] = $this->paidOrder(150000, 'economics-manual-refund');
        $order->latestPayment->update(['provider' => 'manual_transfer']);
        $walletBefore = $store->owner->walletOrCreate()->fresh()->only([
            'pending_balance', 'available_balance', 'held_balance', 'reserve_balance', 'negative_balance',
        ]);
        $admin = $this->makeUser([Role::FINANCE_ADMIN]);

        $refund = app(RefundService::class)->request($order->fresh(), 50000, 'Barang tidak sesuai');
        $refund = app(RefundService::class)->approve($refund, $admin, 'Diterima finance')->fresh();

        $this->assertSame('APPROVED', $refund->status);
        $this->assertSame('MANUAL', $refund->execution_mode);
        $this->assertEquals(0, (float) $order->fresh()->refunded_total);
        $this->assertSame($walletBefore, $store->owner->walletOrCreate()->fresh()->only(array_keys($walletBefore)));
        $this->assertDatabaseMissing('financial_journals', [
            'event_type' => 'ORDER_REFUNDED',
            'reference_type' => $refund->getMorphClass(),
            'reference_id' => $refund->id,
        ]);

        $refund = app(RefundService::class)->completeManual(
            $refund,
            $admin,
            'IPAYMU-RF-0001',
            'Sudah dikirim dari dashboard merchant',
        )->fresh();

        $this->assertSame('COMPLETED', $refund->status);
        $this->assertSame('IPAYMU-RF-0001', $refund->transfer_reference);
        $this->assertEquals(50000, (float) $order->fresh()->refunded_total);
        $this->assertJournalsBalanced();

        // A browser retry must not claw back or journal the refund twice.
        app(RefundService::class)->completeManual($refund, $admin, 'IPAYMU-RF-0001');
        $this->assertSame(1, FinancialJournal::where('event_type', 'ORDER_REFUNDED')->count());
    }

    public function test_withdrawal_is_blocked_while_a_refund_is_open(): void
    {
        [$store, $order] = $this->paidOrder(200000, 'economics-refund-hold');
        $order->update(['funds_release_at' => now()->subMinute()]);
        app(WithdrawalService::class)->releaseMaturedRevenue();
        app(RefundService::class)->request($order->fresh(), 10000, 'Sedang diperiksa');

        $wallet = $store->owner->walletOrCreate()->fresh();
        $method = $this->makeVerifiedPayoutMethod($store->owner);

        try {
            app(WithdrawalService::class)->request($store->owner, (float) $wallet->available_balance, $method);
            $this->fail('Pencairan seharusnya ditahan saat refund masih terbuka.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('refund yang belum selesai', $exception->errors()['amount'][0]);
        }
    }

    public function test_gateway_fee_above_seller_margin_is_booked_as_platform_subsidy(): void
    {
        [, $order] = $this->paidOrder(2000, 'economics-gateway-subsidy', 2000);
        $journal = FinancialJournal::with('postings.account')
            ->where('event_type', 'ORDER_PAID')
            ->where('reference_id', $order->id)
            ->firstOrFail();

        $subsidy = (float) $journal->postings
            ->first(fn ($posting) => $posting->account->code === 'gateway_subsidy_expense')
            ?->amount;

        $this->assertEquals(150, $subsidy);
        $this->assertEquals(0, (float) $order->contribution_margin);
        $this->assertJournalsBalanced();
    }

    public function test_economics_check_passes_for_a_fully_journaled_order(): void
    {
        $this->paidOrder(125000, 'economics-check-ok');

        $this->artisan('jualanyok:economics-check')->assertSuccessful();
    }

    public function test_economics_check_blocks_payout_when_a_paid_order_has_no_journal(): void
    {
        [, $order] = $this->paidOrder(125000, 'economics-check-missing');

        FinancialJournal::query()
            ->where('reference_type', $order->getMorphClass())
            ->where('reference_id', $order->id)
            ->where('event_type', 'ORDER_PAID')
            ->delete();

        $this->artisan('jualanyok:economics-check')
            ->expectsOutputToContain("Order {$order->number} sudah lunas tetapi jurnal ORDER_PAID tidak ditemukan.")
            ->assertFailed();
    }

    /** @return array{0:Store,1:Order} */
    private function paidOrder(float $price, string $key, ?float $actualGatewayFee = null): array
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['price' => $price]);
        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Pembeli', 'email' => $key.'@example.test'],
            ['idempotency_key' => $key],
        );
        $payments = app(PaymentService::class);
        $payment = $payments->createPayment($order, 'mock', 'qris', 'qris');
        $payments->applyResult(new PaymentResult(
            status: PaymentStatus::Paid,
            reference: $payment->reference,
            amount: (float) $payment->amount,
            fee: $actualGatewayFee,
            paidAt: now(),
            eventId: 'paid-'.$key,
        ), 'mock');

        return [$store, $order->fresh()];
    }

    private function assertJournalsBalanced(): void
    {
        FinancialJournal::with('postings')->each(function (FinancialJournal $journal) {
            $debit = (float) $journal->postings->where('direction', 'DEBIT')->sum('amount');
            $credit = (float) $journal->postings->where('direction', 'CREDIT')->sum('amount');
            $this->assertEquals($debit, $credit, 'Jurnal '.$journal->id.' tidak seimbang.');
        });
    }
}
