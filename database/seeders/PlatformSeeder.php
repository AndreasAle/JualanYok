<?php

namespace Database\Seeders;

use App\Models\CampaignBanner;
use App\Models\HomepageSection;
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
            ['E-book & Panduan', 'book-open', 'Bacaan praktis dan panduan dari creator Indonesia.'],
            ['Template & Desain', 'palette', 'Template siap pakai untuk kerja dan berkarya.'],
            ['Kelas Online', 'graduation-cap', 'Belajar langsung dari praktisi dan creator.'],
            ['Jasa & Konsultasi', 'sparkles', 'Temukan ahli untuk membantu kebutuhanmu.'],
            ['Bisnis & Marketing', 'chart-no-axes-column', 'Tools dan pengetahuan untuk menumbuhkan usaha.'],
            ['Teknologi', 'code-xml', 'Produk dan pembelajaran teknologi terkurasi.'],
            ['Fashion', 'shirt', 'Produk fashion pilihan dari brand creator.'],
            ['Beauty', 'heart', 'Kurasi kecantikan dan perawatan diri.'],
            ['Event & Webinar', 'calendar-days', 'Acara, workshop, dan webinar pilihan.'],
            ['Membership', 'users', 'Akses komunitas dan konten eksklusif.'],
            ['Produk Fisik', 'package', 'Barang fisik dari creator dan brand lokal.'],
            ['Lainnya', 'boxes', 'Karya menarik lintas kategori.'],
        ];

        foreach ($categories as $i => [$name, $icon, $description]) {
            ProductCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'icon' => $icon,
                    'description' => $description,
                    'seo_title' => $name.' dari Creator Indonesia',
                    'seo_description' => $description.' Temukan dan beli dengan alur resmi JualanYok.',
                    'sort_order' => $i,
                    'is_active' => true,
                ],
            );
        }

        $homepageSections = [
            ['featured', 'Pilihan JualanYok', 'Kurasi listing yang lengkap, relevan, dan layak tayang.', 'featured'],
            ['popular', 'Sedang populer', 'Produk yang sedang banyak dipilih pembeli.', 'popular'],
            ['digital', 'Produk digital terlaris', 'Karya digital yang bisa langsung kamu gunakan.', 'digital'],
            ['learning', 'Kelas dan webinar', 'Tambah kemampuan lewat pengalaman creator.', 'learning'],
            ['services', 'Jasa dan konsultasi', 'Bekerja langsung dengan creator yang tepat.', 'services'],
            ['latest', 'Baru di JualanYok', 'Listing terbaru yang sudah lolos moderasi.', 'latest'],
            ['promo', 'Promo terbatas', 'Potongan harga nyata dari creator.', 'promo'],
            ['affiliate', 'Bisa kamu promosikan', 'Produk dengan program affiliate aktif.', 'affiliate'],
        ];

        foreach ($homepageSections as $i => [$key, $title, $subtitle, $strategy]) {
            HomepageSection::updateOrCreate(['key' => $key], [
                'title' => $title,
                'subtitle' => $subtitle,
                'strategy' => $strategy,
                'item_limit' => 8,
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        CampaignBanner::updateOrCreate(['name' => 'Creator Indonesia'], [
            'eyebrow' => 'Marketplace creator-first',
            'title' => 'Temukan karya yang dibuat dengan cerita.',
            'description' => 'Produk digital, kelas, jasa, event, dan produk pilihan langsung dari creator Indonesia.',
            'cta_label' => 'Jelajahi produk',
            'cta_url' => '/explore',
            'tone' => 'violet',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        foreach (LegalContent::pages() as $page) {
            StaticPage::updateOrCreate(['slug' => $page['slug']], $page);
        }
    }
}
