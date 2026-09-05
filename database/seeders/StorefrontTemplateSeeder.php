<?php

namespace Database\Seeders;

use App\Models\StorefrontTemplate;
use Illuminate\Database\Seeder;

/**
 * Seven storefronts a creator could open a shop with today.
 *
 * Each has a genuinely different block order, palette and voice — they are not
 * colour variants of one layout. Three things separate these from the flat
 * stacks they replace:
 *
 * 1. Rhythm. A page of identically styled sections reads as a list. Every
 *    template alternates plain content with two or three deliberately styled
 *    bands — a dark figures strip, a tinted testimonial panel, a gradient call
 *    to action — and never more, because when everything is emphasised nothing
 *    is.
 * 2. Showcase blocks. Carousels, marquees, counters, logo walls and numbered
 *    steps are what make a page look like a brand rather than a form. They
 *    existed and no template used one.
 * 3. Palettes that commit. A surface colour, badge colours and a contact colour
 *    chosen together, rather than the default violet with one hue swapped.
 *
 * Animation is used sparingly and always as `slide-up`, staggered across the
 * first items of a section. It is decoration that costs nothing when a visitor
 * has motion turned off, and it is switched off entirely below the fold.
 */
class StorefrontTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $i => $template) {
            StorefrontTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template + ['sort_order' => $i],
            );
        }
    }

    /**
     * Shorthand for a block's style tokens.
     *
     * Written out at every call site would bury the composition under
     * punctuation; the point of a template is that its shape can be read at a
     * glance.
     */
    private function style(array $tokens): array
    {
        return ['style' => $tokens];
    }

    private function templates(): array
    {
        return [
            [
                'slug' => 'creator-digital',
                'name' => 'Studio Digital',
                'tagline' => 'E-book, template, dan preset.',
                'description' => 'Produk unggulan di atas, angka pembuktian di tengah, FAQ menutup keraguan.',
                'use_case' => 'produk digital',
                'is_premium' => false,
                'theme' => [
                    'primary_color' => '#4F46E5',
                    'accent_color' => '#F59E0B',
                    'background_type' => 'gradient',
                    'background_value' => 'radial-gradient(circle at 12% 8%,#E0E7FF 0,transparent 38%),radial-gradient(circle at 88% 4%,#FEF3C7 0,transparent 34%),#FBFBFE',
                    'font_family' => 'jakarta',
                    'button_style' => 'rounded',
                    'card_style' => 'soft',
                    'product_layout' => 'grid',
                    'color_scheme' => 'light',
                    'extras' => [
                        'surface_color' => '#FFFFFF',
                        'badge_background_color' => '#EEF2FF',
                        'badge_text_color' => '#4338CA',
                        'contact_button_color' => '#4F46E5',
                        'spacing' => 'balanced',
                    ],
                ],
                'blueprint' => [
                    ['type' => 'SOCIAL_LINKS', 'content' => ['links' => []]],

                    // A quiet promise strip: the questions a first-time buyer
                    // asks before they will look at a price.
                    ['type' => 'MARQUEE', 'content' => [
                        'items' => ['Akses selamanya', 'Update gratis', 'Kirim otomatis', 'Bisa refund'],
                        'speed' => 34,
                    ]] + $this->style(['background' => 'subtle', 'padding' => 'sm', 'radius' => 'lg', 'width' => 'wide']),

                    ['type' => 'FEATURED_PRODUCTS', 'title' => 'Produk Unggulan', 'content' => ['limit' => 3]]
                        + $this->style(['animation' => 'slide-up']),

                    ['type' => 'STATS', 'content' => ['stats' => [
                        ['value' => 1200, 'label' => 'Pembeli', 'suffix' => '+'],
                        ['value' => 4.9, 'label' => 'Rata-rata ulasan'],
                        ['value' => 24, 'label' => 'Template siap pakai'],
                    ]]] + $this->style(['background' => 'dark', 'padding' => 'lg', 'radius' => 'xl', 'align' => 'center', 'width' => 'wide', 'animation' => 'slide-up']),

                    ['type' => 'TEXT', 'title' => 'Kenapa aku bikin ini', 'content' => [
                        'body' => "Aku dulu kehabisan ide konten tiap Senin pagi.\n\nSemua yang aku jual di sini lahir dari sistem yang aku pakai sendiri sampai hari ini — bukan teori, bukan hasil riset orang lain.",
                    ]] + $this->style(['width' => 'narrow']),

                    ['type' => 'STEPS', 'title' => 'Setelah kamu bayar', 'content' => ['steps' => [
                        ['title' => 'Bayar', 'description' => 'QRIS, transfer, atau e-wallet.'],
                        ['title' => 'Cek email', 'description' => 'Link download masuk otomatis, tanpa nunggu aku online.'],
                        ['title' => 'Pakai', 'description' => 'File tersimpan selamanya di akunmu.'],
                    ]]] + $this->style(['animation' => 'slide-up', 'animation_delay' => '100']),

                    ['type' => 'TESTIMONIAL', 'title' => 'Kata Mereka', 'content' => ['items' => [
                        ['name' => 'Nadia', 'role' => 'Content creator', 'text' => 'Template-nya kepakai hari itu juga. Kalender kontenku akhirnya jalan.', 'rating' => 5],
                        ['name' => 'Bagas', 'role' => 'Freelancer', 'text' => 'Padat, nggak bertele-tele. Langsung ke yang bisa dipraktikkan.', 'rating' => 5],
                    ]]] + $this->style(['background' => 'subtle', 'padding' => 'lg', 'radius' => 'xl']),

                    ['type' => 'LEAD_FORM', 'content' => [
                        'headline' => 'Satu tips tiap minggu',
                        'subtext' => 'Yang bisa langsung dipraktikkan. Berhenti kapan saja.',
                        'button_label' => 'Kirim ke emailku',
                    ]] + $this->style(['background' => 'gradient', 'padding' => 'lg', 'radius' => 'xl', 'align' => 'center', 'shadow' => 'lift']),

                    ['type' => 'FAQ', 'title' => 'Masih ragu?', 'content' => ['items' => [
                        ['question' => 'Filenya sampai berapa lama?', 'answer' => 'Langsung setelah pembayaran lunas, dikirim otomatis ke emailmu.'],
                        ['question' => 'Bisa dipakai berapa lama?', 'answer' => 'Selamanya, termasuk update berikutnya.'],
                        ['question' => 'Kalau ternyata nggak cocok?', 'answer' => 'Ajukan refund dari halaman pembelian sesuai kebijakan yang berlaku.'],
                    ]]],

                    ['type' => 'WHATSAPP_CTA', 'content' => ['label' => 'Tanya dulu sebelum beli', 'message' => 'Halo, aku mau tanya soal produkmu.']],
                ],
            ],

            [
                'slug' => 'freelancer-jasa',
                'name' => 'Studio Jasa',
                'tagline' => 'Portofolio dulu, baru harga.',
                'description' => 'Karya dan proses kerja di depan, paket jasa menyusul setelah calon klien percaya.',
                'use_case' => 'jasa & konsultasi',
                'is_premium' => false,
                'theme' => [
                    'primary_color' => '#0F172A',
                    'accent_color' => '#2563EB',
                    'background_type' => 'solid',
                    'background_value' => '#F7F8FA',
                    'font_family' => 'inter',
                    'button_style' => 'square',
                    'card_style' => 'outline',
                    'product_layout' => 'list',
                    'color_scheme' => 'light',
                    'extras' => [
                        'surface_color' => '#FFFFFF',
                        'badge_background_color' => '#EFF6FF',
                        'badge_text_color' => '#1D4ED8',
                        'contact_button_color' => '#0F172A',
                        'spacing' => 'airy',
                    ],
                ],
                'blueprint' => [
                    ['type' => 'HEADING', 'content' => ['text' => 'Brand yang tidak perlu dijelaskan dua kali', 'size' => 'lg']]
                        + $this->style(['width' => 'narrow', 'animation' => 'slide-up']),

                    ['type' => 'TEXT', 'content' => [
                        'body' => 'Desainer brand, lima tahun, kebanyakan UMKM dan startup tahap awal. Aku kerja dari brief, bukan dari selera.',
                    ]] + $this->style(['width' => 'narrow']),

                    ['type' => 'LOGO_CLOUD', 'title' => 'Pernah bekerja dengan', 'content' => ['logos' => [], 'grayscale' => true]]
                        + $this->style(['padding' => 'md', 'align' => 'center']),

                    ['type' => 'CAROUSEL', 'title' => 'Karya Terpilih', 'content' => ['slides' => [], 'aspect' => 'wide']]
                        + $this->style(['width' => 'wide', 'radius' => 'xl', 'shadow' => 'soft']),

                    ['type' => 'STEPS', 'title' => 'Cara Kerja', 'content' => ['steps' => [
                        ['title' => 'Pilih paket', 'description' => 'Bayar di sini, slot langsung terkunci.'],
                        ['title' => 'Diskusi 30 menit', 'description' => 'Kita bedah kebutuhan dan tolok ukur berhasilnya.'],
                        ['title' => 'Draft pertama', 'description' => 'Tiga hari kerja, lengkap dengan alasan tiap keputusan.'],
                        ['title' => 'Revisi & serah terima', 'description' => 'Sampai sesuai brief, file mentah ikut diserahkan.'],
                    ]]] + $this->style(['background' => 'subtle', 'padding' => 'lg', 'radius' => 'lg', 'animation' => 'slide-up']),

                    ['type' => 'PRODUCT_COLLECTION', 'title' => 'Paket Jasa', 'content' => ['product_ids' => []]],

                    ['type' => 'STATS', 'content' => ['stats' => [
                        ['value' => 68, 'label' => 'Project selesai'],
                        ['value' => 5, 'label' => 'Tahun pengalaman'],
                        ['value' => 96, 'label' => 'Klien kembali lagi', 'suffix' => '%'],
                    ]]] + $this->style(['background' => 'dark', 'padding' => 'lg', 'radius' => 'lg', 'align' => 'center', 'width' => 'wide']),

                    ['type' => 'TESTIMONIAL', 'title' => 'Kata Klien', 'content' => ['items' => [
                        ['name' => 'Sari', 'role' => 'Pemilik brand skincare', 'text' => 'Prosesnya rapi, dan tiap pilihan desain ada alasannya. Itu yang bikin beda.', 'rating' => 5],
                    ]]] + $this->style(['background' => 'outline', 'padding' => 'lg', 'radius' => 'lg']),

                    ['type' => 'FAQ', 'content' => ['items' => [
                        ['question' => 'Berapa lama pengerjaannya?', 'answer' => 'Tergantung paket, umumnya 3–10 hari kerja.'],
                        ['question' => 'Revisinya berapa kali?', 'answer' => 'Sesuai jatah paket, dan aku selalu bilang di awal kalau permintaannya di luar lingkup.'],
                    ]]],

                    ['type' => 'WHATSAPP_CTA', 'content' => ['label' => 'Diskusi project', 'message' => 'Halo, aku mau diskusi project.']],
                ],
            ],

            [
                'slug' => 'kelas-online',
                'name' => 'Kelas Online',
                'tagline' => 'Kurikulum jelas, hasil terukur.',
                'description' => 'Alasan ikut di atas, isi kelas di tengah, jaminan dan FAQ menutup.',
                'use_case' => 'kelas online',
                'is_premium' => true,
                'theme' => [
                    'primary_color' => '#0F766E',
                    'accent_color' => '#F97316',
                    'background_type' => 'gradient',
                    'background_value' => 'radial-gradient(circle at 85% 0%,#CCFBF1 0,transparent 40%),#F7FDFC',
                    'font_family' => 'manrope',
                    'button_style' => 'pill',
                    'card_style' => 'soft',
                    'product_layout' => 'list',
                    'color_scheme' => 'light',
                    'extras' => [
                        'surface_color' => '#FFFFFF',
                        'badge_background_color' => '#CCFBF1',
                        'badge_text_color' => '#0F766E',
                        'contact_button_color' => '#0F766E',
                        'spacing' => 'balanced',
                    ],
                ],
                'blueprint' => [
                    ['type' => 'HEADING', 'content' => ['text' => 'Dari nol sampai bisa bikin sendiri', 'size' => 'lg']]
                        + $this->style(['width' => 'narrow', 'animation' => 'slide-up']),

                    ['type' => 'VIDEO', 'title' => 'Lihat isinya dulu', 'content' => ['url' => '']]
                        + $this->style(['radius' => 'xl', 'shadow' => 'lift', 'width' => 'wide']),

                    ['type' => 'STATS', 'content' => ['stats' => [
                        ['value' => 340, 'label' => 'Alumni', 'suffix' => '+'],
                        ['value' => 12, 'label' => 'Modul video'],
                        ['value' => 4.8, 'label' => 'Rata-rata penilaian'],
                    ]]] + $this->style(['padding' => 'md', 'align' => 'center', 'animation' => 'slide-up', 'animation_delay' => '100']),

                    ['type' => 'COUNTDOWN', 'content' => ['label' => 'Harga early bird berakhir dalam']]
                        + $this->style(['background' => 'accent', 'padding' => 'md', 'radius' => 'lg', 'align' => 'center']),

                    ['type' => 'FEATURED_PRODUCTS', 'title' => 'Pilih Kelasmu', 'content' => ['limit' => 2]],

                    ['type' => 'STEPS', 'title' => 'Alur Belajar', 'content' => ['steps' => [
                        ['title' => 'Dasar', 'description' => 'Alat, istilah, dan kebiasaan yang bikin hasilnya konsisten.'],
                        ['title' => 'Praktik', 'description' => 'Bikin karya pertama di modul dua, bukan di akhir kelas.'],
                        ['title' => 'Perbaiki', 'description' => 'Bedah karyamu sendiri pakai kriteria yang sama tiap kali.'],
                        ['title' => 'Rilis', 'description' => 'Publikasikan, lalu ulangi dengan siklus yang sudah kamu punya.'],
                    ], 'layout' => 'horizontal']]
                        + $this->style(['background' => 'subtle', 'padding' => 'lg', 'radius' => 'xl', 'width' => 'wide']),

                    ['type' => 'TEXT', 'title' => 'Yang kamu dapat', 'content' => [
                        'body' => "Akses seumur hidup ke semua materi, termasuk update berikutnya.\nGrup diskusi bareng peserta lain.\nSertifikat setelah kelas selesai.",
                    ]] + $this->style(['width' => 'narrow']),

                    ['type' => 'TESTIMONIAL', 'title' => 'Kata Alumni', 'content' => ['items' => [
                        ['name' => 'Dimas', 'role' => 'Alumni batch 3', 'text' => 'Runut. Praktik dari modul pertama, jadi nggak sempat mager.', 'rating' => 5],
                        ['name' => 'Rani', 'role' => 'Alumni batch 5', 'text' => 'Bedah karyanya yang paling ngena. Baru sadar salahnya di mana.', 'rating' => 5],
                    ]]] + $this->style(['background' => 'subtle', 'padding' => 'lg', 'radius' => 'xl']),

                    ['type' => 'LEAD_FORM', 'content' => [
                        'headline' => 'Belum yakin? Ambil silabusnya',
                        'subtext' => 'Isi lengkap tiap modul, gratis, tanpa perlu bayar dulu.',
                        'button_label' => 'Kirim silabus',
                    ]] + $this->style(['background' => 'gradient', 'padding' => 'lg', 'radius' => 'xl', 'align' => 'center', 'shadow' => 'lift']),

                    ['type' => 'FAQ', 'content' => ['items' => [
                        ['question' => 'Live atau rekaman?', 'answer' => 'Rekaman, bisa ditonton kapan saja dan diulang sebanyak yang kamu mau.'],
                        ['question' => 'Ada garansi?', 'answer' => 'Ada. Kalau dalam 7 hari terasa nggak cocok, ajukan refund dari halaman pembelian.'],
                    ]]],
                ],
            ],

            [
                'slug' => 'fashion-fisik',
                'name' => 'Butik',
                'tagline' => 'Katalog rapi, ongkir otomatis.',
                'description' => 'Lookbook di depan, katalog menyusul, aturan ukur dan pengiriman dijawab sebelum ditanya.',
                'use_case' => 'produk fisik',
                'is_premium' => true,
                'theme' => [
                    'primary_color' => '#9F1239',
                    'accent_color' => '#D6A75C',
                    'background_type' => 'solid',
                    'background_value' => '#FAF7F4',
                    'font_family' => 'playfair',
                    'button_style' => 'square',
                    'card_style' => 'flat',
                    'product_layout' => 'grid',
                    'color_scheme' => 'light',
                    'extras' => [
                        'surface_color' => '#FFFFFF',
                        'badge_background_color' => '#FDF2F5',
                        'badge_text_color' => '#9F1239',
                        'contact_button_color' => '#9F1239',
                        'spacing' => 'airy',
                    ],
                ],
                'blueprint' => [
                    ['type' => 'CAROUSEL', 'title' => 'Koleksi Terbaru', 'content' => ['slides' => [], 'aspect' => 'portrait', 'autoplay' => true]]
                        + $this->style(['width' => 'wide', 'radius' => 'lg', 'shadow' => 'soft']),

                    ['type' => 'MARQUEE', 'content' => [
                        'items' => ['Kirim hari ini sebelum jam 3', 'Tukar ukuran 7 hari', 'Bahan dipilih sendiri', 'Dijahit satuan'],
                        'speed' => 30,
                        'separator' => '·',
                    ]] + $this->style(['background' => 'primary', 'padding' => 'sm', 'width' => 'full']),

                    ['type' => 'FEATURED_PRODUCTS', 'title' => 'Paling Dicari', 'content' => ['limit' => 6]]
                        + $this->style(['animation' => 'slide-up']),

                    ['type' => 'GALLERY', 'title' => 'Dipakai Pelanggan', 'content' => ['images' => []]]
                        + $this->style(['padding' => 'md']),

                    ['type' => 'STEPS', 'title' => 'Cara Belanja', 'content' => ['steps' => [
                        ['title' => 'Pilih ukuran', 'description' => 'Ada tabel ukur di tiap produk — cek lingkar dada dan panjang.'],
                        ['title' => 'Checkout', 'description' => 'Ongkir dihitung otomatis dari alamatmu.'],
                        ['title' => 'Dikirim', 'description' => 'Nomor resi masuk ke email begitu paket diambil kurir.'],
                    ]]] + $this->style(['background' => 'subtle', 'padding' => 'lg', 'radius' => 'md']),

                    ['type' => 'TESTIMONIAL', 'title' => 'Kata Pembeli', 'content' => ['items' => [
                        ['name' => 'Putri', 'role' => 'Jakarta', 'text' => 'Jahitannya rapi, dan ukurannya persis seperti tabelnya.', 'rating' => 5],
                    ]]] + $this->style(['background' => 'outline', 'padding' => 'md', 'radius' => 'md']),

                    ['type' => 'FAQ', 'content' => ['items' => [
                        ['question' => 'Kalau ukurannya nggak pas?', 'answer' => 'Bisa tukar dalam 7 hari, selama label belum dilepas.'],
                        ['question' => 'Dikirim dari mana?', 'answer' => 'Alamat gudang tertera di tiap halaman produk, lengkap dengan estimasi sampainya.'],
                    ]]],

                    ['type' => 'WHATSAPP_CTA', 'content' => ['label' => 'Tanya stok & ukuran', 'message' => 'Halo, aku mau tanya ukuran.']],
                ],
            ],

            [
                'slug' => 'affiliate-creator',
                'name' => 'Rekomendasi',
                'tagline' => 'Barang yang benar-benar kamu pakai.',
                'description' => 'Halaman rekomendasi yang jujur: alasan dulu, tautan belakangan.',
                'use_case' => 'affiliate',
                'is_premium' => false,
                'theme' => [
                    'primary_color' => '#111827',
                    'accent_color' => '#84CC16',
                    'background_type' => 'solid',
                    'background_value' => '#0B0C10',
                    'font_family' => 'space',
                    'button_style' => 'pill',
                    'card_style' => 'outline',
                    'product_layout' => 'list',
                    'color_scheme' => 'dark',
                    'extras' => [
                        'surface_color' => '#15171E',
                        'badge_background_color' => '#1F2937',
                        'badge_text_color' => '#A3E635',
                        'contact_button_color' => '#84CC16',
                        'spacing' => 'balanced',
                    ],
                ],
                'blueprint' => [
                    ['type' => 'SOCIAL_LINKS', 'content' => ['links' => []]],

                    ['type' => 'HEADING', 'content' => ['text' => 'Yang aku pakai tiap hari', 'size' => 'lg']]
                        + $this->style(['width' => 'narrow', 'animation' => 'slide-up']),

                    ['type' => 'TEXT', 'content' => [
                        'body' => 'Semua di halaman ini barang yang benar-benar aku pakai. Kalau aku dapat komisi dari sebuah tautan, itu aku tulis di bawahnya — bukan disembunyikan.',
                    ]] + $this->style(['width' => 'narrow']),

                    ['type' => 'MARQUEE', 'content' => [
                        'items' => ['Dipakai sendiri', 'Bukan endorse', 'Harga ikut marketplace'],
                        'speed' => 26,
                    ]] + $this->style(['background' => 'accent', 'padding' => 'sm', 'width' => 'full']),

                    ['type' => 'FEATURED_PRODUCTS', 'title' => 'Rekomendasi Bulan Ini', 'content' => ['limit' => 6]]
                        + $this->style(['animation' => 'slide-up']),

                    ['type' => 'STATS', 'content' => ['stats' => [
                        ['value' => 48, 'label' => 'Barang direview'],
                        ['value' => 3, 'label' => 'Tahun dipakai'],
                        ['value' => 0, 'label' => 'Endorse berbayar'],
                    ]]] + $this->style(['background' => 'subtle', 'padding' => 'lg', 'radius' => 'lg', 'align' => 'center']),

                    ['type' => 'FAQ', 'content' => ['items' => [
                        ['question' => 'Kamu dapat komisi?', 'answer' => 'Dari sebagian tautan, ya. Harganya buat kamu tetap sama.'],
                        ['question' => 'Kenapa harganya beda dari yang tertulis?', 'answer' => 'Harga dan stok mengikuti marketplace tujuan, dan bisa berubah kapan saja.'],
                    ]]],

                    ['type' => 'WHATSAPP_CTA', 'content' => ['label' => 'Tanya rekomendasi', 'message' => 'Halo, aku mau tanya rekomendasi.']],
                ],
            ],

            [
                'slug' => 'food-beverage',
                'name' => 'Dapur',
                'tagline' => 'Menu, jam buka, dan pesan cepat.',
                'description' => 'Foto menu di depan, cara pesan dijawab sebelum ditanya.',
                'use_case' => 'food & beverage',
                'is_premium' => false,
                'theme' => [
                    'primary_color' => '#B45309',
                    'accent_color' => '#DC2626',
                    'background_type' => 'solid',
                    'background_value' => '#FFFBF5',
                    'font_family' => 'nunito',
                    'button_style' => 'rounded',
                    'card_style' => 'soft',
                    'product_layout' => 'grid',
                    'color_scheme' => 'light',
                    'extras' => [
                        'surface_color' => '#FFFFFF',
                        'badge_background_color' => '#FEF3C7',
                        'badge_text_color' => '#B45309',
                        'contact_button_color' => '#25D366',
                        'spacing' => 'compact',
                    ],
                ],
                'blueprint' => [
                    ['type' => 'CAROUSEL', 'title' => 'Menu Hari Ini', 'content' => ['slides' => [], 'aspect' => 'square', 'autoplay' => true]]
                        + $this->style(['width' => 'wide', 'radius' => 'xl', 'shadow' => 'soft']),

                    ['type' => 'MARQUEE', 'content' => [
                        'items' => ['Dimasak setelah pesanan masuk', 'Antar area kota', 'Bisa pre-order H-1'],
                        'speed' => 28,
                    ]] + $this->style(['background' => 'primary', 'padding' => 'sm', 'width' => 'full']),

                    ['type' => 'FEATURED_PRODUCTS', 'title' => 'Paling Laris', 'content' => ['limit' => 6]]
                        + $this->style(['animation' => 'slide-up']),

                    ['type' => 'STEPS', 'title' => 'Cara Pesan', 'content' => ['steps' => [
                        ['title' => 'Pilih menu', 'description' => 'Tentukan porsi dan tanggal antar.'],
                        ['title' => 'Bayar', 'description' => 'QRIS atau transfer, langsung terkonfirmasi.'],
                        ['title' => 'Kami masak', 'description' => 'Baru dimasak setelah pesanan masuk.'],
                        ['title' => 'Diantar', 'description' => 'Sampai sesuai jam yang kamu pilih.'],
                    ], 'layout' => 'horizontal']]
                        + $this->style(['background' => 'subtle', 'padding' => 'lg', 'radius' => 'xl', 'width' => 'wide']),

                    ['type' => 'TEXT', 'title' => 'Jam Buka', 'content' => [
                        'body' => "Senin–Jumat 09.00–20.00\nSabtu & Minggu 09.00–17.00\nPesanan besar mohon H-1.",
                    ]] + $this->style(['background' => 'outline', 'padding' => 'md', 'radius' => 'lg', 'width' => 'narrow']),

                    ['type' => 'TESTIMONIAL', 'content' => ['items' => [
                        ['name' => 'Wulan', 'role' => 'Pelanggan tetap', 'text' => 'Rasanya konsisten tiap pesan, dan datangnya selalu tepat jam.', 'rating' => 5],
                    ]]] + $this->style(['padding' => 'md']),

                    ['type' => 'WHATSAPP_CTA', 'content' => ['label' => 'Pesan sekarang', 'message' => 'Halo, aku mau pesan.']]
                        + $this->style(['align' => 'center']),
                ],
            ],

            [
                'slug' => 'minimal-link',
                'name' => 'Kartu Nama',
                'tagline' => 'Satu halaman, semua tautan.',
                'description' => 'Paling ringkas: siapa kamu, ke mana orang harus pergi, dan cara menghubungimu.',
                'use_case' => 'link in bio',
                'is_premium' => false,
                'theme' => [
                    'primary_color' => '#18181B',
                    'accent_color' => '#71717A',
                    'background_type' => 'gradient',
                    'background_value' => 'linear-gradient(180deg,#FFFFFF 0%,#F4F4F5 100%)',
                    'font_family' => 'dm-sans',
                    'button_style' => 'pill',
                    'card_style' => 'outline',
                    'product_layout' => 'list',
                    'color_scheme' => 'light',
                    'extras' => [
                        'surface_color' => '#FFFFFF',
                        'badge_background_color' => '#F4F4F5',
                        'badge_text_color' => '#3F3F46',
                        'contact_button_color' => '#18181B',
                        'spacing' => 'airy',
                    ],
                ],
                'blueprint' => [
                    // Restraint is the whole design here. Nothing is tinted,
                    // nothing animates: on a page this short, one styled band
                    // would be the only thing anyone saw.
                    ['type' => 'SOCIAL_LINKS', 'content' => ['links' => []]]
                        + $this->style(['align' => 'center']),

                    ['type' => 'TEXT', 'content' => [
                        'body' => 'Satu kalimat tentang siapa kamu dan apa yang kamu kerjakan.',
                    ]] + $this->style(['align' => 'center', 'width' => 'narrow']),

                    ['type' => 'LINK_BUTTON', 'content' => ['label' => 'Portofolio', 'url' => '']]
                        + $this->style(['width' => 'narrow']),
                    ['type' => 'LINK_BUTTON', 'content' => ['label' => 'Newsletter', 'url' => '']]
                        + $this->style(['width' => 'narrow']),
                    ['type' => 'LINK_BUTTON', 'content' => ['label' => 'Kerja sama', 'url' => '']]
                        + $this->style(['width' => 'narrow']),

                    ['type' => 'FEATURED_PRODUCTS', 'title' => 'Yang Aku Jual', 'content' => ['limit' => 3]],

                    ['type' => 'WHATSAPP_CTA', 'content' => ['label' => 'Hubungi aku', 'message' => 'Halo!']]
                        + $this->style(['align' => 'center']),
                ],
            ],
        ];
    }
}
