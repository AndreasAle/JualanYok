<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\AffiliateProgram;
use App\Models\Block;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\Role;
use App\Models\Store;
use App\Models\StorefrontTemplate;
use App\Models\StoreTheme;
use App\Models\User;
use App\Support\CoverArt;
use App\Support\Username;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Menyiapkan workspace terbatas untuk tim verifikasi iPaymu.
 * Seeder aman dijalankan ulang dan tidak pernah memberi role admin.
 */
class IpaymuVerificationSeeder extends Seeder
{
    private const MARKER = 'ipaymu-verification';

    private const PRODUCT_SLUG = 'produk-uji-integrasi-pembayaran';

    private const GUIDE_PATH = 'verification/ipaymu/panduan-uji-pembayaran.txt';

    public function run(): void
    {
        $config = $this->validatedConfig();

        $avatarPath = CoverArt::avatar(
            $config['store_name'], '#6D28D9', '#EC4899', 'verification/ipaymu',
        );
        $coverPath = CoverArt::cover(
            $config['store_name'], '#6D28D9', '#EC4899', 'verification/ipaymu',
        );
        $productThumbnail = CoverArt::product(
            'Produk Uji Pembayaran', 'Verifikasi iPaymu', 'verification/ipaymu',
        );

        $guide = implode(PHP_EOL, [
            'PANDUAN UJI PEMBAYARAN JUALANYOK',
            '',
            'Produk ini disiapkan khusus untuk pengujian integrasi payment gateway.',
            'Nominal transaksi: Rp10.000.',
            'Setelah pembayaran berhasil, pesanan dan akses produk tercatat otomatis.',
            '',
            'Bantuan: '.config('jualanyok.business.email'),
        ]);
        Storage::disk('local')->put(self::GUIDE_PATH, $guide);

        DB::transaction(function () use ($config, $avatarPath, $coverPath, $productThumbnail, $guide) {
            $user = $this->upsertReviewer($config);
            $store = $this->upsertStore($user, $config, $avatarPath, $coverPath);
            $product = $this->upsertProduct($store, $productThumbnail, strlen($guide));

            $this->replaceBlocks($store, $product);

            $store->forceFill([
                'is_published' => true,
                'published_at' => $store->published_at ?? now(),
                'status' => 'active',
            ])->save();
        });

        $this->command?->info('Workspace verifikasi iPaymu siap. Akun hanya memiliki akses Creator.');
    }

    /** Hanya dipakai rollback migration dan tetap memeriksa marker kepemilikan. */
    public function remove(): void
    {
        $email = (string) config('jualanyok.ipaymu_review.email');
        $user = User::withTrashed()->where('email', $email)->first();

        if (! $user || data_get($user->profile?->onboarding_state, 'provisioned_for') !== self::MARKER) {
            return;
        }

        $user->forceDelete();
        Storage::disk('local')->delete(self::GUIDE_PATH);
        Storage::disk('public')->deleteDirectory('verification/ipaymu');
    }

    private function upsertReviewer(array $config): User
    {
        $user = User::withTrashed()->where('email', $config['email'])->first();

        if ($user && data_get($user->profile?->onboarding_state, 'provisioned_for') !== self::MARKER) {
            throw new RuntimeException(
                "Email akun review iPaymu sudah dipakai akun lain ({$config['email']}). Gunakan email berbeda di IPAYMU_REVIEW_EMAIL.",
            );
        }

        $usernameOwner = User::withTrashed()
            ->where('username', $config['username'])
            ->when($user, fn ($query) => $query->whereKeyNot($user->id))
            ->exists();

        if ($usernameOwner) {
            throw new RuntimeException(
                "Username akun review iPaymu sudah dipakai ({$config['username']}). Ubah IPAYMU_REVIEW_USERNAME.",
            );
        }

        if (! $user) {
            $user = new User;
        } elseif ($user->trashed()) {
            $user->restore();
        }

        $user->forceFill([
            'name' => $config['name'],
            'username' => $config['username'],
            'email' => $config['email'],
            'password' => $config['password'],
            'email_verified_at' => $user->email_verified_at ?? now(),
            'tos_accepted_at' => $user->tos_accepted_at ?? now(),
            'is_creator' => true,
            'is_affiliate' => false,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
        ])->save();

        $role = Role::firstOrCreate(
            ['slug' => Role::CREATOR],
            ['name' => 'Creator', 'description' => 'Punya toko dan mengelola produk sendiri.'],
        );
        $user->roles()->sync([$role->id]);
        $user->walletOrCreate();
        $user->profile()->updateOrCreate([], [
            'display_name' => $config['name'],
            'goal' => 'digital',
            'niche' => 'Teknologi',
            'bio' => 'Akun terbatas untuk verifikasi integrasi pembayaran JualanYok.',
            'onboarding_state' => [
                'completed_at' => now()->toIso8601String(),
                'provisioned_for' => self::MARKER,
            ],
        ]);

        return $user->fresh(['profile', 'roles']);
    }

