<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialPosting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PlanPayment;
use App\Models\ProviderApiUsage;
use App\Models\Refund;
use App\Models\Wallet;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminEconomicsController extends Controller
{
    public function index(Request $request): Response
    {
        $days = in_array((int) $request->integer('days', 30), [7, 30, 90, 365], true)
            ? (int) $request->integer('days', 30)
            : 30;
        $since = now()->subDays($days - 1)->startOfDay();

        // Historical GMV must not disappear merely because a paid order later
        // entered partial/full refund status.
        $historicalOrders = Order::query()
            ->whereIn('payment_status', ['PAID', 'PARTIALLY_REFUNDED', 'REFUNDED'])
            ->where('paid_at', '>=', $since);
        $legacyOrders = (clone $historicalOrders)->where('settlement_version', '<', 2)->count();
        $orders = (clone $historicalOrders)->where('settlement_version', '>=', 2);
        $totals = (clone $orders)->selectRaw(
            'COUNT(*) as orders, COALESCE(SUM(grand_total),0) as gmv, COALESCE(SUM(commission_base),0) as merchandise, '
            .'COALESCE(SUM(platform_fee),0) as platform_fee, COALESCE(SUM(gateway_fee_actual),0) as gateway_fee, '
            .'COALESCE(SUM(affiliate_commission),0) as affiliate_fee, COALESCE(SUM(shipping_variance),0) as shipping_variance, '
            .'COALESCE(SUM(reserve_amount),0) as reserve_created, COALESCE(SUM(contribution_margin),0) as contribution'
        )->first();

        $refunds = Refund::where('status', 'COMPLETED')->where('processed_at', '>=', $since)
            ->selectRaw('COALESCE(SUM(amount),0) amount, COALESCE(SUM(platform_loss),0) platform_loss, '
                .'COALESCE(SUM(platform_fee_reversal),0) platform_fee_reversal')
            ->first();
        $openRefunds = Refund::whereIn('status', ['REQUESTED', 'APPROVED']);
        $openRefundCount = (clone $openRefunds)->count();
        $openRefundAmount = Money::round((float) (clone $openRefunds)->sum('amount'));
        $staleRefunds = (clone $openRefunds)->where('created_at', '<=', now()->subDay())->count();

        $subscriptions = PlanPayment::query()
            ->where('status', 'PAID')
            ->where('paid_at', '>=', $since)
            ->selectRaw('COALESCE(SUM(base_amount),0) revenue, COALESCE(SUM(gateway_fee),0) gateway_cost')
            ->first();
        $biteshipCost = Money::round((float) ProviderApiUsage::query()
            ->where('provider', 'biteship')
            ->where('occurred_at', '>=', $since)
            ->sum('cost'));
        $payoutSubsidy = Money::round((float) FinancialPosting::query()
            ->join('financial_accounts', 'financial_accounts.id', '=', 'financial_postings.financial_account_id')
            ->join('financial_journals', 'financial_journals.id', '=', 'financial_postings.financial_journal_id')
            ->where('financial_accounts.code', 'payout_expense')
            ->where('financial_journals.posted_at', '>=', $since)
            ->sum('financial_postings.amount'));

        $channels = Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payments.status', 'PAID')
            ->where('payments.paid_at', '>=', $since)
            ->groupBy('payments.provider', 'payments.method', 'payments.channel')
            ->selectRaw('payments.provider, payments.method, payments.channel, COUNT(*) transactions, '
                .'COALESCE(SUM(payments.amount),0) volume, COALESCE(SUM(payments.fee),0) cost, '
                .'AVG(payments.settlement_days) settlement_days')
            ->orderByDesc('volume')
            ->get()
            ->map(fn ($row) => [
                'provider' => $row->provider,
                'method' => $row->method,
                'channel' => $row->channel,
                'transactions' => (int) $row->transactions,
                'volume' => Money::round((float) $row->volume),
                'cost' => Money::round((float) $row->cost),
                'effective_rate' => (float) $row->volume > 0
                    ? round((float) $row->cost / (float) $row->volume * 100, 2)
                    : 0,
                'settlement_days' => round((float) $row->settlement_days, 1),
            ]);

        $daily = (clone $orders)
            ->selectRaw('DATE(paid_at) date, COALESCE(SUM(grand_total),0) gmv, COALESCE(SUM(platform_fee),0) revenue, COALESCE(SUM(contribution_margin),0) contribution')
            ->groupBy('date')->orderBy('date')->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'gmv' => Money::round((float) $row->gmv),
                'revenue' => Money::round((float) $row->revenue),
                'contribution' => Money::round((float) $row->contribution),
            ]);

        $unbalanced = FinancialPosting::query()
            ->selectRaw('financial_journal_id, SUM(CASE WHEN direction = ? THEN amount ELSE -amount END) difference', ['DEBIT'])
            ->groupBy('financial_journal_id')
            ->get()
            ->filter(fn ($row) => ! Money::equals((float) $row->difference, 0))
            ->count();

        $gmv = (float) $totals->gmv;
        $subscriptionRevenue = Money::round((float) $subscriptions->revenue);
        $subscriptionGateway = Money::round((float) $subscriptions->gateway_cost);
        $contribution = Money::round(
            (float) $totals->contribution
            + $subscriptionRevenue
            - $subscriptionGateway
            - (float) $refunds->platform_fee_reversal
            - (float) $refunds->platform_loss
            - $biteshipCost
            - $payoutSubsidy
        );
        $refundRate = $gmv > 0 ? round((float) $refunds->amount / $gmv * 100, 2) : 0;
        $marginPercent = $gmv > 0 ? round($contribution / $gmv * 100, 2) : 0;
        // Includes an explicitly platform-paid fee and the rare remainder when
        // a seller's entire settlement is still smaller than the provider fee.
        $platformGatewayLeak = Money::round((float) (clone $orders)
            ->get([
                'gateway_fee_actual', 'gateway_fee_bearer', 'platform_fee',
                'shipping_variance', 'split_fee_actual', 'contribution_margin',
            ])
            ->sum(function (Order $order) {
                if ($order->gateway_fee_bearer !== 'SELLER') {
                    return (float) $order->gateway_fee_actual;
                }

                return max(0, (float) $order->platform_fee
                    + (float) $order->shipping_variance
                    - (float) $order->split_fee_actual
                    - (float) $order->contribution_margin);
            }));
        $negativeWallets = Wallet::where('negative_balance', '>', 0)->count();
        $alerts = collect([
            $unbalanced > 0 ? ['severity' => 'critical', 'message' => "{$unbalanced} jurnal tidak seimbang; hentikan payout dan rekonsiliasi."] : null,
            $marginPercent < (float) config('marketplace.economics.critical_margin_percent', 0) ? ['severity' => 'critical', 'message' => 'Contribution margin negatif pada periode ini.'] : null,
            $marginPercent >= (float) config('marketplace.economics.critical_margin_percent', 0)
                && $marginPercent < (float) config('marketplace.economics.warning_margin_percent', 1)
                ? ['severity' => 'warning', 'message' => 'Contribution margin berada di bawah target minimum.'] : null,
            $platformGatewayLeak > 0 ? ['severity' => 'warning', 'message' => 'Platform menyubsidi biaya gateway '.Money::format($platformGatewayLeak).' karena settlement seller tidak cukup atau fee memang ditanggung platform.'] : null,
            $biteshipCost > (float) config('marketplace.economics.biteship_daily_warning', 100000) * $days
                ? ['severity' => 'warning', 'message' => 'Pengeluaran API Biteship melewati batas periode.'] : null,
            $negativeWallets > 0 ? ['severity' => 'warning', 'message' => "{$negativeWallets} akun seller/affiliate memiliki saldo negatif."] : null,
            $refundRate > (float) config('marketplace.economics.refund_warning_percent', 5)
                ? ['severity' => 'warning', 'message' => "Refund rate {$refundRate}% melewati batas aman."] : null,
            $legacyOrders > 0 ? ['severity' => 'warning', 'message' => "{$legacyOrders} order lama tidak dimasukkan ke contribution margin karena belum memakai settlement v2."] : null,
            $staleRefunds > 0 ? ['severity' => 'critical', 'message' => "{$staleRefunds} refund belum selesai lebih dari 24 jam. Payout seller terkait tetap ditahan."] : null,
        ])->filter()->values();

        return Inertia::render('Admin/Economics', [
            'days' => $days,
            'stats' => [
                'orders' => (int) $totals->orders,
                'gmv' => Money::round($gmv),
                'merchandise' => Money::round((float) $totals->merchandise),
                'platform_fee' => Money::round((float) $totals->platform_fee),
                'gateway_fee' => Money::round((float) $totals->gateway_fee),
                'gateway_paid_by_platform' => $platformGatewayLeak,
                'affiliate_fee' => Money::round((float) $totals->affiliate_fee),
                'split_cost' => Money::round((float) (clone $orders)->sum('split_fee_actual')),
                'subscription_revenue' => $subscriptionRevenue,
                'subscription_gateway_cost' => $subscriptionGateway,
                'biteship_api_cost' => $biteshipCost,
                'payout_subsidy' => $payoutSubsidy,
                'shipping_variance' => Money::round((float) $totals->shipping_variance),
                'refunds' => Money::round((float) $refunds->amount),
                'refund_loss' => Money::round((float) $refunds->platform_loss + (float) $refunds->platform_fee_reversal),
                'refund_platform_fee_reversal' => Money::round((float) $refunds->platform_fee_reversal),
                'open_refunds' => $openRefundCount,
                'open_refund_amount' => $openRefundAmount,
                'contribution' => $contribution,
                'margin_percent' => $marginPercent,
                'refund_rate' => $refundRate,
                'reserve_balance' => Money::round((float) Wallet::sum('reserve_balance')),
                'negative_balance' => Money::round((float) Wallet::sum('negative_balance')),
                'negative_wallets' => $negativeWallets,
                'unbalanced_journals' => $unbalanced,
                'legacy_orders' => $legacyOrders,
            ],
            'channels' => $channels,
            'alerts' => $alerts,
            'daily' => $daily,
            'lowMarginOrders' => (clone $orders)
                ->where('contribution_margin', '<=', 0)
                ->with('store:id,name')
                ->latest('paid_at')->limit(15)->get()
                ->map(fn (Order $order) => [
                    'number' => $order->number,
                    'store' => $order->store->name,
                    'gmv' => (float) $order->grand_total,
                    'platform_fee' => (float) $order->platform_fee,
                    'gateway_fee' => (float) $order->gateway_fee_actual,
                    'margin' => (float) $order->contribution_margin,
                ]),
        ]);
    }
}
