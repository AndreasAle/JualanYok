<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\FinancialJournal;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PlanPayment;
use App\Models\Refund;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Balanced, append-only general ledger for platform-level money. */
class MarketplaceLedgerService
{
    private const ACCOUNTS = [
        'gateway_clearing' => ['Dana di Gateway', 'ASSET'],
        'seller_receivable' => ['Piutang kepada Seller', 'ASSET'],
        'affiliate_receivable' => ['Piutang kepada Affiliate', 'ASSET'],
        'seller_payable' => ['Utang kepada Seller', 'LIABILITY'],
        'affiliate_payable' => ['Utang kepada Affiliate', 'LIABILITY'],
        'shipping_payable' => ['Utang Ongkir', 'LIABILITY'],
        'tax_payable' => ['Utang Pajak', 'LIABILITY'],
        'platform_fee_revenue' => ['Pendapatan Komisi Platform', 'REVENUE'],
        'subscription_revenue' => ['Pendapatan Langganan', 'REVENUE'],
        'refund_leakage_expense' => ['Beban Refund dan Chargeback', 'EXPENSE'],
        'gateway_subsidy_expense' => ['Subsidi Biaya Gateway', 'EXPENSE'],
        'shipping_variance_expense' => ['Selisih Ongkir', 'EXPENSE'],
        'shipping_variance_revenue' => ['Pendapatan Selisih Ongkir', 'REVENUE'],
        'split_payment_expense' => ['Biaya Split Payment', 'EXPENSE'],
        'payout_expense' => ['Biaya Pencairan', 'EXPENSE'],
        'withdrawal_fee_revenue' => ['Pendapatan Biaya Pencairan', 'REVENUE'],
    ];

    public function recordSale(Order $order, Payment $payment): FinancialJournal
    {
        $gatewayFee = (float) $order->gateway_fee_actual;
        $gatewayNet = Money::round((float) $order->grand_total - $gatewayFee);
        $shippingPayable = $order->shipping_provider === 'biteship' ? (float) $order->shipping_total : 0.0;
        $sellerGross = Money::round(
            (float) $order->commission_base
            + ($order->shipping_provider === 'biteship' ? 0 : (float) $order->shipping_total)
            - (float) $order->platform_fee
            - (float) $order->affiliate_commission
        );
        $gatewayChargedToSeller = Money::round(max(0, $sellerGross - (float) $order->seller_net));
        $gatewaySubsidy = Money::round(max(0, $gatewayFee - $gatewayChargedToSeller));

        return $this->journal(
            eventType: 'ORDER_PAID',
            reference: $order,
            idempotencyKey: 'marketplace-order-paid:'.$order->id,
            description: 'Pembayaran pesanan '.$order->number,
            lines: [
                ['gateway_clearing', 'DEBIT', $gatewayNet],
                ['gateway_subsidy_expense', 'DEBIT', $gatewaySubsidy, $order->store_id],
                ['seller_payable', 'CREDIT', Money::round((float) $order->seller_net - (float) $order->debt_offset), $order->store_id, $order->store->user_id],
                ['seller_receivable', 'CREDIT', (float) $order->debt_offset, $order->store_id, $order->store->user_id],
                ['affiliate_payable', 'CREDIT', (float) $order->affiliate_commission, $order->store_id, $order->affiliate_user_id],
                ['shipping_payable', 'CREDIT', $shippingPayable, $order->store_id],
                ['tax_payable', 'CREDIT', (float) $order->tax_total, $order->store_id],
                ['platform_fee_revenue', 'CREDIT', (float) $order->platform_fee, $order->store_id],
            ],
            meta: [
                'order_number' => $order->number,
                'payment_id' => $payment->id,
                'gateway_fee' => $gatewayFee,
                'gateway_subsidy' => $gatewaySubsidy,
                'gateway_fee_bearer' => $order->gateway_fee_bearer,
                'commission_base' => (float) $order->commission_base,
                'reserve_amount' => (float) $order->reserve_amount,
            ],
        );
    }

