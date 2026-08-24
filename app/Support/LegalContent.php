<?php

namespace App\Support;

final class LegalContent
{
    /** @return array<int, array{slug: string, title: string, body: string, seo_description: string, is_published: bool}> */
    public static function pages(): array
    {
        return [
            [
                'slug' => 'terms',
                'title' => 'Syarat & Ketentuan',
                'body' => self::terms(),
                'seo_description' => 'Syarat dan ketentuan penggunaan platform JualanYok bagi kreator, penjual, affiliate, dan pembeli.',
                'is_published' => true,
            ],
            [
                'slug' => 'privacy',
                'title' => 'Kebijakan Privasi',
                'body' => self::privacy(),
                'seo_description' => 'Penjelasan cara JualanYok mengumpulkan, menggunakan, melindungi, dan menyimpan data pengguna.',
                'is_published' => true,
            ],
            [
                'slug' => 'refund-policy',
                'title' => 'Kebijakan Refund',
                'body' => self::refund(),
                'seo_description' => 'Ketentuan pengajuan, pemeriksaan, persetujuan, dan pengembalian dana transaksi JualanYok.',
                'is_published' => true,
            ],
        ];
    }

    public static function terms(): string
    {
        return <<<'TXT'
Berlaku sejak 24 Agustus 2026. Dengan membuat akun, membuka toko, atau melakukan transaksi melalui JualanYok, kamu menyetujui ketentuan berikut.

## Tentang layanan
JualanYok menyediakan teknologi etalase online, katalog, checkout, produk digital, kelas, jasa, affiliate, pencatatan transaksi, dan fitur pendukung lainnya. JualanYok bukan produsen seluruh produk yang ditampilkan; informasi dan pemenuhan produk menjadi tanggung jawab penjual terkait.

## Akun dan keamanan
Pengguna wajib memberikan informasi yang benar, menjaga kerahasiaan kredensial, dan segera melapor jika menemukan akses tanpa izin. Aktivitas yang dilakukan melalui akun dianggap dilakukan oleh pemilik akun sampai kami menerima laporan yang dapat diverifikasi.

## Kewajiban penjual
Penjual bertanggung jawab atas legalitas, kualitas, deskripsi, harga, stok, hak kekayaan intelektual, pengiriman, serta layanan purnajual produknya. Penjual wajib memenuhi pesanan sesuai informasi yang ditampilkan kepada pembeli.

## Produk dan aktivitas terlarang
Dilarang menjual barang atau jasa ilegal, palsu, melanggar hak cipta, mengandung penipuan, perjudian, eksploitasi, obat terlarang, senjata yang dilarang, konten dewasa yang melanggar hukum, atau aktivitas lain yang bertentangan dengan peraturan Indonesia dan kebijakan penyedia pembayaran.

## Harga, biaya, dan pembayaran
Harga dan biaya yang harus dibayar ditampilkan sebelum pembeli mengonfirmasi transaksi. Pembayaran diproses oleh penyedia payment gateway yang terhubung. Biaya platform, biaya gateway, pajak, dan potongan lain diterapkan sesuai paket atau informasi yang ditampilkan pada saat transaksi.

## Pencairan dana
Saldo penjual dapat melalui masa tahan untuk pemeriksaan pembayaran, refund, sengketa, dan pencegahan fraud. Pencairan hanya dilakukan ke rekening yang telah diverifikasi dan dapat ditunda jika terdapat pemeriksaan transaksi atau pelanggaran.

## Refund dan sengketa
Pengajuan pengembalian dana mengikuti Kebijakan Refund JualanYok dan ketentuan produk terkait. Kami dapat meminta bukti tambahan dari pembeli maupun penjual sebelum mengambil keputusan.

## Penangguhan dan penghentian
Kami dapat membatasi, menangguhkan, atau menutup akun, toko, produk, dan transaksi yang berisiko, melanggar ketentuan, atau diwajibkan oleh hukum. Bila memungkinkan, alasan dan langkah penyelesaian akan disampaikan kepada pemilik akun.

## Ketersediaan layanan
Kami berupaya menjaga layanan tetap aman dan tersedia, tetapi tidak menjamin layanan bebas gangguan setiap saat. Pemeliharaan, gangguan pihak ketiga, keadaan kahar, dan insiden keamanan dapat memengaruhi layanan.

## Perubahan ketentuan
Ketentuan dapat diperbarui untuk menyesuaikan produk, peraturan, dan keamanan. Versi terbaru selalu diterbitkan pada halaman ini dan berlaku sejak tanggal yang dicantumkan.

## Kontak
Pertanyaan mengenai ketentuan ini dapat disampaikan melalui halaman Kontak JualanYok. Gunakan email aktif dan sertakan nomor pesanan bila pertanyaan berkaitan dengan transaksi.
TXT;
    }

