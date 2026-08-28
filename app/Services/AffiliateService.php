<?php

namespace App\Services;

use App\Enums\BalanceBucket;
use App\Enums\CommissionStatus;
use App\Enums\LedgerEntryType;
use App\Models\AffiliateClick;
use App\Models\AffiliateLink;
use App\Models\AffiliateProgram;
use App\Models\Commission;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class AffiliateService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** Records a click and returns the attribution cookie payload. */
    public function trackClick(AffiliateLink $link, array $context = []): AffiliateClick
    {
        $cookieDays = $link->program->cookie_days ?: config('jualanyok.affiliate.default_cookie_days');

        $click = $link->clicks()->create([
            'visitor_hash' => $context['visitor_hash'] ?? null,
            'referrer' => $context['referrer'] ?? null,
            'utm' => $context['utm'] ?? null,
            'device' => $context['device'] ?? null,
            'country' => $context['country'] ?? null,
            'expires_at' => now()->addDays($cookieDays),
        ]);

        $link->increment('clicks');

        return $click;
    }

    /**
     * Resolves which affiliate (if any) earns on this order.
     *
     * Last valid click wins. Self-purchase is rejected — an affiliate buying
     * through their own link earns nothing.
     */
    public function resolveAttribution(Store $store, ?string $code, ?string $buyerEmail): array
    {
        if (! $code) {
            return [];
        }

        $link = AffiliateLink::where('code', $code)->where('is_active', true)->first();

        if (! $link) {
            return [];
        }

        $program = $link->program;

        if (! $program || ! $program->is_active || $program->store_id !== $store->id) {
            return [];
        }

        // The store owner cannot earn affiliate commission on their own store.
        if ($link->user_id === $store->user_id) {
            return [];
        }

        if ($buyerEmail && strcasecmp($link->affiliate->email, $buyerEmail) === 0) {
            return [];
        }

        $click = $link->clicks()
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        return [
            'code' => $link->code,
            'user_id' => $link->user_id,
            'link_id' => $link->id,
            'click_id' => $click?->id,
            'program' => $program,
        ];
    }

    /** Commission owed on one order line, given the resolved attribution. */
    public function commissionForLine(Product $product, float $lineTotal, array $attribution): array
    {
        if (empty($attribution['user_id']) || ! $product->affiliate_enabled) {
            return ['rate' => 0, 'amount' => 0];
        }

        $program = $this->programFor($product) ?? ($attribution['program'] ?? null);

        if (! $program || ! $program->is_active) {
            return ['rate' => 0, 'amount' => 0];
        }

        return [
            'rate' => $program->rate(),
            'amount' => $program->commissionFor($lineTotal),
        ];
    }

    /** Product-specific program if configured, otherwise the store default. */
    public function programFor(Product $product): ?AffiliateProgram
    {
        return AffiliateProgram::where('store_id', $product->store_id)
            ->where(fn ($q) => $q->where('product_id', $product->id)->orWhereNull('product_id'))
            ->orderByRaw('CASE WHEN product_id IS NULL THEN 1 ELSE 0 END')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Materialises commission rows once an order is paid. Amounts land in the
     * affiliate's pending bucket and only become withdrawable after the refund
     * window closes.
     */
    public function recordForPaidOrder(Order $order): void
    {
        if (! $order->affiliate_user_id) {
            return;
        }

        $holdDays = (int) config('jualanyok.affiliate.hold_days', 14);

        DB::transaction(function () use ($order, $holdDays) {
            foreach ($order->items as $item) {
                if ((float) $item->commission_amount <= 0) {
                    continue;
                }

                // Unique on (order_item_id, user_id) — a replayed webhook can
                // never create a second commission for the same line.
                $commission = Commission::firstOrCreate(
                    ['order_item_id' => $item->id, 'user_id' => $order->affiliate_user_id],
                    [
                        'order_id' => $order->id,
                        'store_id' => $order->store_id,
                        'affiliate_link_id' => AffiliateLink::where('code', $order->affiliate_code)->value('id'),
                        'base_amount' => $item->total,
                        'rate' => $item->commission_rate,
                        'amount' => $item->commission_amount,
                        'status' => CommissionStatus::Pending,
                        'available_at' => now()->addDays($holdDays),
                    ],
                );

                if (! $commission->wasRecentlyCreated) {
                    continue;
                }

                $wallet = $this->ledger->walletFor($commission->affiliate);

                $this->ledger->record(
                    wallet: $wallet,
                    type: LedgerEntryType::AffiliateCommission,
                    bucket: BalanceBucket::Pending,
                    amount: (float) $commission->amount,
                    reference: $commission,
                    description: 'Komisi dari pesanan '.$order->number,
                    idempotencyKey: 'commission:'.$commission->id,
                );
            }

            if ($order->affiliate_code) {
                AffiliateLink::where('code', $order->affiliate_code)->each(function (AffiliateLink $link) use ($order) {
                    $link->increment('conversions');
                    $link->increment('revenue', (float) $order->grand_total);
                });

                if ($order->affiliate_click_id) {
                    AffiliateClick::whereKey($order->affiliate_click_id)->update(['converted' => true]);
                }
            }
        });
    }

    /** Moves matured commissions from pending to available. */
    public function releaseMatured(): int
    {
        $released = 0;

        Commission::where('status', CommissionStatus::Pending)
            ->whereColumn('reversed_amount', '<', 'amount')
            ->whereNotNull('available_at')
            ->where('available_at', '<=', now())
            ->whereHas('order', function ($orders) {
                $orders->whereDoesntHave('refunds', fn ($q) => $q->whereIn('status', ['REQUESTED', 'APPROVED']))
                    ->whereDoesntHave('disputes', fn ($q) => $q->whereIn('status', ['OPEN', 'SELLER_RESPONDED', 'UNDER_REVIEW']));
            })
            ->chunkById(200, function ($commissions) use (&$released) {
                foreach ($commissions as $commission) {
                    DB::transaction(function () use ($commission, &$released) {
                        $wallet = $this->ledger->walletFor($commission->affiliate);

                        $this->ledger->move(
                            wallet: $wallet,
                            from: BalanceBucket::Pending,
                            to: BalanceBucket::Available,
                            amount: Money::round((float) $commission->amount - (float) $commission->reversed_amount),
                            type: LedgerEntryType::Release,
                            reference: $commission,
                            description: 'Komisi cair',
                            idempotencyKey: 'commission-release:'.$commission->id,
                        );

                        $commission->update([
                            'status' => CommissionStatus::Approved,
                            'approved_at' => now(),
                        ]);

                        $released++;
                    });
                }
            });

        return $released;
    }

    /** Reverses commission when the underlying order is refunded. */
    /** @return array{amount:float,debt:float} */
    public function reverseForOrder(Order $order, ?string $reason = null, float $cumulativeRatio = 1.0): array
    {
        return DB::transaction(function () use ($order, $reason, $cumulativeRatio) {
            $reversedNow = 0.0;
            $debtCreated = 0.0;

            foreach ($order->commissions as $commission) {
                if ($commission->status === CommissionStatus::Reversed) {
                    continue;
                }

                $target = Money::round((float) $commission->amount * min(1, max(0, $cumulativeRatio)));
                $amount = Money::round(max(0, $target - (float) $commission->reversed_amount));

                if ($amount <= 0) {
                    continue;
                }

                $wallet = $this->ledger->walletFor($commission->affiliate);

                $allocation = $this->ledger->clawback(
                    wallet: $wallet,
                    amount: $amount,
                    type: LedgerEntryType::CommissionReversal,
                    reference: $commission,
                    description: 'Komisi dibatalkan: pesanan '.$order->number.' direfund',
                    idempotencyKey: 'commission-reverse:'.$commission->id.':'.Money::round($target),
                    useReserve: false,
                );
                $debtCreated = Money::round($debtCreated + $allocation['debt']);

                $newReversed = Money::round((float) $commission->reversed_amount + $amount);
                $commission->update([
                    'reversed_amount' => $newReversed,
                    'status' => $newReversed >= (float) $commission->amount - 0.001
                        ? CommissionStatus::Reversed
                        : $commission->status,
                    'note' => $reason,
                ]);

                $reversedNow = Money::round($reversedNow + $amount);
            }

            return ['amount' => $reversedNow, 'debt' => $debtCreated];
        });
    }

    /** Creates (or reuses) a tracking link for an affiliate. */
    public function linkFor(AffiliateProgram $program, int $userId, ?Product $product = null, ?string $campaign = null, ?string $subId = null): AffiliateLink
    {
        return AffiliateLink::firstOrCreate(
            [
                'affiliate_program_id' => $program->id,
                'user_id' => $userId,
                'product_id' => $product?->id,
                'campaign' => $campaign,
                'sub_id' => $subId,
            ],
            ['code' => AffiliateLink::generateCode()],
        );
    }

    public function summaryFor(int $userId): array
    {
        $commissions = Commission::where('user_id', $userId)
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $links = AffiliateLink::where('user_id', $userId)->get();

        return [
            'clicks' => (int) $links->sum('clicks'),
            'conversions' => (int) $links->sum('conversions'),
            'revenue' => Money::round((float) $links->sum('revenue')),
            'pending' => Money::round((float) ($commissions[CommissionStatus::Pending->value]->total ?? 0)),
            'approved' => Money::round((float) ($commissions[CommissionStatus::Approved->value]->total ?? 0)),
            'paid' => Money::round((float) ($commissions[CommissionStatus::Paid->value]->total ?? 0)),
            'conversion_rate' => $links->sum('clicks') > 0
                ? round($links->sum('conversions') / $links->sum('clicks') * 100, 2)
                : 0.0,
        ];
    }
}