    private function upsertStore(User $user, array $config, string $avatarPath, string $coverPath): Store
    {
        $store = Store::withTrashed()->where('user_id', $user->id)->first();

        $usernameOwner = Store::withTrashed()
            ->where('username', $config['store_username'])
            ->when($store, fn ($query) => $query->whereKeyNot($store->id))
            ->exists();

        if ($usernameOwner) {
            throw new RuntimeException(
                "Alamat toko review sudah dipakai ({$config['store_username']}). Ubah IPAYMU_REVIEW_STORE_USERNAME.",
            );
        }

        if (! $store) {
            $store = new Store;
            $store->user_id = $user->id;
        } elseif ($store->trashed()) {
            $store->restore();
        }

        $template = StorefrontTemplate::where('slug', 'creator-digital')->first();

        $store->forceFill([
            'storefront_template_id' => $template?->id,
            'username' => $config['store_username'],
            'name' => $config['store_name'],
            'tagline' => 'Workspace resmi untuk menguji checkout dan pembayaran',
            'bio' => 'Etalase ini disiapkan khusus bagi tim verifikasi payment gateway. Produk, checkout, pembayaran, dan akses setelah transaksi dapat diuji dari sini.',
            'avatar_path' => $avatarPath,
            'cover_path' => $coverPath,
            'socials' => [],
            'whatsapp' => config('jualanyok.business.phone') ?: null,
            'seo_title' => 'Uji Pembayaran JualanYok',
            'seo_description' => 'Toko uji resmi untuk verifikasi integrasi pembayaran JualanYok.',
            'is_published' => true,
            'published_at' => $store->published_at ?? now(),
            'status' => 'active',
            'show_platform_branding' => true,
        ])->save();

        StoreTheme::updateOrCreate(['store_id' => $store->id], [
            'primary_color' => '#6D28D9',
            'accent_color' => '#EC4899',
            'background_type' => 'solid',
            'background_value' => '#FAF9FF',
            'font_family' => 'jakarta',
            'button_style' => 'rounded',
            'card_style' => 'soft',
            'product_layout' => 'grid',
            'color_scheme' => 'light',
            'extras' => ['preset' => 'jualan-yok-signature'],
        ]);

        AffiliateProgram::updateOrCreate(
            ['store_id' => $store->id, 'product_id' => null],
            [
                'commission_type' => 'percentage',
                'commission_value' => 10,
                'cookie_days' => 30,
                'auto_approve' => false,
                'is_active' => false,
            ],
        );

        return $store->fresh(['theme']);
    }