    public static function privacy(): string
    {
        return <<<'TXT'
Berlaku sejak 24 Agustus 2026. Kebijakan ini menjelaskan pengelolaan data ketika kamu menggunakan website, toko, checkout, dashboard, dan layanan JualanYok.

## Data yang dikumpulkan
Kami dapat mengumpulkan nama, email, nomor telepon, alamat, informasi akun, data toko dan produk, detail pesanan, komunikasi dukungan, data perangkat, catatan keamanan, serta aktivitas yang diperlukan untuk menjalankan layanan.

## Penggunaan data
Data digunakan untuk membuat dan mengamankan akun, memproses pesanan dan pembayaran, mengirim akses produk, menangani dukungan dan refund, mencegah fraud, memenuhi kewajiban hukum, serta meningkatkan kinerja layanan.

## Data pembayaran
Data sensitif instrumen pembayaran diproses oleh payment gateway. JualanYok menyimpan referensi transaksi, metode, nominal, dan status yang diperlukan untuk rekonsiliasi, ledger, dukungan, refund, dan audit; kami tidak menyimpan nomor kartu lengkap.

## Pembagian data
Data hanya dibagikan kepada penyedia yang diperlukan untuk operasional, seperti payment gateway, email, hosting, penyimpanan, analitik, dan pengiriman, atau kepada otoritas jika diwajibkan hukum. Kami tidak menjual data pribadi pengguna.

## Cookies dan analitik
Cookies digunakan untuk sesi login, keamanan, keranjang, preferensi, dan atribusi affiliate. Data kunjungan dapat diolah secara terbatas untuk statistik dan pencegahan penyalahgunaan.

## Penyimpanan dan keamanan
Data disimpan selama akun aktif atau selama dibutuhkan untuk transaksi, audit, sengketa, dan kewajiban hukum. Kami menerapkan kontrol akses, enkripsi pada data sensitif yang relevan, pencatatan aktivitas, dan pembatasan akses internal.

## Hak pengguna
Pengguna dapat meminta akses, koreksi, atau penghapusan data tertentu melalui halaman Kontak. Sebagian data transaksi tetap dapat disimpan apabila diwajibkan untuk pembukuan, keamanan, penyelesaian sengketa, atau kepatuhan hukum.

## Komunikasi
Email transaksional dikirim untuk keamanan akun, pesanan, pembayaran, dan dukungan. Komunikasi pemasaran hanya dikirim jika pengguna memberikan persetujuan dan dapat dihentikan melalui opsi berhenti berlangganan.

## Perubahan kebijakan
Perubahan kebijakan diterbitkan pada halaman ini dengan tanggal berlaku terbaru. Penggunaan layanan setelah perubahan berarti kamu telah membaca versi terbaru.

## Kontak privasi
Pertanyaan atau permintaan terkait data pribadi dapat disampaikan melalui halaman Kontak JualanYok.
TXT;
    }

    public static function refund(): string
    {
        return <<<'TXT'
Berlaku sejak 24 Agustus 2026. Kebijakan ini berlaku untuk transaksi yang diproses melalui checkout JualanYok.

## Cara mengajukan refund
Pembeli dapat mengajukan refund melalui detail pembelian di Member Area atau halaman Kontak. Sertakan nomor pesanan, alasan, kronologi, dan bukti pendukung. Pengajuan sebaiknya dilakukan paling lambat 7 hari kalender setelah pembayaran atau setelah produk diterima.

## Kondisi yang dapat dipertimbangkan
Refund dapat dipertimbangkan untuk pembayaran ganda, transaksi terbayar tetapi pesanan tidak terbentuk, produk tidak dikirim, file rusak atau tidak dapat diakses, produk berbeda secara material dari deskripsi, atau pembatalan lain yang disetujui penjual.

## Produk digital
Produk digital yang sudah berhasil diunduh atau diakses umumnya tidak dapat direfund karena perubahan pikiran. Pengecualian dapat diberikan jika file rusak, akses gagal, produk tidak sesuai deskripsi secara material, atau diwajibkan oleh hukum.

## Kelas, jasa, dan produk fisik
Refund kelas atau jasa mempertimbangkan apakah akses, jadwal, atau pekerjaan sudah dimulai. Produk fisik mengikuti kondisi barang, bukti penerimaan, dan ketentuan retur penjual. Biaya pengiriman kembali dapat menjadi tanggung jawab pembeli kecuali kesalahan berasal dari penjual.

## Pemeriksaan pengajuan
JualanYok dapat meminta bukti dari pembeli, penjual, dan penyedia pembayaran. Selama pemeriksaan, dana terkait dapat ditahan. Pengajuan yang tidak lengkap, menyesatkan, atau terindikasi penyalahgunaan dapat ditolak.

## Proses pengembalian dana
Jika disetujui, refund diproses melalui metode yang tersedia dan status pesanan diperbarui. JualanYok menargetkan pemrosesan internal paling lambat 7 hari kerja setelah persetujuan. Waktu dana diterima pembeli dapat berbeda sesuai bank, dompet digital, atau payment gateway.

## Dampak pada akses dan saldo
Refund penuh dapat mencabut akses produk digital atau kelas. Saldo penjual dan komisi affiliate untuk transaksi tersebut akan disesuaikan secara proporsional untuk mencegah pembayaran ganda.

## Biaya yang tidak dapat dikembalikan
Biaya layanan pihak ketiga atau biaya pengiriman yang sudah digunakan dapat tidak dikembalikan sepanjang diizinkan hukum dan telah diinformasikan kepada pembeli.

## Hubungi kami
Untuk bantuan refund, gunakan halaman Kontak dan cantumkan nomor pesanan JY-xxxx agar transaksi dapat ditemukan dengan cepat.
TXT;
    }
}
