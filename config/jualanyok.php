<?php

return [

    'name' => 'JualanYok',

    /*
    |--------------------------------------------------------------------------
    | Money & fees
    |--------------------------------------------------------------------------
    | Defaults only. Live values are read from platform_settings so a super
    | admin can change them without a deploy; see PlatformSetting::get().
    */

    'currency' => 'IDR',

    'fees' => [
        'platform_percent' => (float) env('PLATFORM_FEE_PERCENT', 5),
        'platform_fixed' => (float) env('PLATFORM_FEE_FIXED', 0),
        'withdrawal_fee' => (float) env('WITHDRAWAL_FEE', 5000),
        'minimum_withdrawal' => (float) env('MINIMUM_WITHDRAWAL', 50000),
    ],

    /*
    | Days a sale stays in the pending bucket before it can be withdrawn. This
    | covers the refund window, so a refunded order never claws back money the
    | seller has already cashed out.
    */
    'holding_period_days' => (int) env('HOLDING_PERIOD_DAYS', 7),

    'affiliate' => [
        'default_cookie_days' => 30,
        'default_commission_percent' => 10,
        // Commission is held at least this long even if the seller releases
        // funds earlier, to survive buyer refunds.
        'hold_days' => (int) env('AFFILIATE_HOLD_DAYS', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reserved usernames
    |--------------------------------------------------------------------------
    | Anything routable at the top level, plus names that could be used to
    | impersonate the platform.
    */

    'reserved_usernames' => [
        'admin', 'administrator', 'support', 'help', 'login', 'logout', 'register',
        'signup', 'signin', 'api', 'checkout', 'cart', 'dashboard', 'settings',
        'account', 'billing', 'pricing', 'features', 'templates', 'blog', 'about',
        'contact', 'terms', 'privacy', 'refund', 'legal', 'jualanyok', 'jualan',
        'creator', 'affiliate', 'customer', 'member', 'orders', 'products', 'store',
        'stores', 'shop', 'payment', 'payments', 'pay', 'withdraw', 'balance',
        'analytics', 'assets', 'static', 'storage', 'files', 'download', 'downloads',
        'email', 'mail', 'root', 'system', 'null', 'undefined', 'www', 'app', 'me',
        'new', 'edit', 'delete', 'search', 'explore', 'discover', 'marketplace',
    ],

    'storefront' => [
        'max_blocks_preview' => 60,
        'autosave_debounce_ms' => 900,
    ],

    'uploads' => [
        'image_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'image_max_kb' => 4096,
        'file_mimes' => ['pdf', 'zip', 'epub', 'mp3', 'mp4', 'psd', 'ai', 'xlsx', 'docx', 'pptx'],
        'file_max_kb' => 204800, // 200 MB
    ],

    'demo' => [
        // Guards the seeded demo credentials and the payment simulator from
        // ever being reachable in production.
        'enabled' => (bool) env('DEMO_MODE', true),
    ],
];
