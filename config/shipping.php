<?php

return [
    'default' => env('SHIPPING_PROVIDER', 'manual'),

    'quote_ttl_minutes' => (int) env('SHIPPING_QUOTE_TTL_MINUTES', 30),
    'complaint_window_days' => (int) env('SHIPPING_COMPLAINT_WINDOW_DAYS', 2),
    'auto_complete_days' => (int) env('SHIPPING_AUTO_COMPLETE_DAYS', 2),

    'providers' => [
        'biteship' => [
            'enabled' => (bool) env('BITESHIP_ENABLED', false),
            'token' => env('BITESHIP_API_TOKEN', env('BITESHIP_API_KEY')),
            'base_url' => env('BITESHIP_BASE_URL', 'https://api.biteship.com/v1'),
            'couriers' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('BITESHIP_COURIERS', 'jne,sicepat,anteraja,jnt,ninja,tiki,pos')),
            ))),
            'webhook_header' => env('BITESHIP_WEBHOOK_HEADER', 'X-Callback-Token'),
            'webhook_secret' => env('BITESHIP_WEBHOOK_SECRET'),
            'timeout' => (int) env('BITESHIP_TIMEOUT', 15),
        ],
        'manual' => [
            'enabled' => true,
            'flat_rate' => (float) env('SHIPPING_MANUAL_FLAT_RATE', 15000),
            'free_over' => (float) env('SHIPPING_MANUAL_FREE_OVER', 0),
            'estimated_days' => env('SHIPPING_MANUAL_ESTIMATED_DAYS', '2 - 5 hari'),
        ],
    ],
];
