<?php

return [
    'default' => env('SHIPPING_PROVIDER', 'manual'),

    'quote_ttl_minutes' => (int) env('SHIPPING_QUOTE_TTL_MINUTES', 30),
    'complaint_window_days' => (int) env('SHIPPING_COMPLAINT_WINDOW_DAYS', 2),
    'auto_complete_days' => (int) env('SHIPPING_AUTO_COMPLETE_DAYS', 2),

    /*
     * The geocoder behind the address pin.
     *
     * Biteship's Maps API names areas but returns no coordinates and serves no
     * tiles, so the pin needs its own service. Nominatim is the default because
     * it needs no key; point this at another host to swap it.
     */
    'geocoder' => [
        'base_url' => env('GEOCODER_BASE_URL', 'https://nominatim.openstreetmap.org'),
    ],

    'providers' => [
        'biteship' => [
            'enabled' => (bool) env('BITESHIP_ENABLED', false),
            'token' => env('BITESHIP_API_TOKEN', env('BITESHIP_API_KEY')),
            'base_url' => env('BITESHIP_BASE_URL', 'https://api.biteship.com'),
            'couriers' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('BITESHIP_COURIERS', 'jne,sicepat,anteraja,jnt,ninja,tiki,pos')),
            ))),
            'webhook_header' => env('BITESHIP_WEBHOOK_HEADER', 'X-Callback-Token'),
            'webhook_secret' => env('BITESHIP_WEBHOOK_SECRET'),
            'timeout' => (int) env('BITESHIP_TIMEOUT', 15),
            'cache' => [
                'areas_minutes' => (int) env('BITESHIP_AREAS_CACHE_MINUTES', 1440),
                'rates_minutes' => (int) env('BITESHIP_RATES_CACHE_MINUTES', 10),
                'tracking_minutes' => (int) env('BITESHIP_TRACKING_CACHE_MINUTES', 5),
            ],
            'costs' => [
                'maps' => (float) env('BITESHIP_MAPS_COST', 2),
                'rates' => (float) env('BITESHIP_RATES_COST', 5),
                'tracking' => (float) env('BITESHIP_TRACKING_COST', 10),
            ],
        ],
        'manual' => [
            'enabled' => true,
            'flat_rate' => (float) env('SHIPPING_MANUAL_FLAT_RATE', 15000),
            'free_over' => (float) env('SHIPPING_MANUAL_FREE_OVER', 0),
            'estimated_days' => env('SHIPPING_MANUAL_ESTIMATED_DAYS', '2 - 5 hari'),
        ],
    ],
];
