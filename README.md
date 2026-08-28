# JualanYok

Platform creator-commerce Indonesia. Creator bikin halaman toko berbasis block,
menjual produk digital/fisik/kelas/jasa/event/membership, menerima pembayaran,
mengirim produk otomatis, menjalankan program affiliate, dan mencairkan saldo.

Alur inti:

```
Creator → Storefront → Block/Product → Checkout → Payment → Fulfillment
                                                      ↓
                                          Ledger → Balance → Withdrawal
```

---

## Stack

| Bagian | Teknologi |
| --- | --- |
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | Inertia.js 2 + React 19 + TypeScript |
| Styling | Tailwind CSS 4 (design tokens di `resources/css/app.css`) |
| Build | Vite 7 |
| Database | SQLite (default dev) / MySQL 8 |
| Auth | Session guard + Laravel Sanctum (API token siap pakai) |
| Queue | Database driver (Redis tinggal ganti env) |
| Storage | Filesystem abstraction — `local` untuk file berbayar, `public` untuk gambar, `s3` untuk produksi |
| Test | PHPUnit |

---

## Requirement

- PHP 8.2 atau lebih baru (ekstensi: `pdo_sqlite` atau `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`)
- Composer 2
- Node.js 20+ dan npm
- MySQL 8 (opsional — SQLite dipakai kalau tidak diset)

> **Versi Inertia harus sepadan.** Adapter PHP `inertiajs/inertia-laravel` v2
> berpasangan dengan npm `@inertiajs/react` v2. Kalau sisi npm dinaikkan ke v3
> tanpa menaikkan sisi PHP, halaman akan tampil **kosong total** tanpa error di
> Laravel — v3 membaca data awal dari `<script type="application/json">`,
> sedangkan v2 menuliskannya sebagai atribut `data-page` di `<div id="app">`.
> `package.json` sudah dipatok ke `^2.3` untuk mencegah ini.

---

## Instalasi

> **Windows PowerShell:** PowerShell 5.1 tidak mengenal `&&` sebagai pemisah
> perintah. Jalankan tiap baris satu per satu, atau pakai `;` sebagai pemisah.
> Contoh di bawah sudah aman untuk PowerShell maupun bash.

Jalankan berurutan:

```powershell
composer install
```

```powershell
npm install
```

Salin file environment — **lewati langkah ini kalau `.env` sudah ada**, karena
menimpanya akan menghapus `APP_KEY` yang sudah di-generate:

```powershell
Copy-Item .env.example .env
```

```powershell
php artisan key:generate
```

```powershell
php artisan migrate --seed
```

```powershell
php artisan storage:link
```

> Langkah ini **wajib**. Tanpa symlink `public/storage`, semua gambar produk,
> avatar, dan cover toko akan gagal dimuat (HTTP 403) walaupun datanya benar.

```powershell
npm run build
```

Jalankan aplikasinya:

```powershell
php artisan serve
```

Buka `http://localhost:8000`.

Untuk development dengan hot reload, jalankan di terminal terpisah:

```powershell
npm run dev
```

<details>
<summary>Versi bash / macOS / Linux (satu baris)</summary>

```bash
composer install && npm install && cp .env.example .env && php artisan key:generate && php artisan migrate --seed && php artisan storage:link && npm run build && php artisan serve
```

</details>

### Mengulang dari nol

Kalau ingin mereset database dan data demo:

```powershell
php artisan migrate:fresh --seed
```

### Queue worker

Email struk, webhook toko, fulfilment produk, dan agregasi analitik berjalan di
queue. Tanpa worker, pembayaran tetap tercatat dan saldo tetap benar, tapi
produk tidak akan terkirim ke pembeli.

```powershell
php artisan queue:work
```

### Scheduler

Menangani pematangan saldo, pelepasan komisi, kedaluwarsa pembayaran, dan
ringkasan analitik harian:

```powershell
php artisan schedule:work
```

Di produksi, pasang satu cron:

```
* * * * * cd /path/ke/jualanyok && php artisan schedule:run >> /dev/null 2>&1
```

### Pakai MySQL

Di `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jualanyok
DB_USERNAME=root
DB_PASSWORD=
```

Lalu jalankan `php artisan migrate:fresh --seed`.

### Login dengan Google

Google OAuth sudah disiapkan menggunakan Laravel Socialite. Buat OAuth Client
bertipe **Web application** di Google Cloud Console, lalu tambahkan authorized
redirect URI berikut untuk development:

```
http://127.0.0.1:8000/auth/google/callback
```

Isi `.env`:

```env
GOOGLE_CLIENT_ID=client-id-dari-google
GOOGLE_CLIENT_SECRET=client-secret-dari-google
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/google/callback
```

Setelah mengubah environment, jalankan:

```powershell
php artisan config:clear
```

Untuk production, ganti redirect URI dengan domain HTTPS production dan
daftarkan URI yang sama persis di Google Cloud Console.

---

## Akun demo

Seeder demo hanya jalan kalau `DEMO_MODE=true`. **Password semuanya `password`.**
Jangan pernah aktifkan `DEMO_MODE` di produksi.

| Peran | Email | Catatan |
| --- | --- | --- |
| Super Admin | `admin@jualanyok.test` | Akses penuh + impersonation |
| Finance Admin | `finance@jualanyok.test` | Hanya dia yang bisa memproses penarikan & refund |
| Creator (produk digital + kelas) | `kreator@jualanyok.test` | Toko `/kreatorkita`, paket Creator |
| Creator (jasa + membership + event) | `desain@jualanyok.test` | Toko `/ruangdesain`, paket Pro |
| Creator (produk fisik + affiliate) | `fisik@jualanyok.test` | Toko `/racunstyle` |
| Affiliate | `affiliate@jualanyok.test` | Punya link + komisi berjalan |
| Customer | `pembeli@jualanyok.test` | Punya pembelian, download, dan kelas |

