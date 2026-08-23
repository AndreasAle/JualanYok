<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default provider
    |--------------------------------------------------------------------------
    | The mock provider is the default so the whole checkout → ledger flow is
    | runnable with zero external credentials. Point this at midtrans once the
    | production keys are in the environment.
    */

    'default' => env('PAYMENT_PROVIDER', 'mock'),

    'expiry_hours' => (int) env('PAYMENT_EXPIRY_HOURS', 24),

    /*
     * Manual QRIS billing for SaaS plans — not a checkout provider, so it sits
     * outside `providers`.
     *
     * The merchant's static QRIS string is a business identifier, so it lives in
     * the environment and never in the repository. Each payment turns it into a
     * single-use dynamic code with the amount locked in. There is no callback
     * from the wallet: an admin matches the incoming amount and approves.
     * See PlanPaymentService.
     */
    'qris' => [
        'enabled' => (bool) env('QRIS_ENABLED', false),
        'static_payload' => env('QRIS_STATIC_PAYLOAD'),
        'window_minutes' => (int) env('QRIS_WINDOW_MINUTES', 30),

        // Charged to the buyer on top of the order, mirroring what the wallet
        // provider deducts from the merchant. Zero means the platform absorbs it.
        'fee_percent' => (float) env('QRIS_FEE_PERCENT', 0.7),
        'fee_fixed' => (float) env('QRIS_FEE_FIXED', 0),
    ],

    'providers' => [

        'mock' => [
            'enabled' => (bool) env('PAYMENT_MOCK_ENABLED', true),
            'secret' => env('PAYMENT_MOCK_SECRET', 'mock-webhook-secret'),
        ],

        'manual_transfer' => [
            'enabled' => (bool) env('PAYMENT_MANUAL_ENABLED', true),
        ],

        /*
         * QRIS for product checkout. Shares the merchant code configured under
         * the top-level `qris` key, and is only selectable once that code is set.
         */
        'qris' => [
            'enabled' => (bool) env('QRIS_CHECKOUT_ENABLED', false),
        ],

        'midtrans' => [
            'enabled' => (bool) env('MIDTRANS_ENABLED', false),
            'server_key' => env('MIDTRANS_SERVER_KEY'),
            'client_key' => env('MIDTRANS_CLIENT_KEY'),
            'production' => (bool) env('MIDTRANS_PRODUCTION', false),
        ],

        'xendit' => [
            'enabled' => (bool) env('XENDIT_ENABLED', false),
            'secret_key' => env('XENDIT_SECRET_KEY'),
            'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
        ],
    ],
];
