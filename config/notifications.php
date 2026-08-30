<?php

return [
    'poll_seconds' => 45,
    'retention_days' => 90,

    /*
     * Every business notification is kept in-app. Email is the escalation
     * channel: essential categories are immediate and locked, while routine
     * categories can be delivered immediately, as a daily digest, or muted.
     */
    'categories' => [
        'orders' => [
            'label' => 'Pesanan',
            'description' => 'Pesanan baru, pembayaran pesanan, dan pemenuhan.',
            'email_default' => 'immediate',
            'email_locked' => false,
        ],
        'payments' => [
            'label' => 'Pembayaran',
            'description' => 'Pembayaran yang membutuhkan perhatian.',
            'email_default' => 'immediate',
            'email_locked' => true,
        ],
        'shipping' => [
            'label' => 'Pengiriman',
            'description' => 'Penjemputan, resi, keterlambatan, dan gangguan kurir.',
            'email_default' => 'immediate',
            'email_locked' => false,
        ],
        'inventory' => [
            'label' => 'Stok',
            'description' => 'Peringatan stok rendah dan stok habis.',
            'email_default' => 'daily',
            'email_locked' => false,
        ],
        'refunds' => [
            'label' => 'Refund & komplain',
            'description' => 'Pengajuan, keputusan, dan tenggat respons.',
            'email_default' => 'immediate',
            'email_locked' => true,
        ],
        'finance' => [
            'label' => 'Saldo & penarikan',
            'description' => 'Rekening pencairan, penarikan, reserve, dan saldo.',
            'email_default' => 'immediate',
            'email_locked' => true,
        ],
        'marketplace' => [
            'label' => 'Marketplace',
            'description' => 'Moderasi, distribusi, dan kurasi produk.',
            'email_default' => 'daily',
            'email_locked' => false,
        ],
        'subscription' => [
            'label' => 'Langganan',
            'description' => 'Aktivasi, pembayaran gagal, dan masa aktif paket.',
            'email_default' => 'immediate',
            'email_locked' => true,
        ],
        'security' => [
            'label' => 'Keamanan',
            'description' => 'Perubahan akun dan aktivitas keamanan penting.',
            'email_default' => 'immediate',
            'email_locked' => true,
        ],
        'system' => [
            'label' => 'Sistem',
            'description' => 'Gangguan provider, pemeliharaan, dan status platform.',
            'email_default' => 'daily',
            'email_locked' => false,
        ],
        'growth' => [
            'label' => 'Tips & perkembangan',
            'description' => 'Milestone, tips, dan kabar fitur baru.',
            'email_default' => 'off',
            'email_locked' => false,
        ],
    ],
];