Storefront demo yang bisa langsung dibuka:

- `http://localhost:8000/kreatorkita`
- `http://localhost:8000/ruangdesain`
- `http://localhost:8000/racunstyle`

Pembeli masuk ke Member Area lewat `/masuk-pembeli` (kode OTP dikirim ke email —
di development, cek `storage/logs/laravel.log`).

---

## Mencoba alur pembayaran

Provider `mock` aktif secara default dan berperilaku seperti gateway asli:
membuat tagihan, memberi instruksi (VA/QRIS/e-wallet), menandatangani callback,
dan menolak callback yang nominalnya tidak cocok.

1. Buka salah satu storefront demo, klik **Beli** pada sebuah produk.
2. Isi nama dan email, lanjut ke checkout.
3. Pilih metode bayar, klik **Bayar Sekarang**.
4. Di halaman status, tekan **Simulasi Bayar Sukses**.

Yang terjadi setelah itu — semuanya nyata, bukan tampilan:

- Order berpindah ke `PROCESSING`/`COMPLETED`
- Stok produk fisik dipotong (reservasi dikonversi jadi pengurangan stok)
- Ledger seller ditulis: gross, biaya platform, komisi affiliate, net
- Komisi affiliate dibuat dengan status `PENDING`
- Akses download / enrolment kelas / tiket dibuat
- Struk dikirim ke email pembeli (butuh queue worker)
- Notifikasi masuk ke dashboard creator

Tombol simulasi hanya muncul kalau `DEMO_MODE=true` dan provider `mock`.

### Mengaktifkan iPaymu API v2

JualanYok memakai **Direct Payment** supaya pembeli memilih QRIS, Virtual
Account, DANA, atau ShopeePay di checkout kita, lalu menyelesaikan pembayaran
di halaman aman iPaymu. Status lunas hanya diterima dari callback dengan
signature yang valid dan setiap event diproses satu kali.

Isi hanya di `.env` server (VA dan API Key asli tidak boleh masuk Git):

```dotenv
PAYMENT_PROVIDER=ipaymu
PAYMENT_MOCK_ENABLED=false
PAYMENT_MANUAL_ENABLED=false

IPAYMU_ENABLED=true
IPAYMU_VA=isi-va-baru-dari-dashboard
IPAYMU_API_KEY=isi-api-key-baru-dari-dashboard
IPAYMU_PRODUCTION=true
IPAYMU_FEE_DIRECTION=MERCHANT
```

Endpoint callback production:

```text
https://jualanyok.conweb.id/webhooks/payments/ipaymu
```

Di **Dashboard iPaymu â†’ Integrasi â†’ Pengaturan**, pilih callback
`application/x-www-form-urlencoded` (format JSON juga didukung). Direct Payment
selalu mengirim `notifyUrl` di setiap tagihan, jadi endpoint di atas ikut
terdaftar otomatis pada transaksi.

Setelah mengubah `.env`:

```bash
php artisan optimize:clear
php artisan optimize
php artisan jualanyok:preflight
```

Preflight akan menolak rilis bila iPaymu masih Sandbox, kredensial kosong,
fee dibebankan ke pembeli, mock masih aktif, atau mode demo masih menyala.

---

## Menjual produk digital

### Bagaimana barangnya sampai ke pembeli

Pembeli **tidak perlu punya akun**. Begitu pembayaran lunas:

1. Struk masuk ke emailnya berisi tombol **"Ambil File Kamu"**
2. Tombol itu membuka `/pesanan/{token}` — halaman miliknya sendiri, tanpa login
3. Semua file bisa diunduh dari situ, **kapan saja, selamanya**

Penjual tidak melakukan apa pun. Tidak ada file yang dikirim manual lewat chat,
dan tidak ada penjual yang harus online jam 2 pagi.

**File tidak pernah dilampirkan ke email.** Lampiran mentok di batas 25 MB,
tidak bisa dicabut atau dibatasi setelah terkirim, dan membuat pembaruan versi
jadi mustahil.

### Tautannya permanen, kuotanya membatasi

Token di URL itu kredensialnya: 48 karakter acak, menunjuk **satu** pesanan,
tidak memberi sesi dan tidak menjangkau apa pun selain pesanan itu.

- Setiap unduhan **diperiksa ulang** saat diminta — dicabut? kedaluwarsa? kuota habis?
- Kuota per file diatur penjual (`download_limit`); kalau habis, halamannya
  menjelaskan alasannya, bukan sekadar menyembunyikan tombol
- Halaman ini dikirim dengan header `X-Robots-Tag: noindex, nofollow` —
  sebagai header, bukan meta tag, karena crawler tanpa JavaScript tidak akan
  pernah melihat tag yang dirender di sisi klien
- Token salah dan token yang tidak pernah ada memberi respons identik

### Pembeli lama ikut dapat versi baru

Ini yang tidak bisa dilakukan penyerahan lewat chat. Saat penjual menekan
**Ganti file**, semua pembeli lama otomatis dikabari lewat email bahwa ada edisi
baru — di balik tautan yang sama seperti waktu mereka beli, gratis, tanpa beli
ulang. Entitlement-nya hidup lebih lama daripada transaksinya.

### Kalau pembeli mau punya perpustakaan

Opsional. Tombol **"Simpan ke akunku"** menempelkan pembelian tamu ke akun yang
sedang login. Tautan aslinya tetap berfungsi setelahnya — mengklaim bersifat
menambah, bukan mengganti.

Kelas dan membership tetap butuh akun karena menyimpan progres belajar; halaman
pengambilan menyampaikan itu terus terang, bukan melempar tamu ke dinding login.


File yang dijual diatur dari **Dashboard → Produk → (pilih produk) → tab File**.
Tab ini hanya muncul untuk produk bertipe *Produk Digital*, dan baru tersedia
setelah produknya tersimpan.

