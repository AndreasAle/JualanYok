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

    'providers' => [

        'mock' => [
            'enabled' => (bool) env('PAYMENT_MOCK_ENABLED', true),
            'secret' => env('PAYMENT_MOCK_SECRET', 'mock-webhook-secret'),
        ],

        'manual_transfer' => [
            'enabled' => (bool) env('PAYMENT_MANUAL_ENABLED', true),
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
