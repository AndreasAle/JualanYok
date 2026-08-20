<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Services\PlanService;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Feature keys are the contract between PlanService and the UI. A limit of
     * null means unlimited; enabled=false means the capability is absent.
     */
    public function run(): void
    {
        $plans = [
            [
                'slug' => Plan::FREE,
                'name' => 'Gratis',
                'tagline' => 'Buat mulai jualan tanpa modal.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'transaction_fee_percent' => 7.5,
                'trial_days' => 0,
                'sort_order' => 1,
                'highlights' => [
                    'Storefront dengan block dasar',
                    '10 produk, 15 block',
                    'Checkout & pembayaran lengkap',
                    'Badge JualanYok tetap tampil',
                ],
                'features' => [
                    [PlanService::PRODUCTS_LIMIT, 'Jumlah produk', 'limit', true, 10],
                    [PlanService::BLOCKS_LIMIT, 'Jumlah block', 'limit', true, 15],
                    [PlanService::PREMIUM_TEMPLATES, 'Template premium', 'boolean', false, null],
                    [PlanService::CUSTOM_DOMAIN, 'Custom domain', 'boolean', false, null],
                    [PlanService::REMOVE_BRANDING, 'Hilangkan branding', 'boolean', false, null],
                    [PlanService::PIXELS, 'Pixel & analytics', 'boolean', false, null],
                    [PlanService::WEBHOOKS, 'Webhook', 'boolean', false, null],
                    [PlanService::EMAIL_BROADCAST, 'Email broadcast', 'boolean', false, null],
                    [PlanService::AFFILIATE_TOOLS, 'Tool affiliate lengkap', 'boolean', false, null],
                    [PlanService::ADVANCED_ANALYTICS, 'Analitik lanjutan', 'boolean', false, null],
                    [PlanService::TEAM_MEMBERS, 'Anggota tim', 'limit', false, 0],
                ],
            ],
            [
                'slug' => Plan::CREATOR,
                'name' => 'Creator',
                'tagline' => 'Buat kamu yang udah rutin jualan.',
                'price_monthly' => 49000,
                'price_yearly' => 490000,
                'transaction_fee_percent' => 5,
                'trial_days' => 14,
                'sort_order' => 2,
                'highlights' => [
                    '50 produk, 40 block',
                    'Template premium',
                    'Pixel & analytics lanjutan',
                    'Fee transaksi turun jadi 5%',
                ],
                'features' => [
                    [PlanService::PRODUCTS_LIMIT, 'Jumlah produk', 'limit', true, 50],
                    [PlanService::BLOCKS_LIMIT, 'Jumlah block', 'limit', true, 40],
                    [PlanService::PREMIUM_TEMPLATES, 'Template premium', 'boolean', true, null],
                    [PlanService::CUSTOM_DOMAIN, 'Custom domain', 'boolean', false, null],
                    [PlanService::REMOVE_BRANDING, 'Hilangkan branding', 'boolean', false, null],
                    [PlanService::PIXELS, 'Pixel & analytics', 'boolean', true, null],
                    [PlanService::WEBHOOKS, 'Webhook', 'boolean', false, null],
                    [PlanService::EMAIL_BROADCAST, 'Email broadcast', 'boolean', true, null],
                    [PlanService::AFFILIATE_TOOLS, 'Tool affiliate lengkap', 'boolean', false, null],
                    [PlanService::ADVANCED_ANALYTICS, 'Analitik lanjutan', 'boolean', true, null],
                    [PlanService::TEAM_MEMBERS, 'Anggota tim', 'limit', false, 0],
                ],
            ],
            [
                'slug' => Plan::PRO,
                'name' => 'Pro',
                'tagline' => 'Buat brand yang mau tampil profesional.',
                'price_monthly' => 149000,
                'price_yearly' => 1490000,
                'transaction_fee_percent' => 3.5,
                'trial_days' => 14,
                'sort_order' => 3,
                'highlights' => [
                    'Produk & block tanpa batas',
                    'Custom domain sendiri',
                    'Branding JualanYok bisa dihilangkan',
                    'Webhook & tool affiliate lengkap',
                ],
                'features' => [
                    [PlanService::PRODUCTS_LIMIT, 'Jumlah produk', 'limit', true, null],
                    [PlanService::BLOCKS_LIMIT, 'Jumlah block', 'limit', true, null],
                    [PlanService::PREMIUM_TEMPLATES, 'Template premium', 'boolean', true, null],
                    [PlanService::CUSTOM_DOMAIN, 'Custom domain', 'boolean', true, null],
                    [PlanService::REMOVE_BRANDING, 'Hilangkan branding', 'boolean', true, null],
                    [PlanService::PIXELS, 'Pixel & analytics', 'boolean', true, null],
                    [PlanService::WEBHOOKS, 'Webhook', 'boolean', true, null],
                    [PlanService::EMAIL_BROADCAST, 'Email broadcast', 'boolean', true, null],
                    [PlanService::AFFILIATE_TOOLS, 'Tool affiliate lengkap', 'boolean', true, null],
                    [PlanService::ADVANCED_ANALYTICS, 'Analitik lanjutan', 'boolean', true, null],
                    [PlanService::TEAM_MEMBERS, 'Anggota tim', 'limit', true, 3],
                ],
            ],
            [
                'slug' => Plan::BUSINESS,
                'name' => 'Business',
                'tagline' => 'Buat tim dan volume transaksi besar.',
                'price_monthly' => 399000,
                'price_yearly' => 3990000,
                'transaction_fee_percent' => 2.5,
                'trial_days' => 14,
                'sort_order' => 4,
                'highlights' => [
                    'Semua fitur Pro',
                    'Sampai 15 anggota tim',
                    'Fee transaksi paling rendah',
                    'Prioritas dukungan',
                ],
                'features' => [
                    [PlanService::PRODUCTS_LIMIT, 'Jumlah produk', 'limit', true, null],
                    [PlanService::BLOCKS_LIMIT, 'Jumlah block', 'limit', true, null],
                    [PlanService::PREMIUM_TEMPLATES, 'Template premium', 'boolean', true, null],
                    [PlanService::CUSTOM_DOMAIN, 'Custom domain', 'boolean', true, null],
                    [PlanService::REMOVE_BRANDING, 'Hilangkan branding', 'boolean', true, null],
                    [PlanService::PIXELS, 'Pixel & analytics', 'boolean', true, null],
                    [PlanService::WEBHOOKS, 'Webhook', 'boolean', true, null],
                    [PlanService::EMAIL_BROADCAST, 'Email broadcast', 'boolean', true, null],
                    [PlanService::AFFILIATE_TOOLS, 'Tool affiliate lengkap', 'boolean', true, null],
                    [PlanService::ADVANCED_ANALYTICS, 'Analitik lanjutan', 'boolean', true, null],
                    [PlanService::TEAM_MEMBERS, 'Anggota tim', 'limit', true, 15],
                ],
            ],
        ];

        foreach ($plans as $config) {
            $plan = Plan::updateOrCreate(
                ['slug' => $config['slug']],
                collect($config)->except('features')->all(),
            );

            foreach ($config['features'] as [$key, $label, $type, $enabled, $limit]) {
                $plan->features()->updateOrCreate(
                    ['key' => $key],
                    ['label' => $label, 'value_type' => $type, 'enabled' => $enabled, 'limit' => $limit],
                );
            }
        }
    }
}