Yang bisa dilakukan di sana:

- Unggah file (tarik-lepas atau pilih manual), atau daftarkan **tautan eksternal**
  kalau filenya sudah kamu hosting sendiri.
- Atur nama yang dilihat pembeli, versi, batas jumlah unduh, dan masa berlaku akses.
- **Ganti file** — menukar isi file tanpa mencabut akses pembeli lama, jadi semua
  yang sudah beli otomatis dapat versi terbaru. Nomor versi naik sendiri (1.0 → 1.1).

### Cara file dilindungi

- File ditulis ke disk **privat** (`storage/app/private`), bukan `storage/app/public`.
  Tidak ada URL publik yang mengarah ke sana.
- Nama file di disk diacak, jadi tidak bisa ditebak dari nama aslinya.
- Pembeli hanya menerima **signed URL berumur 15 menit**. Setiap kali diunduh,
  syarat aksesnya divalidasi ulang (dicabut? kedaluwarsa? kuota habis?), sehingga
  tautan yang bocor tetap tidak bisa dipakai melewati haknya.
- Tipe file dibatasi lewat `config/jualanyok.uploads.file_mimes` dan diperiksa
  berdasarkan **isi** file, bukan sekadar ekstensinya — `shell.php` yang di-rename
  jadi `.pdf` tetap ditolak.

### Produk digital tanpa file tidak bisa dijual

Ini ditegakkan berlapis, supaya tidak ada pembeli yang membayar lalu tidak
menerima apa pun:

1. Produk digital tanpa file **tidak bisa diubah statusnya menjadi Aktif**.
2. Kalaupun statusnya aktif karena data lama, produknya **disembunyikan** dari
   storefront (`scopeDeliverable`).
3. `CheckoutService` **menolak** order yang memuat produk tak-terkirim.

Menghapus file yang sudah pernah dibeli juga ditolak — karena relasinya
`cascadeOnDelete`, penghapusan akan mencabut akses pembeli lama secara diam-diam.
Gunakan **Ganti file** untuk itu.

> **Catatan `APP_URL`.** Signed URL ikut menandatangani host. Kalau `APP_URL`
> tidak sama dengan origin yang benar-benar melayani permintaan (misal `APP_URL`
> `http://localhost` tapi server jalan di `http://127.0.0.1:8000`), semua link
> download akan ditolak dengan *Invalid signature*.

---

## Block & gaya storefront

Storefront disusun dari block. Selain 21 block dasar, ada enam block showcase:

| Block | Gunanya |
|---|---|
| **Carousel** | Slide gambar yang bisa digeser, dengan judul dan tautan per slide |
| **Teks Berjalan** | Ticker promo yang bergulir mulus |
| **Angka Pencapaian** | Angka yang menghitung naik saat discroll — pembeli, rating, pengalaman |
| **Logo Partner** | Deretan logo brand, abu-abu sampai disentuh |
| **Sebelum & Sesudah** | Penggeser pembanding hasil kerja |
| **Alur Langkah** | Proses bernomor, vertikal atau horizontal |

### Mengatur tampilan tiap block

Panel **Tampilan & animasi** di editor block mengatur latar belakang, ruang
dalam, sudut, bayangan, perataan, lebar isi, dan animasi saat discroll
(muncul, naik, geser, membesar, dari buram) beserta jeda mulainya.

**Bukan CSS bebas.** Nilainya kosakata tetap yang divalidasi server
(`App\Support\BlockStyle`). Sebelumnya `style` menerima array apa pun dan
dirender langsung sebagai inline style — artinya sebuah block bisa diberi
`position:fixed; inset:0` dan menutupi tombol beli di tokonya sendiri, sementara
pratinjau builder tetap terlihat normal. Token tidak bisa menghasilkan halaman
rusak, dan warnanya selalu mengikuti tema toko sehingga tidak pernah tabrakan
dengan palet.

### Tiga jaring pengaman animasi

Animasi tidak boleh menyembunyikan barang dagangan:

1. **Keadaan tersembunyi ada di dalam `@media (scripting: enabled)`.** Kalau
   bundel JS gagal dimuat, isinya langsung terlihat — bukan kolom kosong.
2. **Reveal ditulis sebagai inline style**, jadi menang telak di kaskade. Tidak
   ada utility atau media block lain yang bisa membuatnya tetap tak terlihat.
3. **Ada failsafe 1,5 detik.** Observer melaporkan elemen begitu diamati, bahkan
   saat di luar layar. Kalau laporan pertama itu tidak pernah datang, isinya
   ditampilkan tanpa menunggu lebih lama.

Angka pada block Pencapaian juga dijamin mendarat di nilai aslinya lewat timer
terpisah, karena browser menahan `requestAnimationFrame` di tab latar — tanpa
itu, pengunjung yang membuka toko di tab belakang bisa menemukan angkanya beku
di nol. Desimal dipertahankan: rating 4,9 tidak boleh berubah jadi 5.

Semua animasi mati otomatis untuk pengunjung yang memilih mode hemat gerak.

---

## Keranjang belanja

Pembeli bisa mengumpulkan beberapa produk lalu membayarnya dalam **satu order**.
Keranjang terikat pada satu toko — menjelajah dua kreator tidak mencampur isinya —
dan dikenali lewat cookie `jy_cart_{store_id}` (httpOnly, 30 hari). Tamu tidak
perlu login; kalau kemudian login, keranjang yang ada ikut menempel ke akunnya.

### Yang tidak masuk keranjang

Produk yang harga atau syaratnya ditentukan per pembelian tetap memakai jalur
**Beli Sekarang** langsung:

- Donasi dan produk *bayar seikhlasnya* — nominalnya diisi pembeli
- Jasa/konsultasi — butuh pemilihan jadwal
- Produk affiliate (`EXTERNAL`) — transaksinya di luar JualanYok
- Produk digital tanpa file — belum bisa dikirim sama sekali

