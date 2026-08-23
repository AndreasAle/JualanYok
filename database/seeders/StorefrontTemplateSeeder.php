<?php

namespace Database\Seeders;

use App\Models\StorefrontTemplate;
use Illuminate\Database\Seeder;

/**
 * Seven templates. Each one has a genuinely different block order and theme —
 * they are not colour variants of one layout.
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

    private function templates(): array
    {
        return [
            [
                'slug' => 'creator-digital',
                'name' => 'Creator Digital',
                'tagline' => 'Jual e-book, template, dan preset.',
                'description' => 'Produk unggulan langsung di atas, disusul bukti sosial dan FAQ.',
                'use_case' => 'produk digital',
                'is_premium' => false,
                'theme' => [
                    'primary_color' => '#6D28D9',
                    'accent_color' => '#A855F7',
                    'background_type' => 'solid',
                    'background_value' => '#FAF9FF',
                    'button_style' => 'rounded',
                    'card_style' => 'soft',
                    'product_layout' => 'grid',
                ],
                'blueprint' => [
                    ['type' => 'SOCIAL_LINKS', 'content' => ['links' => []]],
                    ['type' => 'FEATURED_PRODUCTS', 'title' => 'Produk Unggulan', 'content' => ['limit' => 3]],
                    ['type' => 'PROMO_BANNER', 'content' => [
                        'headline' => 'Diskon 20% minggu ini',
                        'subtext' => 'Pakai kode di bawah waktu checkout.',
                        'code' => 'HEMAT20',
                    ]],
                    ['type' => 'TEXT', 'title' => 'Tentang Aku', 'content' => [
                        'body' => "Aku bantu kamu bikin konten yang konsisten tanpa kehabisan ide.\nSemua produk di sini lahir dari yang aku pakai sendiri tiap hari.",
                    ]],
                    ['type' => 'TESTIMONIAL', 'title' => 'Kata Mereka', 'content' => ['items' => [
                        ['name' => 'Nadia', 'role' => 'Content creator', 'text' => 'Template-nya langsung kepakai hari itu juga.', 'rating' => 5],
                        ['name' => 'Bagas', 'role' => 'Freelancer', 'text' => 'Isinya padat, nggak bertele-tele.', 'rating' => 5],
                    ]]],
                    ['type' => 'LEAD_FORM', 'content' => [
                        'headline' => 'Dapat tips mingguan gratis',
                        'subtext' => 'Satu email per minggu, isinya yang bisa langsung dipraktikkan.',
                        'button_label' => 'Gabung Sekarang',
                    ]],
                    ['type' => 'FAQ', 'title' => 'Pertanyaan Umum', 'content' => ['items' => [
                        ['question' => 'Gimana cara dapat filenya?', 'answer' => 'Begitu pembayaran lunas, link download langsung dikirim ke emailmu.'],
                        ['question' => 'Bisa dipakai berapa lama?', 'answer' => 'Selamanya. Update berikutnya juga kamu dapat gratis.'],
                    ]]],
                    ['type' => 'WHATSAPP_CTA', 'content' => ['label' => 'Tanya-tanya dulu', 'message' => 'Halo, aku mau tanya soal produkmu.']],
                ],
            ],
            [
                'slug' => 'freelancer-jasa',
                'name' => 'Freelancer & Jasa',
                'tagline' => 'Tawarkan jasa dan buka slot konsultasi.',
                'description' => 'Dimulai dari portofolio dan proses kerja, baru paket jasa.',
                'use_case' => 'jasa & konsultasi',
                'is_premium' => false,
                'theme' => [
                    'primary_color' => '#1E293B',
                    'accent_color' => '#3B82F6',
                    'background_type' => 'solid',
                    'background_value' => '#F8FAFC',
                    'button_style' => 'square',
                    'card_style' => 'outline',
                    'product_layout' => 'list',
                ],
                'blueprint' => [
                    ['type' => 'HEADING', 'content' => ['text' => 'Bantu brand kamu tampil beda', 'size' => 'lg']],
                    ['type' => 'TEXT', 'content' => ['body' => 'Aku desainer brand dengan pengalaman 5 tahun bantu UMKM dan startup.']],
                    ['type' => 'GALLERY', 'title' => 'Portofolio', 'content' => ['images' => []]],
                    ['type' => 'PRODUCT_COLLECTION', 'title' => 'Paket Jasa', 'content' => ['product_ids' => []]],
                    ['type' => 'TEXT', 'title' => 'Cara Kerja', 'content' => [
                        'body' => "1. Kamu pilih paket dan bayar.\n2. Kita diskusi kebutuhan lewat panggilan 30 menit.\n3. Aku kirim draft pertama dalam 3 hari kerja.\n4. Revisi sampai kamu puas, sesuai jatah paket.",
                    ]],
                    ['type' => 'TESTIMONIAL', 'title' => 'Klien Sebelumnya', 'content' => ['items' => [
                        ['name' => 'Sari', 'role' => 'Pemilik brand skincare', 'text' => 'Prosesnya rapi dan hasilnya sesuai brief.', 'rating' => 5],
                    ]]],
                    ['type' => 'FAQ', 'content' => ['items' => [
                        ['question' => 'Berapa lama pengerjaannya?', 'answer' => 'Tergantung paket, biasanya 3–10 hari kerja.'],
                    ]]],
                    ['type' => 'WHATSAPP_CTA', 'content' => ['label' => 'Diskusi Project', 'message' => 'Halo, aku mau diskusi project.']],
                ],
            ],
            [
                'slug' => 'kelas-online',
                'name' => 'Kelas Online',
                'tagline' => 'Jual kelas dengan kurikulum jelas.',
                'description' => 'Urgensi di atas, kurikulum di tengah, jaminan dan FAQ di bawah.',
                'use_case' => 'kelas online',
                'is_premium' => true,
                'theme' => [
                    'primary_color' => '#047857',
                    'accent_color' => '#10B981',
                    'background_type' => 'gradient',
                    'background_value' => '#ECFDF5',
                    'button_style' => 'pill',
                    'card_style' => 'soft',
                    'product_layout' => 'list',
                ],
                'blueprint' => [
                    ['type' => 'HEADING', 'content' => ['text' => 'Dari nol sampai bisa bikin konten sendiri', 'size' => 'lg']],
                    ['type' => 'VIDEO', 'title' => 'Kenalan dulu', 'content' => ['url' => '']],
                    ['type' => 'COUNTDOWN', 'content' => ['label' => 'Harga early bird berakhir dalam']],
                    ['type' => 'FEATURED_PRODUCTS', 'title' => 'Kelas yang Tersedia', 'content' => ['limit' => 2]],
                    ['type' => 'TEXT', 'title' => 'Apa yang Kamu Dapat', 'content' => [
                        'body' => "Akses seumur hidup ke semua materi.\nGrup diskusi bareng peserta lain.\nSertifikat setelah kelas selesai.",
                    ]],
                    ['type' => 'TESTIMONIAL', 'title' => 'Alumni Bilang Apa', 'content' => ['items' => [
                        ['name' => 'Dimas', 'role' => 'Alumni batch 3', 'text' => 'Materinya runut, langsung praktik dari modul pertama.', 'rating' => 5],
                    ]]],
                    ['type' => 'LEAD_FORM', 'content' => [
                        'headline' => 'Belum yakin? Ambil silabusnya dulu',
                        'button_label' => 'Kirim Silabus',
                    ]],
                    ['type' => 'FAQ', 'content' => ['items' => [
                        ['question' => 'Kelasnya live atau rekaman?', 'answer' => 'Rekaman, jadi bisa kamu tonton kapan saja.'],
                        ['question' => 'Ada garansi?', 'answer' => 'Ada. Kalau dalam 7 hari kamu merasa nggak cocok, ajukan refund dari halaman pembelian.'],
                    ]]],
                ],
            ],
            [
                'slug' => 'fashion-fisik',
                'name' => 'Fashion & Produk Fisik',
                'tagline' => 'Katalog produk dengan varian dan stok.',
                'description' => 'Grid katalog besar dengan banner promo dan info pengiriman.',
                'use_case' => 'produk fisik',
                'is_premium' => false,
                'theme' => [
                    'primary_color' => '#DB2777',
                    'accent_color' => '#F472B6',
                    'background_type' => 'solid',
                    'background_value' => '#FFF7FB',
                    'button_style' => 'pill',
                    'card_style' => 'soft',
                    'product_layout' => 'grid',
                ],
                'blueprint' => [
                    ['type' => 'PROMO_BANNER', 'content' => [
                        'headline' => 'Gratis ongkir min. belanja 200rb',
                        'subtext' => 'Berlaku ke seluruh Indonesia.',
                    ]],
                    ['type' => 'SOCIAL_LINKS', 'content' => ['links' => []]],
                    ['type' => 'FEATURED_PRODUCTS', 'title' => 'Best Seller', 'content' => ['limit' => 4]],
                    ['type' => 'GALLERY', 'title' => 'Dipakai Mereka', 'content' => ['images' => []]],
                    ['type' => 'PRODUCT_COLLECTION', 'title' => 'Koleksi Terbaru', 'content' => ['product_ids' => []]],
                    ['type' => 'TEXT', 'title' => 'Info Pengiriman', 'content' => [
                        'body' => "Pesanan diproses maksimal 1×24 jam kerja.\nNomor resi dikirim ke emailmu begitu barang dikirim.",
                    ]],
                    ['type' => 'FAQ', 'content' => ['items' => [
                        ['question' => 'Bisa tukar ukuran?', 'answer' => 'Bisa dalam 3 hari setelah barang diterima, selama label belum dilepas.'],
                    ]]],
                    ['type' => 'WHATSAPP_CTA', 'content' => ['label' => 'Tanya Stok', 'message' => 'Halo, mau tanya stok.']],
                ],
            ],
            [
                'slug' => 'affiliate-creator',
                'name' => 'Affiliate Creator',
                'tagline' => 'Kurasi produk orang lain, dapat komisi.',
                'description' => 'Fokus ke daftar rekomendasi produk affiliate.',
                'use_case' => 'affiliate',
                'is_premium' => false,
                'theme' => [
                    'primary_color' => '#EA580C',
                    'accent_color' => '#FB923C',
                    'background_type' => 'solid',
                    'background_value' => '#FFFBF5',
                    'button_style' => 'rounded',
                    'card_style' => 'flat',
                    'product_layout' => 'grid',
                ],
                'blueprint' => [
                    ['type' => 'HEADING', 'content' => ['text' => 'Racun belanja versi aku', 'size' => 'md']],
                    ['type' => 'TEXT', 'content' => ['body' => 'Semua yang aku rekomendasiin di sini beneran aku pakai. Kalau kamu beli lewat link ini, aku dapat komisi kecil tanpa nambah harga buat kamu.']],
                    ['type' => 'SOCIAL_LINKS', 'content' => ['links' => []]],
                    ['type' => 'PRODUCT_COLLECTION', 'title' => 'Rekomendasi Minggu Ini', 'content' => ['product_ids' => []]],
                    ['type' => 'LINK_BUTTON', 'content' => ['label' => 'Lihat Wishlist Lengkap', 'url' => 'https://']],
                    ['type' => 'ARTICLE', 'title' => 'Review Terbaru', 'content' => ['title' => 'Jujur soal produk yang lagi viral', 'excerpt' => 'Aku pakai 2 minggu, ini hasilnya.']],
                    ['type' => 'LEAD_FORM', 'content' => ['headline' => 'Mau dikabarin tiap ada promo?', 'button_label' => 'Kabari Aku']],
                ],
            ],
            [
                'slug' => 'food-beverage',
                'name' => 'Food & Beverage',
                'tagline' => 'Menu, promo, dan pesan lewat WhatsApp.',
                'description' => 'Menu di atas, CTA WhatsApp menonjol, jam buka jelas.',
                'use_case' => 'F&B',
                'is_premium' => false,
                'theme' => [
                    'primary_color' => '#B45309',
                    'accent_color' => '#F59E0B',
                    'background_type' => 'solid',
                    'background_value' => '#FFFBEB',
                    'button_style' => 'rounded',
                    'card_style' => 'soft',
                    'product_layout' => 'grid',
                ],
                'blueprint' => [
                    ['type' => 'PROMO_BANNER', 'content' => ['headline' => 'Promo makan siang 11.00–14.00', 'subtext' => 'Diskon 15% semua menu utama.']],
                    ['type' => 'FEATURED_PRODUCTS', 'title' => 'Menu Favorit', 'content' => ['limit' => 4]],
                    ['type' => 'WHATSAPP_CTA', 'content' => ['label' => 'Pesan Sekarang', 'message' => 'Halo, mau pesan menu.']],
                    ['type' => 'PRODUCT_COLLECTION', 'title' => 'Menu Lengkap', 'content' => ['product_ids' => []]],
                    ['type' => 'TEXT', 'title' => 'Jam Buka', 'content' => ['body' => "Senin–Jumat: 10.00–21.00\nSabtu–Minggu: 09.00–22.00"]],
                    ['type' => 'GALLERY', 'title' => 'Suasana Tempat', 'content' => ['images' => []]],
                    ['type' => 'SOCIAL_LINKS', 'content' => ['links' => []]],
                ],
            ],
            [
                'slug' => 'minimal-link',
                'name' => 'Minimal Personal',
                'tagline' => 'Link-in-bio yang bersih dan cepat.',
                'description' => 'Hanya tombol dan sosial media, tanpa distraksi.',
                'use_case' => 'link personal',
                'is_premium' => false,
                'theme' => [
                    'primary_color' => '#111827',
                    'accent_color' => '#4B5563',
                    'background_type' => 'solid',
                    'background_value' => '#FFFFFF',
                    'button_style' => 'square',
                    'card_style' => 'outline',
                    'product_layout' => 'list',
                ],
                'blueprint' => [
                    ['type' => 'SOCIAL_LINKS', 'content' => ['links' => []]],
                    ['type' => 'LINK_BUTTON', 'content' => ['label' => 'Portofolio', 'url' => 'https://']],
                    ['type' => 'LINK_BUTTON', 'content' => ['label' => 'Newsletter', 'url' => 'https://']],
                    ['type' => 'DIVIDER', 'content' => []],
                    ['type' => 'FEATURED_PRODUCTS', 'title' => 'Yang Aku Jual', 'content' => ['limit' => 2]],
                    ['type' => 'WHATSAPP_CTA', 'content' => ['label' => 'Hubungi Aku', 'message' => 'Halo!']],
                ],
            ],
        ];
    }
}
