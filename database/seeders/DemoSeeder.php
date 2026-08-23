<?php

namespace Database\Seeders;

use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\AffiliateProgram;
use App\Models\Block;
use App\Models\Course;
use App\Models\Inventory;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFile;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\StorefrontTemplate;
use App\Models\User;
use App\Payments\PaymentResult;
use App\Services\AffiliateService;
use App\Services\AnalyticsService;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use App\Services\PlanService;
use App\Services\StoreProvisionService;
use App\Services\WithdrawalService;
use App\Support\CoverArt;
use App\Support\Username;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds a fully populated demo environment.
 *
 * Orders are created through CheckoutService and settled through
 * PaymentService, so the seeded data exercises the same code path as a real
 * purchase: stock is committed, the ledger is written, commissions accrue and
 * digital access is granted. Nothing here writes balances directly.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Fulfilment, receipts and webhooks hang off queued listeners. Running
        // them inline here means the seeded demo has real digital access,
        // enrolments and tickets instead of jobs waiting on an idle worker.
        config(['queue.default' => 'sync']);

        $this->seedDemoFiles();

        $admins = $this->createAdmins();
        $customer = $this->createCustomerAccount();

        $kreator = $this->createKreatorKita();
        $ruang = $this->createRuangDesain();
        $racun = $this->createRacunStyle();

        $affiliate = $this->createAffiliate($kreator, $ruang);

        $this->simulateSales($kreator, $affiliate);
        $this->simulateSales($ruang, null);
        $this->simulateSales($racun, null, 3);

        $this->buildAnalytics([$kreator, $ruang, $racun]);

        $this->command?->info('Demo data siap. Kredensial ada di README.');
    }

    /** Placeholder files so the download flow is exercisable end to end. */
    private function seedDemoFiles(): void
    {
        $disk = Storage::disk('local');

        $files = [
            'demo/content-plan-30-hari.pdf' => '%PDF-1.4
% Demo file JualanYok
',
            'demo/notion-kit.zip' => 'PKdemo',
        ];

        foreach ($files as $path => $contents) {
            if (! $disk->exists($path)) {
                $disk->put($path, $contents);
            }
        }
    }

    /* ------------------------------------------------------------------ */

    private function createAdmins(): array
    {
        $super = $this->makeUser('Admin JualanYok', 'superadmin', 'admin@jualanyok.test', [Role::SUPER_ADMIN]);
        $finance = $this->makeUser('Finance JualanYok', 'financeadmin', 'finance@jualanyok.test', [Role::FINANCE_ADMIN]);

        return compact('super', 'finance');
    }

    private function createCustomerAccount(): User
    {
        return $this->makeUser('Rina Pembeli', 'rinabeli', 'pembeli@jualanyok.test', [Role::CUSTOMER]);
    }

    private function makeUser(string $name, string $username, string $email, array $roles): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'username' => $username,
                'password' => 'password',
                'email_verified_at' => now(),
                'tos_accepted_at' => now(),
                'phone' => '08'.random_int(1000000000, 1999999999),
            ],
        );

        $user->roles()->syncWithoutDetaching(Role::whereIn('slug', $roles)->pluck('id'));
        $user->profile()->firstOrCreate([], ['display_name' => $name]);
        $user->walletOrCreate();

        return $user;
    }

    /* ------------------------------------------------------------------ */

    private function createKreatorKita(): Store
    {
        $owner = $this->makeUser('Ayu Prameswari', 'ayuprames', 'kreator@jualanyok.test', [Role::CREATOR]);

        $store = $this->provisionStore($owner, 'kreatorkita', 'KreatorKita', 'creator-digital', [
            'tagline' => 'Bantu kamu jadi kreator yang konsisten',
            'bio' => 'Aku bikin tools dan kelas buat content creator Indonesia yang capek kehabisan ide.',
        ]);

        app(PlanService::class)->subscribe($owner, Plan::where('slug', Plan::CREATOR)->first());

        $ebook = $this->makeProduct($store, [
            'type' => ProductType::Digital,
            'name' => 'E-book Content Plan 30 Hari',
            'short_description' => 'Rencana konten sebulan penuh, tinggal isi dan posting.',
            'description' => "Berisi 30 ide konten harian lengkap dengan hook, caption, dan CTA.\nCocok buat kamu yang sering bingung mau posting apa hari ini.",
            'price' => 149000,
            'compare_at_price' => 249000,
            'affiliate_enabled' => true,
            'post_purchase_message' => 'Makasih! Jangan lupa join grup Telegram-nya, linknya ada di halaman 2 e-book.',
        ]);

        ProductFile::updateOrCreate(
            ['product_id' => $ebook->id, 'name' => 'content-plan-30-hari.pdf'],
            [
                'disk' => 'local',
                'path' => 'demo/content-plan-30-hari.pdf',
                'mime_type' => 'application/pdf',
                'size' => 2_400_000,
                'version' => '2.1',
                'download_limit' => 5,
                'watermark_pdf' => true,
            ],
        );

        $template = $this->makeProduct($store, [
            'type' => ProductType::Digital,
            'name' => 'Template Notion Bisnis Kreator',
            'short_description' => 'Kelola konten, klien, dan keuangan dari satu dashboard.',
            'price' => 89000,
            'affiliate_enabled' => true,
        ]);

        ProductFile::updateOrCreate(
            ['product_id' => $template->id, 'name' => 'notion-bisnis-kreator.zip'],
            ['disk' => 'local', 'path' => 'demo/notion-kit.zip', 'size' => 840_000, 'version' => '1.0'],
        );

        $kelas = $this->makeProduct($store, [
            'type' => ProductType::Course,
            'name' => 'Kelas Editing Reels dari Nol',
            'short_description' => '12 materi video, dari potong klip sampai color grading.',
            'price' => 349000,
            'compare_at_price' => 499000,
            'affiliate_enabled' => true,
        ]);

        $this->buildCourse($kelas);

        $this->makeProduct($store, [
            'type' => ProductType::Donation,
            'name' => 'Traktir Kopi',
            'short_description' => 'Kalau kontenku membantu, boleh banget ditraktir.',
            'price' => 25000,
            'is_pay_what_you_want' => true,
            'minimum_price' => 10000,
        ]);

        $this->enableAffiliateProgram($store, 25);
        $this->fillBlocks($store);

        return $store;
    }

    private function createRuangDesain(): Store
    {
        $owner = $this->makeUser('Raka Adiputra', 'rakadesain', 'desain@jualanyok.test', [Role::CREATOR]);

        $store = $this->provisionStore($owner, 'ruangdesain', 'RuangDesain', 'freelancer-jasa', [
            'tagline' => 'Bantu brand kamu punya identitas yang jelas',
            'bio' => 'Desainer brand, 5 tahun bantu UMKM dan startup Indonesia bikin identitas visual.',
        ]);

        app(PlanService::class)->subscribe($owner, Plan::where('slug', Plan::PRO)->first());

        $bundle = $this->makeProduct($store, [
            'type' => ProductType::Digital,
            'name' => 'Bundle Template Brand Guideline',
            'short_description' => '40 halaman siap edit di Figma dan Canva.',
            'price' => 199000,
            'affiliate_enabled' => true,
        ]);

        ProductFile::updateOrCreate(
            ['product_id' => $bundle->id, 'name' => 'brand-guideline-bundle.zip'],
            [
                'disk' => 'local',
                'path' => 'demo/brand-guideline-bundle.zip',
                'mime_type' => 'application/zip',
                'size' => 18_500_000,
                'version' => '1.0',
            ],
        );

        $konsultasi = $this->makeProduct($store, [
            'type' => ProductType::Service,
            'name' => 'Konsultasi Branding 1-on-1',
            'short_description' => 'Sesi 60 menit buat bedah brand kamu.',
            'price' => 450000,
        ]);

        $konsultasi->service()->updateOrCreate([], [
            'duration_minutes' => 60,
            'buffer_minutes' => 15,
            'lead_time_hours' => 24,
            'meeting_url' => 'https://meet.google.com/demo-ruang-desain',
        ]);

        foreach ([1, 2, 3, 4, 5] as $day) {
            $konsultasi->service->availabilityRules()->updateOrCreate(
                ['day_of_week' => $day, 'start_time' => '09:00:00'],
                ['end_time' => '17:00:00'],
            );
        }

        $membership = $this->makeProduct($store, [
            'type' => ProductType::Membership,
            'name' => 'Member Ruang Desain',
            'short_description' => 'Akses asset bulanan, review karya, dan grup diskusi.',
            'price' => 99000,
        ]);

        $membership->membershipPlans()->updateOrCreate(
            ['name' => 'Bulanan'],
            ['price' => 99000, 'interval' => 'monthly', 'benefits' => ['Asset pack bulanan', 'Review karya', 'Grup diskusi']],
        );

        $webinar = $this->makeProduct($store, [
            'type' => ProductType::Event,
            'name' => 'Webinar: Rebranding Tanpa Ganti Logo',
            'short_description' => 'Sesi live 2 jam plus tanya jawab.',
            'price' => 75000,
        ]);

        $webinar->event()->updateOrCreate([], [
            'mode' => 'online',
            'starts_at' => now()->addWeeks(2)->setTime(19, 0),
            'ends_at' => now()->addWeeks(2)->setTime(21, 0),
            'capacity' => 100,
            'meeting_url' => 'https://zoom.us/j/demo-ruang-desain',
        ]);

        $this->enableAffiliateProgram($store, 15);
        $this->fillBlocks($store);

        return $store;
    }

    private function createRacunStyle(): Store
    {
        $owner = $this->makeUser('Vina Larasati', 'vinaracun', 'fisik@jualanyok.test', [Role::CREATOR]);

        $store = $this->provisionStore($owner, 'racunstyle', 'RacunStyle', 'fashion-fisik', [
            'tagline' => 'Racun fashion buat anak muda',
            'bio' => 'Kurasi outfit harian yang nyaman, terjangkau, dan nggak pasaran.',
        ]);

        $kaos = $this->makeProduct($store, [
            'type' => ProductType::Physical,
            'name' => 'Kaos Oversize Cotton Combed 30s',
            'short_description' => 'Bahan adem, jahitan rapi, nggak melar.',
            'price' => 129000,
            'compare_at_price' => 159000,
            'affiliate_enabled' => true,
        ]);

        foreach ([['S', 12], ['M', 20], ['L', 18], ['XL', 6]] as [$size, $stock]) {
            $variant = ProductVariant::updateOrCreate(
                ['product_id' => $kaos->id, 'name' => "Hitam / {$size}"],
                ['options' => ['Warna' => 'Hitam', 'Ukuran' => $size], 'stock' => $stock, 'weight_gram' => 250],
            );

            Inventory::updateOrCreate(
                ['product_id' => $kaos->id, 'product_variant_id' => $variant->id],
                ['quantity' => $stock, 'track_stock' => true, 'low_stock_threshold' => 5],
            );
        }

        $tote = $this->makeProduct($store, [
            'type' => ProductType::Physical,
            'name' => 'Tote Bag Kanvas Tebal',
            'short_description' => 'Muat laptop 14 inci, tetap ringan.',
            'price' => 79000,
            'affiliate_enabled' => true,
        ]);

        Inventory::updateOrCreate(
            ['product_id' => $tote->id, 'product_variant_id' => null],
            ['quantity' => 35, 'track_stock' => true],
        );

        // External affiliate pick — clicked through to another marketplace.
        $this->makeProduct($store, [
            'type' => ProductType::External,
            'name' => 'Sepatu Andalan Sehari-hari (Affiliate)',
            'short_description' => 'Yang aku pakai hampir tiap hari. Beli lewat link ini ya.',
            'price' => 0,
            'external_url' => 'https://shopee.co.id/search?keyword=sepatu%20sneakers',
        ]);

        $this->enableAffiliateProgram($store, 20);
        $this->fillBlocks($store);

        return $store;
    }

    private function createAffiliate(Store ...$stores): User
    {
        $user = $this->makeUser('Bagas Affiliator', 'bagasafil', 'affiliate@jualanyok.test', [Role::AFFILIATE, Role::CUSTOMER]);
        $user->forceFill(['is_affiliate' => true])->save();

        $affiliates = app(AffiliateService::class);

        foreach ($stores as $store) {
            foreach ($store->products()->where('affiliate_enabled', true)->get() as $product) {
                $program = $affiliates->programFor($product);

                if (! $program) {
                    continue;
                }

                $program->applications()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['status' => 'APPROVED', 'reviewed_at' => now()],
                );

                $link = $affiliates->linkFor($program, $user->id, $product, 'ig-story');

                // Seed some click history so the dashboard is not empty.
                $clicks = random_int(15, 60);
                $link->update(['clicks' => $clicks]);

                $affiliates->trackClick($link, ['device' => 'mobile', 'referrer' => 'https://instagram.com']);
            }
        }

        return $user->fresh();
    }

    /* ------------------------------------------------------------------ */

    private function provisionStore(User $owner, string $username, string $name, string $templateSlug, array $extra): Store
    {
        if ($existing = $owner->store) {
            return $existing;
        }

        $template = StorefrontTemplate::where('slug', $templateSlug)->first();

        $palette = $template->theme ?? [];
        $primary = $palette['primary_color'] ?? null;
        $accent = $palette['accent_color'] ?? null;

        $store = app(StoreProvisionService::class)->create($owner, [
            'username' => Username::normalize($username),
            'name' => $name,
            'avatar_path' => CoverArt::avatar($name, $primary, $accent),
            'cover_path' => CoverArt::cover($name, $primary, $accent),
            'tagline' => $extra['tagline'] ?? null,
            'bio' => $extra['bio'] ?? null,
            'whatsapp' => '628'.random_int(100000000, 999999999),
            'socials' => [
                'instagram' => "https://instagram.com/{$username}",
                'tiktok' => "https://tiktok.com/@{$username}",
            ],
        ], $template);

        app(StoreProvisionService::class)->publish($store);

        return $store->fresh(['theme', 'blocks']);
    }

    private function makeProduct(Store $store, array $attributes): Product
    {
        $type = $attributes['type'];

        return Product::updateOrCreate(
            ['store_id' => $store->id, 'slug' => Str::slug($attributes['name'])],
            array_merge([
                'type' => $type,
                'status' => ProductStatus::Active,
                'visibility' => 'public',
                'currency' => 'IDR',
                'product_category_id' => ProductCategory::inRandomOrder()->value('id'),
                'sku' => Str::upper(Str::random(8)),
                'thumbnail_path' => CoverArt::product($attributes['name'], $type->label()),
            ], $attributes),
        );
    }

    private function buildCourse(Product $product): void
    {
        /** @var Course $course */
        $course = $product->course()->updateOrCreate([], [
            'level' => 'beginner',
            'outcome' => 'Setelah kelas ini kamu bisa bikin reels dari mentah sampai siap posting.',
            'certificate_enabled' => true,
        ]);

        $sections = [
            ['Dasar-dasar', [
                ['Kenalan dengan tools', 8, true, 0],
                ['Impor dan potong klip', 12, false, 0],
                ['Ritme dan timing', 15, false, 0],
            ]],
            ['Bikin Menarik', [
                ['Transisi yang nggak lebay', 14, false, 2],
                ['Teks dan caption on-screen', 11, false, 2],
                ['Pilih audio yang pas', 9, false, 3],
            ]],
            ['Finishing', [
                ['Color grading sederhana', 18, false, 5],
                ['Ekspor tanpa turun kualitas', 10, false, 5],
            ]],
        ];

        foreach ($sections as $i => [$title, $lessons]) {
            $section = $course->sections()->updateOrCreate(
                ['title' => $title],
                ['position' => $i],
            );

            foreach ($lessons as $j => [$lessonTitle, $duration, $free, $drip]) {
                $section->lessons()->updateOrCreate(
                    ['title' => $lessonTitle],
                    [
                        'type' => 'video',
                        'duration_minutes' => $duration,
                        'is_free_preview' => $free,
                        'drip_days' => $drip,
                        'position' => $j,
                        'body' => 'Catatan materi untuk '.$lessonTitle.'.',
                    ],
                );
            }
        }
    }

    private function enableAffiliateProgram(Store $store, float $percent): void
    {
        AffiliateProgram::updateOrCreate(
            ['store_id' => $store->id, 'product_id' => null],
            [
                'commission_type' => 'percentage',
                'commission_value' => $percent,
                'cookie_days' => 30,
                'auto_approve' => true,
                'is_active' => true,
                'terms' => 'Dilarang memakai iklan berbayar dengan kata kunci nama brand.',
            ],
        );
    }

    /** Fills template placeholders with real product references and copy. */
    private function fillBlocks(Store $store): void
    {
        $products = $store->products()->active()->get();

        foreach ($store->blocks as $block) {
            $content = $block->content ?? [];

            if ($block->type->value === 'PRODUCT_COLLECTION' && empty($content['product_ids'])) {
                $content['product_ids'] = $products->pluck('id')->take(4)->all();
            }

            if ($block->type->value === 'SOCIAL_LINKS') {
                $content['links'] = $store->socials ?? [];
            }

            if ($block->type->value === 'WHATSAPP_CTA') {
                $content['number'] = $store->whatsapp;
            }

            if ($block->type->value === 'COUNTDOWN' && empty($content['ends_at'])) {
                $content['ends_at'] = now()->addDays(5)->toIso8601String();
            }

            $block->update(['content' => $content, 'draft_content' => $content]);
        }

        // Every demo store gets a lead form and FAQ even if its template lacks one.
        foreach (['LEAD_FORM', 'FAQ'] as $type) {
            if (! $store->blocks()->where('type', $type)->exists()) {
                Block::create([
                    'store_id' => $store->id,
                    'type' => $type,
                    'position' => $store->blocks()->max('position') + 1,
                    'is_published' => true,
                    'content' => $type === 'LEAD_FORM'
                        ? ['headline' => 'Mau dikabarin promo?', 'button_label' => 'Gabung']
                        : ['items' => [['question' => 'Berapa lama prosesnya?', 'answer' => 'Maksimal 1×24 jam kerja.']]],
                    'draft_content' => null,
                ]);
            }
        }

        $store->refresh();
    }

    /* ------------------------------------------------------------------ */

    /**
     * Drives real checkouts through the production services, then settles them
     * with a simulated gateway callback.
     */
    private function simulateSales(Store $store, ?User $affiliate, int $count = 6): void
    {
        $checkout = app(CheckoutService::class);
        $payments = app(PaymentService::class);

        $products = $store->products()
            ->active()
            ->where('type', '!=', ProductType::External->value)
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        $buyers = [
            ['Rina Pembeli', 'pembeli@jualanyok.test'],
            ['Dimas Prakoso', 'dimas@contoh.test'],
            ['Sari Wulandari', 'sari@contoh.test'],
            ['Yoga Pratama', 'yoga@contoh.test'],
            ['Nadia Kusuma', 'nadia@contoh.test'],
            ['Bayu Saputra', 'bayu@contoh.test'],
        ];

        for ($i = 0; $i < $count; $i++) {
            [$name, $email] = $buyers[$i % count($buyers)];
            $product = $products[$i % $products->count()];

            $line = ['product_id' => $product->id, 'quantity' => random_int(1, 2)];

            if ($product->type->tracksStock()) {
                $variant = $product->variants()->where('is_active', true)->first();

                if ($variant) {
                    $line['variant_id'] = $variant->id;
                }
            }

            if ($product->is_pay_what_you_want) {
                $line['price'] = 25000;
            }

            try {
                $order = $checkout->createOrder(
                    $store,
                    [$line],
                    ['name' => $name, 'email' => $email, 'marketing_consent' => $i % 2 === 0],
                    [
                        'idempotency_key' => "demo-{$store->id}-{$i}",
                        'affiliate_code' => $affiliate && $i % 3 === 0
                            ? $affiliate->affiliateLinks()
                                ->whereHas('program', fn ($q) => $q->where('store_id', $store->id))
                                ->value('code')
                            : null,
                        'ip' => '127.0.0.1',
                    ],
                );
            } catch (\Throwable $e) {
                continue; // Out of stock or already seeded — skip this one.
            }

            $payment = $payments->createPayment($order, 'mock', 'qris', 'qris');

            // Backdate so some revenue has already matured past the hold period.
            $paidAt = now()->subDays(max(0, 20 - $i * 3));

            $payments->applyResult(new PaymentResult(
                status: PaymentStatus::Paid,
                reference: $payment->reference,
                amount: (float) $payment->amount,
                paidAt: $paidAt,
                eventId: "demo-event-{$store->id}-{$i}",
            ), 'mock');

            $order->fresh()->update(['created_at' => $paidAt, 'paid_at' => $paidAt]);
        }

        // Mature what is old enough, exactly as the scheduler would.
        app(WithdrawalService::class)->releaseMaturedRevenue();
        app(AffiliateService::class)->releaseMatured();
    }

    private function buildAnalytics(array $stores): void
    {
        $analytics = app(AnalyticsService::class);

        foreach ($stores as $store) {
            for ($day = 29; $day >= 0; $day--) {
                $date = now()->subDays($day);

                $store->analyticsSummaries()->updateOrCreate(
                    ['date' => $date->toDateString()],
                    [
                        'views' => random_int(40, 320),
                        'unique_visitors' => random_int(30, 240),
                        'product_views' => random_int(10, 90),
                        'block_clicks' => random_int(5, 60),
                        'checkouts' => random_int(1, 12),
                        'leads' => random_int(0, 8),
                        'sources' => [
                            'instagram' => random_int(10, 120),
                            'tiktok' => random_int(5, 90),
                            'direct' => random_int(5, 60),
                            'google' => random_int(0, 25),
                        ],
                    ],
                );
            }

            // Overlay the real order totals so revenue in the chart matches the
            // ledger rather than being invented.
            $store->orders()
                ->paid()
                ->get()
                ->groupBy(fn ($order) => $order->paid_at->toDateString())
                ->each(function ($orders, $date) use ($store) {
                    $store->analyticsSummaries()
                        ->where('date', $date)
                        ->update([
                            'orders' => $orders->count(),
                            'gross_revenue' => $orders->sum('grand_total'),
                            'net_revenue' => $orders->sum('seller_net'),
                        ]);
                });
        }
    }
}