### Produk bervarian

Produk dengan varian aktif (ukuran, warna) **wajib dipilih varianya dulu** —
stok disimpan per varian, jadi baris pesanan tanpa varian tidak mereservasi apa
pun dan penjual tidak tahu harus mengirim yang mana. Karena itu:

- Kartu produk di grid menampilkan tombol **Pilih Varian** yang membuka halaman
  produk, bukan tombol keranjang (tidak ada ruang memilih opsi di kartu).
- Halaman produk punya pemilih varian; tombol beli dan keranjang terkunci sampai
  ada yang dipilih.
- `CartService` dan `CheckoutService` sama-sama menolak baris tanpa varian.
- Item yang sudah telanjur ada di keranjang sebelum penjual menambahkan varian
  **ditandai**, tidak dihitung, dan tidak menggagalkan checkout item lain.

### Aturan yang dijaga server

- **Harga selalu dihitung ulang** dari katalog setiap keranjang dibaca. Snapshot
  `cart_items.unit_price` tidak pernah menang atas harga sekarang.
- **Item yang sudah tidak dijual tetap terlihat tapi ditandai**, tidak dihitung ke
  subtotal, dan tidak ikut dibayar — supaya pembeli paham kenapa totalnya berubah,
  bukan menemukan barangnya hilang diam-diam.
- **Stok membatasi jumlah** saat menambah dan saat checkout.
- Saat checkout dari keranjang, browser mengirim `from_cart: true` dan **tidak ada
  daftar item sama sekali** — server menyusun ulang barisnya dari keranjang
  tersimpan. Item yang diselipkan di request diabaikan.
- Keranjang **baru dikosongkan setelah order berhasil dibuat**, jadi checkout yang
  gagal tidak menghanguskan isi keranjang.
- Halaman storefront **tidak membuat baris `carts` untuk pengunjung biasa** —
  keranjang lahir saat item pertama ditambahkan.

---

## Pembayaran langganan lewat QRIS

Upgrade paket dibayar dengan **scan QRIS**, lalu dikonfirmasi manual oleh admin.
Tidak ada callback dari penyedia dompet, jadi satu-satunya penghubung antara
transfer masuk dan pelanggan adalah **nominalnya**.

### Cara kerjanya

1. Creator pilih paket → sistem membuat pembayaran dengan **nominal unik**:
   harga paket + 1..999. Contoh: Pro Rp 149.000 → **Rp 149.881**.
2. QRIS statis milikmu diubah jadi **QRIS dinamis** (tag `01` = `12`) dengan
   nominal terkunci di tag `54`, lalu di-render jadi QR di halaman pembayaran.
3. Pembeli scan pakai DANA/GoPay/OVO/ShopeePay/m-banking. Nominal sudah terisi
   otomatis dan tidak bisa diubah.
4. Creator klik **"Saya sudah bayar"** → masuk antrean admin.
5. Admin buka **Admin → Bayar Langganan**, cocokkan nominal dengan notifikasi
   dompet, klik **Setujui** → paket langsung aktif.

### Kenapa nominalnya dijamin unik

Kalau dua orang sama-sama menunggu Rp 149.881, satu transfer masuk jadi ambigu
dan admin bisa mengaktifkan akun yang salah. Jadi keunikannya **ditegakkan
database**, bukan diharapkan dari angka acak:

- Kolom `claimable_amount` menyimpan nominal selama pembayaran masih terbuka,
  dan di-`NULL`-kan begitu lunas/ditolak/kedaluwarsa.
- Kolom itu punya **unique index**. Karena MySQL dan SQLite sama-sama
  memperlakukan `NULL` sebagai nilai berbeda, ini berarti *"maksimal satu klaim
  terbuka per nominal"* di kedua engine — sesuatu yang tidak bisa dilakukan
  partial index secara portabel.
- Nominal yang sudah selesai otomatis bisa dipakai ulang pembeli berikutnya.

Aturan lain yang dijaga:

- Satu creator hanya boleh punya **satu pembayaran terbuka**; membuat yang baru
  otomatis melepas yang lama.
- Pembayaran yang **sudah dikonfirmasi pembayar tidak ikut kedaluwarsa** — dananya
  mungkin memang sedang dalam perjalanan, dan menghapusnya dari antrean sama saja
  menghilangkan uang orang.
- Approve bersifat **idempoten** dan dikunci baris, jadi dua admin yang mengeklik
  bersamaan tidak membuat dua langganan.
- Endpoint upgrade instan **menolak paket berbayar** saat QRIS aktif, supaya
  request buatan tangan tidak bisa mendapatkan paket gratis.

### Konfigurasi

```
QRIS_ENABLED=true
QRIS_STATIC_PAYLOAD="00020101021126570011ID.DANA..."
QRIS_WINDOW_MINUTES=30
```

`QRIS_STATIC_PAYLOAD` adalah **teks di dalam QR statis** milik merchant kamu,
bukan file gambarnya. Nilai ini identitas bisnis, jadi disimpan di `.env` dan
tidak pernah masuk repositori.

> **Wajib pakai tanda kutip.** Payload QRIS memuat nama kota yang biasanya
> mengandung spasi (mis. `Kota Palembang`). Tanpa kutip, Laravel gagal membaca
> `.env` sama sekali.

QR digambar **lokal** (`bacon/bacon-qr-code`) dan ditanam sebagai data URI —
payload pembayaran tidak pernah dikirim ke layanan pihak ketiga hanya untuk
digambar.

---

## QRIS untuk checkout produk

Metode yang sama juga tersedia buat pembeli produk. Aktifkan dengan:

```
QRIS_CHECKOUT_ENABLED=true
QRIS_FEE_PERCENT=0.7
```

### Ke mana uangnya

