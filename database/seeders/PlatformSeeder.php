<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use App\Models\PlatformSetting;
use App\Models\ProductCategory;
use App\Models\StaticPage;
use App\Support\LegalContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['withdrawal.minimum', 50000, 'finance'],
            ['withdrawal.fee', 5000, 'finance'],
            ['withdrawal.holding_days', 7, 'finance'],
            ['affiliate.hold_days', 14, 'finance'],
            ['tax.percent', 0, 'finance'],
            ['payments.manual_accounts', [
                ['bank' => 'BCA', 'number' => '1234567890', 'holder' => 'PT JualanYok Indonesia'],
                ['bank' => 'Mandiri', 'number' => '9876543210', 'holder' => 'PT JualanYok Indonesia'],
            ], 'finance'],
        ];

        foreach ($settings as [$key, $value, $group]) {
            PlatformSetting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }

        $accounts = [
            ['platform_revenue', 'Pendapatan Platform', 'revenue'],
            ['payment_fees', 'Biaya Payment Gateway', 'expense'],
            ['payable_sellers', 'Kewajiban ke Seller', 'liability'],
            ['payable_affiliates', 'Kewajiban ke Affiliate', 'liability'],
            ['cash', 'Kas', 'asset'],
        ];

        foreach ($accounts as [$code, $name, $type]) {
            LedgerAccount::updateOrCreate(['code' => $code], ['name' => $name, 'type' => $type]);
        }

        $categories = [
            'E-book & Panduan', 'Template & Preset', 'Kelas Online', 'Jasa & Konsultasi',
            'Fashion', 'Food & Beverage', 'Kecantikan', 'Gadget & Aksesoris',
            'Musik & Audio', 'Fotografi', 'Bisnis & Keuangan', 'Lainnya',
        ];

        foreach ($categories as $i => $name) {
            ProductCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i],
            );
        }

        foreach (LegalContent::pages() as $page) {
            StaticPage::updateOrCreate(['slug' => $page['slug']], $page);
        }
    }
}
