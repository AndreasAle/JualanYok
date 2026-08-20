<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use App\Models\PlatformSetting;
use App\Models\ProductCategory;
use App\Models\StaticPage;
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

        $pages = [
            [
                'slug' => 'terms',
                'title' => 'Syarat & Ketentuan',
                'body' => $this->terms(),
            ],
            [
                'slug' => 'privacy',
                'title' => 'Kebijakan Privasi',
                'body' => $this->privacy(),
            ],
            [
                'slug' => 'refund-policy',
                'title' => 'Kebijakan Refund',
                'body' => $this->refund(),
            ],
        ];

        foreach ($pages as $page) {
            StaticPage::updateOrCreate(['slug' => $page['slug']], $page);
        }
    }

    private function terms(): string
    {
        return <<<'TXT'
Dokumen ini adalah contoh isi untuk keperluan pengembangan. Ganti dengan naskah hukum yang ditinjau sebelum dipakai di produksi.

## Penggunaan layanan
JualanYok menyediakan platform untuk membuat storefront dan menjual produk. Kamu bertanggung jawab atas produk yang kamu jual dan kepatuhannya terhadap hukum yang berlaku di Indonesia.

## Akun
Kamu wajib menjaga kerahasiaan akunmu. Semua aktivitas yang terjadi lewat akunmu menjadi tanggung jawabmu.

## Produk yang dilarang
Produk ilegal, hasil pelanggaran hak cipta, konten dewasa, senjata, obat terlarang, dan produk yang melanggar peraturan tidak boleh dijual di platform ini.

## Biaya
JualanYok memotong biaya transaksi sesuai paket langganan yang aktif. Rincian potongan selalu tampil di halaman pesanan.

## Pencairan dana
Dana penjualan ditahan selama masa refund sebelum bisa dicairkan. Pencairan diproses ke rekening yang sudah diverifikasi.

## Penangguhan
Kami dapat menangguhkan akun atau toko yang melanggar ketentuan ini, dengan alasan yang kami sampaikan.
TXT;
    }

    private function privacy(): string
    {
        return <<<'TXT'
Dokumen ini adalah contoh isi untuk keperluan pengembangan. Ganti dengan naskah yang ditinjau sebelum dipakai di produksi.

## Data yang kami kumpulkan
Kami menyimpan data akun (nama, email, nomor telepon), data toko, data transaksi, dan data analitik kunjungan.

## Analitik
Pengunjung toko dihitung memakai hash harian dari alamat IP dan user agent. Hash ini berganti tiap hari dan tidak dipakai untuk melacak seseorang lintas hari atau lintas toko.

## Data pembayaran
Nomor rekening pencairan disimpan dalam bentuk terenkripsi. Kami tidak menyimpan data kartu kredit — itu ditangani penyedia pembayaran.

## Marketing
Kami hanya mengirim email marketing kepada orang yang memberikan persetujuan. Tautan berhenti berlangganan tersedia di setiap email.

## Hak kamu
Kamu bisa meminta salinan atau penghapusan datamu lewat halaman bantuan.
TXT;
    }

    private function refund(): string
    {
        return <<<'TXT'
Dokumen ini adalah contoh isi untuk keperluan pengembangan.

## Pengajuan refund
Pembeli dapat mengajukan refund dari halaman pesanan di Member Area, dengan menyertakan alasan.

## Peninjauan
Pengajuan ditinjau oleh tim finance JualanYok bersama penjual. Kami memutuskan berdasarkan bukti dan kebijakan penjual.

## Efek refund
Jika refund disetujui, saldo penjual disesuaikan secara proporsional dan komisi affiliate untuk pesanan tersebut dibatalkan. Untuk refund penuh, akses produk digital dicabut.

## Produk digital
Produk digital yang sudah diunduh umumnya tidak dapat direfund, kecuali file rusak atau tidak sesuai deskripsi.

## Masa tahan dana
Dana penjualan ditahan beberapa hari sebelum bisa dicairkan, tepatnya agar refund tetap bisa diproses tanpa membebani penjual.
TXT;
    }
}