Uang masuk ke **rekening merchant pemilik platform**, bukan ke kreator — QR-nya
QR kamu. Kreator menerima **angka di saldo aplikasi**, dan uang fisiknya baru
berpindah saat penarikan dicairkan.

Contoh nyata (pembelian e-book Rp 149.000 di paket Creator):

| | |
|---|---|
| Pembeli bayar | **Rp 150.526** → rekening platform |
| Biaya pembayaran (0,7%) | Rp 1.043 → platform |
| Kode unik | Rp 483 → platform |
| Fee platform (5%) | Rp 7.450 → platform |
| **Saldo kreator bertambah** | **Rp 141.550** |

Kreator tidak pernah dibebani biaya pembayaran maupun kode unik — keduanya
dibayar pembeli di atas harga barang dan tetap di platform.

Saldo itu masuk kantong **Pending** dan matang jadi **Available** setelah masa
endap (`HOLDING_PERIOD_DAYS`, default 7 hari), baru bisa ditarik.

### Konfirmasi manual, jalur yang sama dengan gateway

**Admin → Bayar Pesanan** menampilkan antrean. Cari nominal yang masuk di
dompet (bisa diketik `150526` atau `150.526`), klik **Konfirmasi lunas**.

Konfirmasi itu memanggil `PaymentService::markPaid` — persis fungsi yang dipakai
callback gateway. Jadi stok terpotong, ledger tertulis, komisi affiliate
terhitung, produk terkirim, dan struk terkirim dengan cara yang identik. **Tidak
ada jalur "manual" kedua yang lebih lemah.**

### Yang dijaga

- **Nominal unik dijamin database.** Kolom `payments.claimable_amount` memegang
  nominal selama pembayaran terbuka dan di-`NULL` begitu lunas/gagal/kedaluwarsa,
  dengan unique index di atasnya. Dua pembayaran terbuka tidak mungkin bernominal
  sama, jadi satu transfer masuk selalu menunjuk ke satu pesanan.
- **Checkout yang ditinggalkan mengembalikan nominalnya** saat kedaluwarsa —
  kalau tidak, satu angka terkunci selamanya dan rentang 999 pelan-pelan habis.
- **Kembali ke QRIS memakai QR yang sama**, bukan membuat nominal baru, supaya
  pembeli yang sudah terlanjur scan tetap valid.
- **Konfirmasi ganda tidak membayar penjual dua kali** — `markPaid` idempoten dan
  mengunci baris.
- Total yang tertulis di halaman checkout **adalah yang ditagih**. Biaya
  pembayaran dihitung ulang saat metode dipilih, dan berganti metode tidak
  menumpuk dua biaya.

> **Catatan.** Karena uang mengalir lewat rekening platform, kamu menampung dana
> milik orang lain. Pastikan selalu ada kas untuk membayar seluruh saldo yang
> bisa ditarik, dan cek kewajiban perizinannya (di Indonesia aktivitas semacam
> ini umumnya masuk ranah PJP Bank Indonesia) sebelum volumenya besar.

---

## Email

Alur email akun sudah lengkap dan berbahasa Indonesia:

| Kejadian | Email |
|---|---|
| Daftar biasa | Selamat datang **+** konfirmasi alamat email |
| Daftar via Google | Selamat datang saja — alamatnya sudah dibuktikan Google |
| Lupa password | Tautan atur ulang, berlaku 60 menit |
| Login pembeli | Kode OTP 6 digit, berlaku 10 menit |
| Pesanan lunas | Struk ke pembeli, notifikasi ke creator |

Pengguna Google **tidak** dikirimi email verifikasi — meminta konfirmasi alamat
yang sudah dibuktikan Google itu mubazir, dan melatih orang mengeklik tautan
verifikasi yang tidak mereka minta.

### Konfigurasi SMTP

Development memakai `MAIL_MAILER=log`; semua email masuk ke
`storage/logs/laravel.log` sehingga bisa diperiksa tanpa mengirim apa pun.