    public function recordRefund(Refund $refund): FinancialJournal
    {
        $amount = (float) $refund->amount;
        $sellerClawback = min($amount, Money::round(max(0, (float) $refund->seller_clawback)));
        $affiliateClawback = min(
            Money::round(max(0, (float) $refund->affiliate_clawback)),
            Money::round(max(0, $amount - $sellerClawback)),
        );
        $platformFeeReversal = min(
            Money::round(max(0, (float) $refund->platform_fee_reversal)),
            Money::round(max(0, $amount - $sellerClawback - $affiliateClawback)),
        );
        $shippingReversal = min(
            Money::round(max(0, (float) $refund->shipping_reversal)),
            Money::round(max(0, $amount - $sellerClawback - $affiliateClawback - $platformFeeReversal)),
        );
        $taxReversal = min(
            Money::round(max(0, (float) $refund->tax_reversal)),
            Money::round(max(0, $amount - $sellerClawback - $affiliateClawback - $platformFeeReversal - $shippingReversal)),
        );
        $sellerDebt = min($sellerClawback, Money::round(max(0, (float) $refund->seller_debt_created)));
        $affiliateDebt = min($affiliateClawback, Money::round(max(0, (float) $refund->affiliate_debt_created)));
        $platformLeakage = Money::round(max(
            0,
            $amount - $sellerClawback - $affiliateClawback - $platformFeeReversal - $shippingReversal - $taxReversal,
        ));
        $order = $refund->order;

        return $this->journal(
            eventType: 'ORDER_REFUNDED',
            reference: $refund,
            idempotencyKey: 'marketplace-refund:'.$refund->id,
            description: 'Refund pesanan '.$order->number,
            lines: [
                ['seller_payable', 'DEBIT', Money::round($sellerClawback - $sellerDebt), $order->store_id, $order->store->user_id],
                ['seller_receivable', 'DEBIT', $sellerDebt, $order->store_id, $order->store->user_id],
                ['affiliate_payable', 'DEBIT', Money::round($affiliateClawback - $affiliateDebt), $order->store_id, $order->affiliate_user_id],
                ['affiliate_receivable', 'DEBIT', $affiliateDebt, $order->store_id, $order->affiliate_user_id],
                ['platform_fee_revenue', 'DEBIT', $platformFeeReversal, $order->store_id],
                ['shipping_payable', 'DEBIT', $shippingReversal, $order->store_id],
                ['tax_payable', 'DEBIT', $taxReversal, $order->store_id],
                ['refund_leakage_expense', 'DEBIT', $platformLeakage, $order->store_id],
                ['gateway_clearing', 'CREDIT', $amount],
            ],
            meta: [
                'order_number' => $order->number,
                'reason' => $refund->reason,
                'seller_debt_created' => $sellerDebt,
                'affiliate_debt_created' => $affiliateDebt,
                'platform_fee_reversal' => $platformFeeReversal,
                'shipping_reversal' => $shippingReversal,
                'tax_reversal' => $taxReversal,
            ],
        );
    }

    /** Books recurring SaaS revenue independently from marketplace GMV. */
    public function recordSubscription(PlanPayment $payment): FinancialJournal
    {
        $revenue = Money::round((float) $payment->base_amount);
        $gatewayFee = Money::round(min($revenue, max(0, (float) $payment->gateway_fee)));
        $gatewayNet = Money::round($revenue - $gatewayFee);

        return $this->journal(
            eventType: 'SUBSCRIPTION_PAID',
            reference: $payment,
            idempotencyKey: 'marketplace-subscription-paid:'.$payment->id,
            description: 'Langganan '.$payment->reference,
            lines: [
                ['gateway_clearing', 'DEBIT', $gatewayNet, null, $payment->user_id],
                ['gateway_subsidy_expense', 'DEBIT', $gatewayFee, null, $payment->user_id],
                ['subscription_revenue', 'CREDIT', $revenue, null, $payment->user_id],
            ],
            meta: [
                'plan_id' => $payment->plan_id,
                'billing_interval' => $payment->billing_interval,
                'provider' => $payment->provider,
                'gateway_fee' => $gatewayFee,
            ],
        );
    }

