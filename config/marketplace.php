<?php

return [
    // A transaction must still leave at least this amount before platform and
    // affiliate fees. Methods that would consume the entire order are hidden.
    'minimum_net_transaction' => (float) env('MARKETPLACE_MINIMUM_NET_TRANSACTION', 1000),
    /*
    | The buyer never receives a QRIS surcharge. Gateway costs are estimated
    | here for checkout guidance, then replaced by the provider's actual fee
    | when the payment settles.
    */
    'gateway_fee_bearer' => env('MARKETPLACE_GATEWAY_FEE_BEARER', 'SELLER'),

    'payment_costs' => [
        'ipaymu' => [
            'qris:mpm' => [
                // H+2 is the safe default. Fast settlement (2.5%) must be an
                // explicit seller opt-in, never an invisible platform cost.
                'percent' => (float) env('IPAYMU_QRIS_COST_PERCENT', 0.7),
                'fixed' => (float) env('IPAYMU_QRIS_COST_FIXED', 0),
                'settlement_days' => (int) env('IPAYMU_QRIS_SETTLEMENT_DAYS', 2),
            ],
            'va:bca' => ['percent' => 0, 'fixed' => 4500, 'settlement_days' => 2],
            'va:bni' => ['percent' => 0, 'fixed' => 3500, 'settlement_days' => 0],
            'va:bri' => ['percent' => 0, 'fixed' => 3500, 'settlement_days' => 0],
            'va:mandiri' => ['percent' => 0, 'fixed' => 4000, 'settlement_days' => 0],
            'va:permata' => ['percent' => 0, 'fixed' => 3500, 'settlement_days' => 0],
            'ewallet:dana' => ['percent' => 3.5, 'fixed' => 0, 'settlement_days' => 3],
            'ewallet:shopeepay' => ['percent' => 3.5, 'fixed' => 0, 'settlement_days' => 3],
        ],
    ],

    'reserve' => [
        'enabled' => (bool) env('MARKETPLACE_RESERVE_ENABLED', true),
        'base_percent' => (float) env('MARKETPLACE_RESERVE_PERCENT', 2),
        'physical_percent' => (float) env('MARKETPLACE_PHYSICAL_RESERVE_PERCENT', 5),
        'new_seller_bonus_percent' => (float) env('MARKETPLACE_NEW_SELLER_RESERVE_BONUS', 2),
        'new_seller_paid_orders' => (int) env('MARKETPLACE_NEW_SELLER_ORDER_THRESHOLD', 10),
        'maximum_percent' => (float) env('MARKETPLACE_MAX_RESERVE_PERCENT', 10),
        'release_days' => (int) env('MARKETPLACE_RESERVE_RELEASE_DAYS', 30),
    ],

    'split_payment' => [
        'enabled' => (bool) env('IPAYMU_SPLIT_ENABLED', false),
        'fee_per_split' => (float) env('IPAYMU_SPLIT_FEE', 150),
        'minimum_amount' => (float) env('IPAYMU_SPLIT_MINIMUM', 500),
    ],

    'payout' => [
        'provider_cost' => (float) env('PAYOUT_PROVIDER_COST', 3000),
    ],

    'economics' => [
        'warning_margin_percent' => (float) env('ECONOMICS_WARNING_MARGIN_PERCENT', 1),
        'critical_margin_percent' => (float) env('ECONOMICS_CRITICAL_MARGIN_PERCENT', 0),
        'refund_warning_percent' => (float) env('ECONOMICS_REFUND_WARNING_PERCENT', 5),
        'biteship_daily_warning' => (float) env('ECONOMICS_BITESHIP_DAILY_WARNING', 100000),
    ],
];
