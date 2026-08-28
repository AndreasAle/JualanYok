<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentCostRule;
use App\Support\Money;
use Illuminate\Support\Collection;

class PaymentEconomicsService
{
    /** Adds seller-side costs and a data-driven recommendation to checkout methods. */
    public function decorateMethods(array $methods, float $amount): array
    {
        $decorated = collect($methods)->map(function (array $method) use ($amount) {
            $providerPercent = (float) ($method['fee_percent'] ?? 0);
            $providerFixed = (float) ($method['fee_fixed'] ?? 0);
            $cost = $this->cost(
                (string) $method['provider'],
                (string) $method['method'],
                (string) ($method['channel'] ?? ''),
                $amount,
            );

            if ($cost['source'] === 'UNCONFIGURED') {
                $cost['percent'] = $providerPercent;
                $cost['fixed'] = $providerFixed;
                $cost['amount'] = Money::round($amount * $providerPercent / 100 + $providerFixed);
                $cost['source'] = 'PROVIDER_CATALOG';
            }

            $economicallyAvailable = $amount > 0
                && $cost['amount'] < $amount
                && ($amount - $cost['amount']) >= (float) config('marketplace.minimum_net_transaction', 1000);

            return $method + [
                // Buyer fees stay zero. These fields describe the seller's
                // settlement deduction and must never be added to QRIS total.
                'fee_percent' => 0.0,
                'fee_fixed' => 0.0,
                'processing_fee_estimate' => $cost['amount'],
                'processing_fee_percent' => $cost['percent'],
                'processing_fee_fixed' => $cost['fixed'],
                'fee_bearer' => $cost['bearer'],
                'settlement_days' => $cost['settlement_days'],
                'cost_source' => $cost['source'],
                'economically_available' => $economicallyAvailable,
                'recommended' => false,
            ];
        });

        $recommendedKey = $decorated
            ->filter(fn (array $method) => $method['fee_bearer'] === 'SELLER' && $method['economically_available'])
            ->sortBy([
                ['processing_fee_estimate', 'asc'],
                ['settlement_days', 'asc'],
            ])
            ->keys()
            ->first();

        return $decorated->map(function (array $method, int $key) use ($recommendedKey) {
            $method['recommended'] = $recommendedKey !== null && $key === $recommendedKey;

            return $method;
        })->values()->all();
    }

    /** Returns the effective estimated processing cost for one channel. */
    public function cost(string $provider, string $method, ?string $channel, float $amount): array
    {
        $channel = (string) $channel;
        $rule = PaymentCostRule::query()
            ->where('provider', $provider)
            ->where('method', $method)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->where(fn ($q) => $q->whereNull('minimum_amount')->orWhere('minimum_amount', '<=', $amount))
            ->where(fn ($q) => $q->whereNull('maximum_amount')->orWhere('maximum_amount', '>=', $amount))
            ->first();

        $fallback = config("marketplace.payment_costs.{$provider}.{$method}:{$channel}", []);
        $percent = (float) ($rule?->fee_percent ?? $fallback['percent'] ?? 0);
        $fixed = (float) ($rule?->fee_fixed ?? $fallback['fixed'] ?? 0);

        return [
            'amount' => Money::round($amount * $percent / 100 + $fixed),
            'percent' => $percent,
            'fixed' => $fixed,
            'settlement_days' => (int) ($rule?->settlement_days ?? $fallback['settlement_days'] ?? 0),
            'bearer' => strtoupper((string) ($rule?->fee_bearer ?? config('marketplace.gateway_fee_bearer', 'SELLER'))),
            'source' => $rule?->source ?? ($fallback !== [] ? 'CONFIG' : 'UNCONFIGURED'),
        ];
    }

    /** Provider-reported fee wins; estimates are only the safe fallback. */
    public function settledFee(Payment $payment, ?float $providerFee): array
    {
        if ($providerFee !== null) {
            return ['amount' => Money::round(max(0, $providerFee)), 'source' => 'PROVIDER'];
        }

        if ((float) $payment->fee > 0 && $payment->fee_source === 'PROVIDER') {
            return ['amount' => Money::round((float) $payment->fee), 'source' => 'PROVIDER'];
        }

        $cost = $this->cost(
            $payment->provider,
            (string) $payment->method,
            (string) $payment->channel,
            (float) $payment->amount,
        );

        return ['amount' => $cost['amount'], 'source' => 'ESTIMATE'];
    }

    /** Used by the production seeder and safe to rerun after a tariff change. */
    public function syncConfiguredRules(): Collection
    {
        $synced = collect();

        foreach ((array) config('marketplace.payment_costs', []) as $provider => $rules) {
            foreach ($rules as $key => $cost) {
                [$method, $channel] = array_pad(explode(':', (string) $key, 2), 2, '');

                $synced->push(PaymentCostRule::updateOrCreate(
                    compact('provider', 'method', 'channel'),
                    [
                        'fee_percent' => (float) ($cost['percent'] ?? 0),
                        'fee_fixed' => (float) ($cost['fixed'] ?? 0),
                        'settlement_days' => (int) ($cost['settlement_days'] ?? 0),
                        'fee_bearer' => strtoupper((string) config('marketplace.gateway_fee_bearer', 'SELLER')),
                        'source' => 'CONFIG',
                        'is_active' => true,
                    ],
                ));
            }
        }

        return $synced;
    }
}