    public function recordShippingVariance(Order $order, Model $reference, float $varianceDelta, string $key): ?FinancialJournal
    {
        $varianceDelta = Money::round($varianceDelta);
        if (Money::equals($varianceDelta, 0)) {
            return null;
        }

        $lines = $varianceDelta > 0
            ? [
                ['shipping_payable', 'DEBIT', $varianceDelta, $order->store_id],
                ['shipping_variance_revenue', 'CREDIT', $varianceDelta, $order->store_id],
            ]
            : [
                ['shipping_variance_expense', 'DEBIT', abs($varianceDelta), $order->store_id],
                ['shipping_payable', 'CREDIT', abs($varianceDelta), $order->store_id],
            ];

        return $this->journal(
            'SHIPPING_COST_ADJUSTED',
            $reference,
            $key,
            'Penyesuaian ongkir pesanan '.$order->number,
            $lines,
            ['order_number' => $order->number, 'variance_delta' => $varianceDelta],
        );
    }

    public function recordPayout(Model $withdrawal, float $amount, float $userFee, float $providerCost): FinancialJournal
    {
        $amount = Money::round($amount);
        $userFee = Money::round(max(0, $userFee));
        $providerCost = Money::round(max(0, $providerCost));
        $expense = Money::round(max(0, $providerCost - $userFee));
        $revenue = Money::round(max(0, $userFee - $providerCost));
        $cashOut = Money::round($amount - $userFee + $providerCost);

        return $this->journal(
            'WITHDRAWAL_PAID',
            $withdrawal,
            'marketplace-withdrawal-paid:'.$withdrawal->getKey(),
            'Pencairan '.$withdrawal->number,
            [
                ['seller_payable', 'DEBIT', $amount, null, $withdrawal->user_id],
                ['payout_expense', 'DEBIT', $expense, null, $withdrawal->user_id],
                ['gateway_clearing', 'CREDIT', $cashOut],
                ['withdrawal_fee_revenue', 'CREDIT', $revenue],
            ],
            ['user_fee' => $userFee, 'provider_cost' => $providerCost],
        );
    }

    /** @param array<int, array{0:string,1:string,2:float,3?:int|null,4?:int|null}> $lines */
    public function journal(
        string $eventType,
        Model $reference,
        string $idempotencyKey,
        string $description,
        array $lines,
        array $meta = [],
    ): FinancialJournal {
        return DB::transaction(function () use ($eventType, $reference, $idempotencyKey, $description, $lines, $meta) {
            $existing = FinancialJournal::where('idempotency_key', $idempotencyKey)->first();

            if ($existing) {
                return $existing;
            }

            $lines = array_values(array_filter($lines, fn (array $line) => Money::round((float) $line[2]) > 0));

            if (count($lines) < 2) {
                throw new RuntimeException('Jurnal wajib memiliki minimal dua posting bernilai positif.');
            }

            $debits = Money::round(collect($lines)->where(1, 'DEBIT')->sum(2));
            $credits = Money::round(collect($lines)->where(1, 'CREDIT')->sum(2));

            if (! Money::equals($debits, $credits)) {
                throw new RuntimeException("Jurnal tidak seimbang: debit {$debits}, kredit {$credits}.");
            }

            $journal = new FinancialJournal([
                'event_type' => $eventType,
                'idempotency_key' => $idempotencyKey,
                'currency' => 'IDR',
                'description' => $description,
                'meta' => $meta ?: null,
                'posted_at' => now(),
            ]);
            $journal->reference()->associate($reference);
            $journal->save();

            foreach ($lines as $line) {
                [$code, $direction, $amount] = $line;
                $storeId = $line[3] ?? null;
                $userId = $line[4] ?? null;
                $account = $this->account($code);
                $journal->postings()->create([
                    'financial_account_id' => $account->id,
                    'direction' => $direction,
                    'amount' => Money::round((float) $amount),
                    'store_id' => $storeId ?? null,
                    'user_id' => $userId ?? null,
                    'created_at' => now(),
                ]);
            }

            return $journal->load('postings.account');
        });
    }

    public function account(string $code): FinancialAccount
    {
        [$name, $type] = self::ACCOUNTS[$code] ?? throw new RuntimeException("Akun keuangan {$code} tidak dikenal.");

        return FinancialAccount::firstOrCreate(['code' => $code], compact('name', 'type'));
    }

    public function ensureAccounts(): void
    {
        foreach (array_keys(self::ACCOUNTS) as $code) {
            $this->account($code);
        }
    }
}