Untuk produksi dengan email Hostinger:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=mail@domainkamu.id
MAIL_PASSWORD=            # isi sendiri, jangan pernah di-commit
MAIL_FROM_ADDRESS=mail@domainkamu.id
```

Port 465 memakai SSL implisit (`MAIL_SCHEME=smtps`). Kalau memakai port 587,
set `MAIL_PORT=587` dan `MAIL_SCHEME=smtp` (STARTTLS).

Teks bawaan Laravel diterjemahkan lewat `lang/id.json`, jadi email tidak lagi
mencampur bahasa — email akun yang terbaca seperti layanan lain mengundang orang
memperlakukannya sebagai phishing. Pastikan `APP_LOCALE=id`.

---

## Menjalankan test

```powershell
php artisan test
```

83 test, 261 assertion. Yang dicakup:

- Registrasi, login (email/username), username unik + reserved, OTP pembeli
- Onboarding creator + pemasangan template
- Block CRUD, reorder, duplikasi, penjadwalan, draft vs published
- Product CRUD, slug unik, soft delete
- Checkout: total, kupon, idempotency, harga selalu dari database
- Stock locking (anti oversell)
- Payment callback: signature, idempotency, validasi nominal
- Digital access setelah pembayaran + signed download URL + batas unduhan
- Affiliate: atribusi, penolakan self-purchase, kalkulasi komisi, pematangan
- Refund: clawback saldo penjual (penuh & sebagian), pembatalan komisi, pencabutan akses
- Ledger: immutability, bucket tidak boleh negatif, rekonsiliasi
- Withdrawal: hold, pencegahan double withdrawal, reversal, minimum, rekening terverifikasi
- Otorisasi antar-creator, antar-customer, dan pemisahan role admin
- Limit paket & feature gating
- Rendering semua halaman terhadap data demo

Cek tipe dan build frontend:

```powershell
npx tsc --noEmit
```

```powershell
npm run build
```

---

## Struktur utama

```
app/
├── Enums/            Status order, payment, fulfillment, ledger, dll.
├── Models/           Eloquent models
├── Payments/         Abstraksi payment gateway
│   ├── PaymentProviderInterface.php
│   ├── PaymentManager.php
│   └── Providers/    MockProvider, ManualTransferProvider, MidtransProvider
├── Services/         Logika bisnis
│   ├── LedgerService.php          ← satu-satunya penulis saldo
│   ├── CheckoutService.php        ← pembuatan order + reservasi stok
│   ├── PaymentService.php         ← callback, settlement, ledger
│   ├── FulfillmentService.php     ← pengiriman produk
│   ├── AffiliateService.php       ← atribusi & komisi
│   ├── WithdrawalService.php      ← pencairan
│   ├── RefundService.php          ← refund & clawback
│   ├── PlanService.php            ← feature gating
│   └── AnalyticsService.php       ← event & agregasi
├── Http/Controllers/
│   ├── PublicSite/   Landing, storefront, checkout, webhook, download
│   ├── Auth/         Registrasi, login, OTP, onboarding
│   ├── Creator/      Dashboard creator
│   ├── Customer/     Member area
│   ├── Affiliate/    Dashboard affiliate
│   └── Admin/        Panel super admin
resources/js/
├── pages/            Halaman Inertia (Marketing, Auth, Storefront, Checkout,
│                     Creator, Member, Affiliate, Admin)
├── layouts/          MarketingLayout, AuthLayout, DashboardLayout
└── components/       UI kit, block renderer, komponen bersama
routes/
├── web.php           Marketing, checkout, webhook, download
├── auth.php          Autentikasi & onboarding
├── creator.php       /dashboard/*
├── member.php        /member/*
├── affiliate.php     /affiliate/*
├── admin.php         /admin/*
└── storefront.php    /{username} — didaftarkan paling akhir
```

---

## Alur status

### Order

```
DRAFT → PENDING_PAYMENT → PAID → PROCESSING → COMPLETED
                ↓                      ↓
            EXPIRED              REFUND_REQUESTED → REFUNDED
            CANCELLED                            → PARTIALLY_REFUNDED
```

Status order, status pembayaran, dan status fulfilment disimpan di tiga kolom
terpisah supaya tidak saling menimpa.

### Payment

```
PENDING → PROCESSING → PAID
   ↓                     ↓
EXPIRED / FAILED    REFUNDED / PARTIALLY_REFUNDED
```

### Saldo (bucket)

```
Penjualan lunas
      ↓
  PENDING ──(setelah masa tahan)──► AVAILABLE ──(ajukan tarik)──► HELD ──(cair)──► WITHDRAWN
      ↑                                  ↑                          │
      └────── refund (clawback) ─────────┘◄──── penarikan ditolak ──┘
```

### Komisi affiliate

```
PENDING ──(lewat masa refund)──► APPROVED ──► PAID
   │                                 │
   └────────── REVERSED ◄────────────┘   (kalau ordernya direfund)
```

### Withdrawal

```
REQUESTED → UNDER_REVIEW → APPROVED → PROCESSING → PAID
     │            │            │           │
     └────────────┴────────────┴───────────┴──► REJECTED / CANCELLED / FAILED
                                                 (dana kembali ke AVAILABLE)
```

### Subscription

```
TRIALING → ACTIVE → PAST_DUE → EXPIRED
              ↓
          CANCELLED (aktif sampai akhir periode)
```

---

## Cara kerja uang

Ini bagian yang paling penting untuk dipahami sebelum mengubah apa pun.

**`LedgerService` adalah satu-satunya kode yang boleh menulis saldo.** Tidak ada
tempat lain yang menyentuh kolom balance di tabel `wallets`.

- `ledger_entries` bersifat *append-only*. Model-nya melempar exception kalau
  ada yang mencoba `update()` atau `delete()`. Koreksi selalu berupa entri baru
  dengan tanda berlawanan.
- Setiap penulisan mengunci baris wallet (`lockForUpdate`) dan menulis entri
  ledger + memperbarui bucket dalam satu transaksi.
- Bucket tidak pernah boleh negatif — ini yang membuat double withdrawal
  mustahil: permintaan kedua menemukan dananya sudah pindah ke `HELD` dan gagal.
- `idempotency_key` membuat callback yang diulang menjadi no-op.
- `LedgerService::reconcile()` membandingkan saldo tersimpan dengan hasil
  penjumlahan ledger. Test memverifikasi keduanya selalu cocok.

---

## Menambahkan payment provider

1. Buat adapter di `app/Payments/Providers/` yang mengimplementasi
   `PaymentProviderInterface`.
2. Daftarkan di `PaymentManager::build()`.
3. Tambahkan konfigurasi di `config/payments.php` dan variabel di `.env.example`.

Yang wajib benar di adapter:

- `verifyWebhook()` harus mengembalikan `false` untuk apa pun yang tidak bisa
  dibuktikan secara kriptografis. Signature yang tidak valid membuat endpoint
  membalas 401 dan tidak menyentuh saldo.
- `parseWebhook()` harus mengembalikan `eventId` yang unik per event. Kolom
  unik `(provider, event_id)` yang mencegah pemrosesan ganda.
- `amount` yang dikembalikan dipakai untuk verifikasi. Kalau tidak cocok dengan
  tagihan, `PaymentService` melempar exception dan tidak menyelesaikan order.

Business logic (order, ledger, fulfilment, komisi) tidak perlu diubah sama
sekali. `MidtransProvider` sudah tersedia sebagai contoh lengkap.

## Menambahkan notification provider

Notifikasi memakai channel Laravel (`mail`, `database`). Untuk WhatsApp:

1. Buat channel class dengan method `send($notifiable, $notification)`.
2. Tambahkan `toWhatsapp()` di notification yang relevan.
3. Tambahkan nama channel ke `via()`.

Saat ini WhatsApp belum punya adapter konkret — lihat bagian keterbatasan.

---

## Produk fisik dan pengiriman (fase 1-2)

Alur produk fisik sudah memakai ongkir server-side dan dana tahan:

1. Seller mengisi berat/dimensi produk dan alamat gudang pada **Dashboard -> Pengiriman**.
2. Pembeli memilih alamat serta layanan kurir. Tarif dikunci dalam token terenkripsi dan diverifikasi ulang saat checkout.
3. Setelah pembayaran lunas, dana seller tetap berada di bucket pending. Seller kemudian membuat pengiriman dari detail pesanan.
4. Biteship mengirim status, resi, dan perubahan biaya melalui webhook. Scheduler juga menyinkronkan kiriman aktif sebagai fallback.
5. Setelah paket diterima, pembeli dapat mengonfirmasi penerimaan atau membuka komplain. Dana baru dapat dilepas setelah penerimaan/auto-complete dan tidak ada sengketa aktif.
6. Komplain masuk ke pusat sengketa admin. Keputusan untuk pembeli membuat antrean refund; keputusan untuk seller menyelesaikan order dan membuka jadwal pelepasan dana.

Konfigurasi production:

```env
SHIPPING_PROVIDER=biteship
BITESHIP_ENABLED=true
BITESHIP_API_TOKEN=isi_dari_dashboard_biteship
BITESHIP_COURIERS=jne,sicepat,anteraja,jnt,ninja,tiki,pos
BITESHIP_WEBHOOK_HEADER=X-Callback-Token
BITESHIP_WEBHOOK_SECRET=buat_secret_panjang_acak
```

Daftarkan `POST https://domain-kamu/webhooks/shipping/biteship` untuk event
`order.status`, `order.waybill_id`, dan `order.price`. Pada pengaturan webhook
Biteship, isi **Headers Signature Key** sesuai `BITESHIP_WEBHOOK_HEADER` dan
**Headers Signature Secret** sesuai `BITESHIP_WEBHOOK_SECRET`.

Scheduler wajib aktif agar status tetap tersinkron bila webhook terlambat:

```cron
* * * * * cd /path/aplikasi && php artisan schedule:run >> /dev/null 2>&1
```

## Unit economics marketplace

Angka biaya tidak ditanam secara acak di controller. Semua aturan berada di
`config/marketplace.php`, dapat dioverride lewat environment, dan setiap order
menyimpan snapshot estimasi serta biaya provider aktual. Dengan begitu perubahan
tarif besok tidak mengubah histori transaksi kemarin.

Default yang dipakai sebagai titik awal operasional:

- QRIS reguler: `0,7%`, settlement H+2, menjadi rekomendasi utama untuk transaksi kecil.
- QRIS cepat/e-wallet: `2,5%` dan `3,5%`; tampil transparan, bukan biaya tersembunyi.
- Virtual account: biaya tetap per kanal; mesin rekomendasi membandingkan nominal
  aktual sehingga VA baru disarankan ketika memang lebih hemat.
- Komisi platform dihitung dari nilai barang setelah diskon, bukan dari ongkir.
- Ongkir Biteship adalah pass-through. Selisih quote dengan biaya aktual masuk
  akun selisih ongkir, bukan disamarkan sebagai pendapatan produk.
- Seller baru dan produk fisik memiliki rolling reserve. Refund mengambil dana
  dari reserve, pending, available, lalu mencatat utang seller bila masih kurang.
- Refund manual memakai dua tahap: finance menerima pengajuan, mengirim dana di
  iPaymu/bank, lalu memasukkan nomor referensi. Saldo seller dan jurnal baru
  berubah setelah transfer dikonfirmasi. Payout seller ditahan selama refund terbuka.
- Pencairan punya nominal minimum dan biaya tetap agar transfer kecil tidak
  menggerus margin platform.
- Panggilan Biteship Maps/Rates/Tracking dicache dan dicatat per request nyata,
  sehingga refresh berulang tidak langsung menjadi biaya API baru.

Seeder aturan pembayaran wajib dijalankan setelah migration. Seeder idempotent,
jadi aman dijalankan kembali saat tarif diperbarui:

```bash
php artisan migrate --force
php artisan db:seed --class=PaymentEconomicsSeeder --force
php artisan jualanyok:economics-check
```

Panel **Admin -> Ekonomi** memisahkan GMV, pendapatan platform, biaya gateway,
ongkir, affiliate, refund, payout, biaya API, dan contribution margin. Profit
Guard memberi peringatan ketika margin negatif, biaya aktual melewati estimasi,
saldo negatif membesar, atau data lama belum memakai settlement versi terbaru.

Sebelum payout massal, jalankan `jualanyok:economics-check`. Perintah ini gagal
bila cache wallet berbeda dari ledger, jurnal debit/kredit tidak seimbang atau
kosong, order modern sudah lunas tanpa jurnal `ORDER_PAID`, maupun refund selesai
tanpa jurnal `ORDER_REFUNDED`.

## Deployment checklist

Jalankan ini dulu — perintahnya memeriksa semua poin di bawah dan keluar dengan
kode error kalau ada yang belum aman, jadi bisa dipasang di skrip deploy:

```bash
php artisan jualanyok:preflight
```


- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] **`DEMO_MODE=false`** — mematikan seeder demo dan tombol simulasi bayar
- [ ] `APP_KEY` di-generate dan disimpan aman
- [ ] `PAYMENT_MOCK_ENABLED=false`, provider asli aktif dan terisi kuncinya
- [ ] `SESSION_SECURE_COOKIE=true`, aplikasi di belakang HTTPS
- [ ] Database MySQL 8 dengan backup terjadwal
- [ ] `FILESYSTEM_DISK=s3` (atau disk privat lain) untuk file produk berbayar
- [ ] Mailer asli terkonfigurasi dan domain terverifikasi (SPF/DKIM)
- [ ] `php artisan migrate --force`
- [ ] `php artisan db:seed --class=PaymentEconomicsSeeder --force`
- [ ] `php artisan jualanyok:economics-check`
- [ ] `npm run build`
- [ ] `php artisan config:cache route:cache view:cache`
- [ ] Queue worker berjalan di bawah supervisor
- [ ] Cron scheduler terpasang
- [ ] Webhook gateway diarahkan ke `POST /webhooks/payments/{provider}`
- [ ] Webhook Biteship diarahkan ke `POST /webhooks/shipping/biteship` dengan header secret
- [ ] Monitoring untuk job gagal dan webhook gagal

---

## Catatan keamanan

- Otorisasi divalidasi di server pada setiap request. Menyembunyikan menu di
  frontend tidak pernah dijadikan mekanisme keamanan.
- Path file produk tidak pernah dikirim ke client. Pembeli hanya menerima signed
  URL berumur pendek yang menunjuk ke token akses, dan syaratnya (revoked,
  kedaluwarsa, kuota) dicek ulang saat file benar-benar diunduh.
- Nomor rekening pencairan dan secret webhook dienkripsi di database.
- Endpoint webhook pembayaran dikecualikan dari CSRF, tapi wajib lolos verifikasi
  signature.
- Integrasi pixel hanya menerima **ID**, bukan potongan script, sehingga tidak
  ada script arbitrary yang bisa disuntikkan ke halaman toko.
- Block `EMBED` hanya mengizinkan host yang ada di allowlist.
- Analitik memakai hash harian dari IP + user agent + APP_KEY. Hash berganti tiap
  hari sehingga tidak bisa dipakai melacak seseorang lintas hari atau lintas toko.
- Endpoint OTP dan reset password memberi respons identik untuk email yang ada
  maupun tidak, supaya tidak bisa dipakai enumerasi akun.
- Impersonation hanya untuk super admin, menampilkan banner, dan dicatat di audit
  log pada saat mulai maupun selesai.
- Security header (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  HSTS saat HTTPS) dipasang lewat middleware.
- Rate limit terpasang pada login, registrasi, OTP, checkout, webhook, dan
  download.

---

## Asumsi yang diambil

Beberapa detail tidak disebutkan spesifik, jadi diputuskan sebagai berikut:

1. **Biaya gateway dibebankan ke seller/platform sesuai aturan kanal** dan tidak
   disamarkan sebagai surcharge QRIS kepada pembeli. Fee provider aktual menang
   atas estimasi konfigurasi.
2. **Biaya platform hanya dihitung dari nilai barang setelah diskon**. Ongkir,
   pajak, tip, dan biaya gateway bukan basis komisi.
3. **Komisi affiliate dipotong dari bagian penjual**, bukan ditambahkan di atas
   harga pembeli.
4. **Masa tahan komisi affiliate (14 hari) lebih panjang dari masa tahan saldo
   penjual (7 hari)**, supaya refund tidak menyebabkan komisi yang sudah cair
   harus ditarik kembali.
5. **Atribusi affiliate memakai last valid click** dengan cookie httpOnly.
6. **Pembeli tidak wajib punya akun.** Identitas pembeli adalah email per toko;
   akun dibuat otomatis saat pertama kali login OTP.
7. **Refund parsial memotong saldo penjual dan affiliate secara kumulatif dan
   proporsional**. Urutannya reserve, pending, available, lalu saldo negatif;
   pendapatan berikutnya otomatis menutup saldo negatif.
8. **Reserve seller** dilepas terjadwal setelah jendela risiko. Dana reserve
   tidak dihitung sebagai saldo yang boleh ditarik.
9. **SQLite jadi default development** supaya aplikasi bisa langsung dijalankan;
   MySQL 8 tetap didukung penuh lewat env.

---

## Keterbatasan yang masih ada

Ditulis apa adanya supaya tidak ada kejutan:

- **Adapter Midtrans belum diuji end-to-end** terhadap sandbox asli. Kodenya
  ditulis sesuai kontrak Snap + notification yang terdokumentasi, tapi tanpa
  kredensial sandbox di environment ini, jalannya belum pernah diverifikasi.
  Xendit baru ada slot konfigurasinya, adapternya belum ditulis.
- **Watermark PDF belum diimplementasikan.** Kolom `watermark_pdf` sudah ada di
  `product_files` dan ikut ditandai di seeder, tapi belum ada job yang benar-benar
  menempelkan nama/email pembeli ke PDF.
- **Adapter WhatsApp belum ada.** Notifikasi berjalan lewat email dan in-app.
  Struktur channel-nya siap, providernya belum ditulis.
- **Email broadcast dan campaign**: tabelnya (`campaigns`, `marketing_consents`)
  dan pencatatan consent sudah jalan, tapi UI pengiriman broadcast belum dibuat.
- **Verifikasi custom domain**: tabel `store_domains` beserta token verifikasi
  sudah ada dan tampil di pengaturan, tapi proses pengecekan DNS-nya belum
  otomatis — masih perlu ditambahkan admin/support.
- **Support ticket**: form kontak sudah membuat tiket sungguhan di database, tapi
  panel balasan tiket untuk admin belum dibuat.
- **Booking jasa**: slot dibuat saat order dibayar dan dilindungi unique index
  anti double-booking, tapi UI pemilihan jadwal oleh pembeli belum ada — slot
  saat ini diisi lewat metadata order.
- **Team member / sub-admin**: role dan permission sudah ada di database, tapi
  UI untuk mengundang anggota tim belum dibuat.
- **Bundle JS masih satu chunk (±690 KB)**. Berfungsi, tapi sebaiknya
  di-code-split per area sebelum produksi.
- **Content moderation & report**: tabel `content_reports` tersedia, alur review
  di panel admin belum dibuat.