    private function upsertProduct(Store $store, string $thumbnailPath, int $guideSize): Product
    {
        $product = Product::withTrashed()
            ->where('store_id', $store->id)
            ->where('slug', self::PRODUCT_SLUG)
            ->first();

        if (! $product) {
            $product = new Product;
            $product->store_id = $store->id;
        } elseif ($product->trashed()) {
            $product->restore();
        }

        $product->forceFill([
            'type' => ProductType::Digital,
            'sku' => 'IPAYMU-REVIEW-10000',
            'name' => 'Produk Uji Integrasi Pembayaran',
            'slug' => self::PRODUCT_SLUG,
            'short_description' => 'Produk digital Rp10.000 untuk menguji transaksi payment gateway secara end-to-end.',
            'description' => 'Gunakan produk ini untuk memeriksa alur checkout JualanYok. Setelah pembayaran berhasil, pesanan tercatat dan panduan uji dapat diunduh oleh pembeli.',
            'thumbnail_path' => $thumbnailPath,
            'price' => 10000,
            'compare_at_price' => null,
            'currency' => 'IDR',
            'is_pay_what_you_want' => false,
            'status' => ProductStatus::Active,
            'visibility' => 'public',
            'tags' => ['verifikasi', 'payment-gateway', 'ipaymu'],
            'min_quantity' => 1,
            'max_quantity' => 1,
            'checkout_message' => 'Masukkan email aktif agar hasil transaksi dan akses produk dapat diperiksa.',
            'post_purchase_message' => 'Pembayaran uji berhasil. Silakan periksa pesanan dan akses unduhan.',
            'terms' => 'Produk ini khusus untuk pengujian verifikasi integrasi pembayaran.',
            'affiliate_enabled' => false,
            'settings' => ['provisioned_for' => self::MARKER],
        ])->save();

        ProductFile::updateOrCreate(
            ['product_id' => $product->id, 'name' => 'Panduan Uji Pembayaran.txt'],
            [
                'disk' => 'local',
                'path' => self::GUIDE_PATH,
                'mime_type' => 'text/plain',
                'size' => $guideSize,
                'version' => '1.0',
                'download_limit' => 3,
                'access_days' => 7,
                'position' => 0,
            ],
        );

        return $product->fresh('files');
    }

    private function replaceBlocks(Store $store, Product $product): void
    {
        Block::withTrashed()->where('store_id', $store->id)->forceDelete();

        $blocks = [
            [
                'type' => 'HEADING',
                'title' => null,
                'content' => ['text' => 'Uji pembayaran JualanYok dengan alur nyata', 'size' => 'lg'],
            ],
            [
                'type' => 'TEXT',
                'title' => 'Tentang etalase ini',
                'content' => ['body' => 'Workspace ini hanya dipakai untuk verifikasi. Tim pemeriksa dapat membuka produk, mengisi data pembeli, memilih metode pembayaran, dan memastikan akses pascatransaksi tercatat.'],
            ],
            [
                'type' => 'FEATURED_PRODUCTS',
                'title' => 'Produk Uji',
                'content' => ['product_ids' => [$product->id], 'limit' => 1],
            ],
            [
                'type' => 'FAQ',
                'title' => 'Informasi Pengujian',
                'content' => ['items' => [
                    ['question' => 'Berapa nominal transaksi uji?', 'answer' => 'Nominal produk uji adalah Rp10.000.'],
                    ['question' => 'Apa yang terjadi setelah pembayaran?', 'answer' => 'Pesanan ditandai lunas dan akses unduhan diberikan kepada pembeli.'],
                    ['question' => 'Apakah akun ini memiliki akses admin?', 'answer' => 'Tidak. Akun pemeriksa hanya memiliki akses Creator ke workspace uji ini.'],
                ]],
            ],
        ];

        foreach ($blocks as $position => $definition) {
            Block::create([
                'store_id' => $store->id,
                'type' => $definition['type'],
                'title' => $definition['title'],
                'content' => $definition['content'],
                'draft_content' => $definition['content'],
                'position' => $position,
                'is_published' => true,
                'visible_mobile' => true,
                'visible_desktop' => true,
                'animation' => $position === 0 ? 'fade-up' : null,
            ]);
        }
    }

    /** @return array{name: string, email: string, username: string, password: string, store_username: string, store_name: string} */
    private function validatedConfig(): array
    {
        $config = config('jualanyok.ipaymu_review', []);
        $required = ['name', 'email', 'username', 'password', 'store_username', 'store_name'];

        foreach ($required as $key) {
            if (blank($config[$key] ?? null)) {
                throw new RuntimeException('Konfigurasi IPAYMU_REVIEW_'.strtoupper($key).' wajib diisi sebelum migration produksi.');
            }
        }

        $config['username'] = Username::normalize((string) $config['username']);
        $config['store_username'] = Username::normalize((string) $config['store_username']);

        if (! filter_var($config['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('IPAYMU_REVIEW_EMAIL bukan alamat email yang valid.');
        }

        if (! Username::isValidFormat($config['username']) || ! Username::isValidFormat($config['store_username'])) {
            throw new RuntimeException('Username review iPaymu harus 3-30 karakter dan hanya memakai huruf kecil, angka, titik, strip, atau underscore.');
        }

        if (strlen((string) $config['password']) < 12) {
            throw new RuntimeException('IPAYMU_REVIEW_PASSWORD minimal 12 karakter.');
        }

        return $config;
    }
}
