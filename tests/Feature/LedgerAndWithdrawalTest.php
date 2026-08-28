<?php

namespace Tests\Feature;

use App\Enums\BalanceBucket;
use App\Enums\LedgerEntryType;
use App\Enums\WithdrawalStatus;
use App\Models\LedgerEntry;
use App\Models\Role;
use App\Services\LedgerService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LedgerAndWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_ledger_entries_are_immutable(): void
    {
        $user = $this->makeUser();
        $wallet = $user->walletOrCreate();

        $entry = app(LedgerService::class)->record(
            $wallet,
            LedgerEntryType::Adjustment,
            BalanceBucket::Available,
            50000,
        );

        $this->expectException(RuntimeException::class);
        $entry->update(['amount' => 999999]);
    }

    public function test_ledger_entries_cannot_be_deleted(): void
    {
        $user = $this->makeUser();
        $wallet = $user->walletOrCreate();

        $entry = app(LedgerService::class)->record(
            $wallet,
            LedgerEntryType::Adjustment,
            BalanceBucket::Available,
            50000,
        );

        $this->expectException(RuntimeException::class);
        $entry->delete();
    }

    public function test_a_bucket_can_never_go_negative(): void
    {
        $user = $this->makeUser();
        $wallet = $user->walletOrCreate();
        $ledger = app(LedgerService::class);

        $ledger->record($wallet, LedgerEntryType::Adjustment, BalanceBucket::Available, 10000);

        $this->expectException(RuntimeException::class);
        $ledger->record($wallet, LedgerEntryType::Withdrawal, BalanceBucket::Available, -20000);
    }

    public function test_idempotency_key_makes_a_repeated_credit_a_no_op(): void
    {
        $user = $this->makeUser();
        $wallet = $user->walletOrCreate();
        $ledger = app(LedgerService::class);

        foreach (range(1, 3) as $ignored) {
            $ledger->record(
                $wallet,
                LedgerEntryType::SellerRevenue,
                BalanceBucket::Available,
                75000,
                idempotencyKey: 'once-only',
            );
        }

        $this->assertSame(1, LedgerEntry::where('idempotency_key', 'once-only')->count());
        $this->assertEquals(75000, (float) $wallet->fresh()->available_balance);
    }

    public function test_requesting_a_withdrawal_holds_the_funds(): void
    {
        $user = $this->makeUser([Role::CREATOR]);
        $wallet = $user->walletOrCreate();
        $method = $this->makeVerifiedPayoutMethod($user);

        app(LedgerService::class)->record(
            $wallet, LedgerEntryType::SellerRevenue, BalanceBucket::Available, 500000,
        );

        $withdrawal = app(WithdrawalService::class)->request($user, 200000, $method);

        $wallet->refresh();

        $this->assertSame(WithdrawalStatus::Requested, $withdrawal->status);
        $this->assertEquals(300000, (float) $wallet->available_balance);
        $this->assertEquals(200000, (float) $wallet->held_balance);
        $this->assertEquals(195000, (float) $withdrawal->net_amount); // minus 5.000 fee
    }

    public function test_creator_can_withdraw_the_exact_available_balance(): void
    {
        $user = $this->makeUser([Role::CREATOR]);
        $wallet = $user->walletOrCreate();
        $method = $this->makeVerifiedPayoutMethod($user);

        app(LedgerService::class)->record(
            $wallet, LedgerEntryType::SellerRevenue, BalanceBucket::Available, 200000,
        );

        $withdrawal = app(WithdrawalService::class)->request($user, 200000, $method);

        $this->assertEquals(0, (float) $wallet->fresh()->available_balance);
        $this->assertEquals(200000, (float) $wallet->fresh()->held_balance);
        $this->assertEquals(195000, (float) $withdrawal->net_amount);
    }

    public function test_double_withdrawal_of_the_same_balance_is_impossible(): void
    {
        $user = $this->makeUser([Role::CREATOR]);
        $wallet = $user->walletOrCreate();
        $method = $this->makeVerifiedPayoutMethod($user);

        app(LedgerService::class)->record(
            $wallet, LedgerEntryType::SellerRevenue, BalanceBucket::Available, 100000,
        );

        $service = app(WithdrawalService::class);
        $service->request($user, 90000, $method);

        // The first request already moved the money into `held`.
        $this->expectException(ValidationException::class);
        $service->request($user, 90000, $method);
    }

    public function test_withdrawal_below_the_minimum_is_rejected(): void
    {
        $user = $this->makeUser([Role::CREATOR]);
        $wallet = $user->walletOrCreate();
        $method = $this->makeVerifiedPayoutMethod($user);

        app(LedgerService::class)->record(
            $wallet, LedgerEntryType::SellerRevenue, BalanceBucket::Available, 100000,
        );

        $this->expectException(ValidationException::class);
        app(WithdrawalService::class)->request($user, 1000, $method);
    }

    public function test_unverified_payout_account_cannot_be_used(): void
    {
        $user = $this->makeUser([Role::CREATOR]);
        $wallet = $user->walletOrCreate();

        $method = $this->makeVerifiedPayoutMethod($user);
        $method->update(['status' => 'unverified']);

        app(LedgerService::class)->record(
            $wallet, LedgerEntryType::SellerRevenue, BalanceBucket::Available, 500000,
        );

        $this->expectException(ValidationException::class);
        app(WithdrawalService::class)->request($user, 100000, $method->fresh());
    }

    public function test_rejecting_a_withdrawal_returns_the_held_funds(): void
    {
        $user = $this->makeUser([Role::CREATOR]);
        $admin = $this->makeUser([Role::FINANCE_ADMIN]);
        $wallet = $user->walletOrCreate();
        $method = $this->makeVerifiedPayoutMethod($user);

        app(LedgerService::class)->record(
            $wallet, LedgerEntryType::SellerRevenue, BalanceBucket::Available, 300000,
        );

        $service = app(WithdrawalService::class);
        $withdrawal = $service->request($user, 200000, $method);

        $service->reverse($withdrawal, WithdrawalStatus::Rejected, $admin, 'Data rekening tidak cocok.');

        $wallet->refresh();

        $this->assertEquals(300000, (float) $wallet->available_balance);
        $this->assertEquals(0, (float) $wallet->held_balance);
        $this->assertSame(WithdrawalStatus::Rejected, $withdrawal->fresh()->status);
    }

    public function test_paying_a_withdrawal_moves_funds_out_of_held(): void
    {
        $user = $this->makeUser([Role::CREATOR]);
        $admin = $this->makeUser([Role::FINANCE_ADMIN]);
        $wallet = $user->walletOrCreate();
        $method = $this->makeVerifiedPayoutMethod($user);

        app(LedgerService::class)->record(
            $wallet, LedgerEntryType::SellerRevenue, BalanceBucket::Available, 300000,
        );

        $service = app(WithdrawalService::class);
        $withdrawal = $service->request($user, 200000, $method);
        $service->approve($withdrawal, $admin);
        $service->markPaid($withdrawal->fresh(), $admin, 'TRX-123');

        $wallet->refresh();

        $this->assertEquals(0, (float) $wallet->held_balance);
        $this->assertEquals(200000, (float) $wallet->withdrawn_balance);
        $this->assertSame(WithdrawalStatus::Paid, $withdrawal->fresh()->status);
        $this->assertSame([], app(LedgerService::class)->reconcile($wallet));
    }

    public function test_paying_the_same_withdrawal_twice_is_a_no_op(): void
    {
        $user = $this->makeUser([Role::CREATOR]);
        $admin = $this->makeUser([Role::FINANCE_ADMIN]);
        $wallet = $user->walletOrCreate();
        $method = $this->makeVerifiedPayoutMethod($user);

        app(LedgerService::class)->record(
            $wallet, LedgerEntryType::SellerRevenue, BalanceBucket::Available, 300000,
        );

        $service = app(WithdrawalService::class);
        $withdrawal = $service->request($user, 200000, $method);
        $service->approve($withdrawal, $admin);

        $service->markPaid($withdrawal->fresh(), $admin, 'TRX-1');
        $service->markPaid($withdrawal->fresh(), $admin, 'TRX-1');

        $this->assertEquals(200000, (float) $wallet->fresh()->withdrawn_balance);
    }

    public function test_owner_can_cancel_an_untouched_request_but_not_an_approved_one(): void
    {
        $user = $this->makeUser([Role::CREATOR]);
        $admin = $this->makeUser([Role::FINANCE_ADMIN]);
        $wallet = $user->walletOrCreate();
        $method = $this->makeVerifiedPayoutMethod($user);

        app(LedgerService::class)->record(
            $wallet, LedgerEntryType::SellerRevenue, BalanceBucket::Available, 500000,
        );

        $service = app(WithdrawalService::class);

        $first = $service->request($user, 100000, $method);
        $service->cancelByOwner($first, $user);
        $this->assertSame(WithdrawalStatus::Cancelled, $first->fresh()->status);

        $second = $service->request($user, 100000, $method);
        $service->approve($second, $admin);

        $this->expectException(ValidationException::class);
        $service->cancelByOwner($second->fresh(), $user);
    }

    public function test_a_user_cannot_cancel_someone_elses_withdrawal(): void
    {
        $owner = $this->makeUser([Role::CREATOR]);
        $stranger = $this->makeUser([Role::CREATOR]);
        $wallet = $owner->walletOrCreate();
        $method = $this->makeVerifiedPayoutMethod($owner);

        app(LedgerService::class)->record(
            $wallet, LedgerEntryType::SellerRevenue, BalanceBucket::Available, 500000,
        );

        $withdrawal = app(WithdrawalService::class)->request($owner, 100000, $method);

        $this->expectException(HttpException::class);
        app(WithdrawalService::class)->cancelByOwner($withdrawal, $stranger);
    }

    public function test_wallet_totals_always_reconcile_with_the_ledger(): void
    {
        $user = $this->makeUser([Role::CREATOR]);
        $wallet = $user->walletOrCreate();
        $ledger = app(LedgerService::class);

        $ledger->record($wallet, LedgerEntryType::SellerRevenue, BalanceBucket::Pending, 250000);
        $ledger->move($wallet, BalanceBucket::Pending, BalanceBucket::Available, 100000, LedgerEntryType::Release);
        $ledger->record($wallet, LedgerEntryType::Refund, BalanceBucket::Pending, -50000);

        $wallet->refresh();

        $this->assertEquals(100000, (float) $wallet->pending_balance);
        $this->assertEquals(100000, (float) $wallet->available_balance);
        $this->assertSame([], $ledger->reconcile($wallet));
    }
}
