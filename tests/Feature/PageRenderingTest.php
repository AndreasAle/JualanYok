<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Role;
use App\Models\StaticPage;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\StorefrontTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Renders every screen against the seeded demo data. This is the guard that
 * catches a controller shipping a prop the page does not expect, or a page
 * file that does not exist at all.
 */
class PageRenderingTest extends TestCase
{
    use RefreshDatabase;

    private static bool $seeded = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
        $this->seed(StorefrontTemplateSeeder::class);
        $this->seed(DemoSeeder::class);
    }

    private function creator(): User
    {
        return User::where('email', 'kreator@jualanyok.test')->firstOrFail();
    }

    private function superAdmin(): User
    {
        return User::where('email', 'admin@jualanyok.test')->firstOrFail();
    }

    private function financeAdmin(): User
    {
        return User::where('email', 'finance@jualanyok.test')->firstOrFail();
    }

    private function affiliate(): User
    {
        return User::where('email', 'affiliate@jualanyok.test')->firstOrFail();
    }

    public function test_marketing_pages_render(): void
    {
        foreach (['/', '/pricing', '/features', '/templates', '/templates/creator-digital/demo', '/contact', '/faq', '/terms', '/privacy', '/refund-policy'] as $uri) {
            $this->get($uri)->assertOk();
        }

        $this->get('/faq')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Marketing/Faq')
            ->where('business.name', config('jualanyok.business.name'))
        );

        foreach (['terms', 'privacy', 'refund-policy'] as $slug) {
            $this->assertStringNotContainsString(
                'contoh isi',
                StaticPage::where('slug', $slug)->firstOrFail()->body,
            );
        }
    }

    public function test_auth_pages_render(): void
    {
        foreach (['/login', '/register', '/forgot-password', '/masuk-pembeli'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_public_storefronts_and_product_pages_render(): void
    {
        foreach (Store::live()->get() as $store) {
            $this->get("/{$store->username}")->assertOk();

            // Every product, not just the first: variant-bearing products took a
            // different code path and were broken while this test still passed.
            foreach ($store->products()->publiclyListed()->get() as $product) {
                $this->get("/{$store->username}/p/{$product->slug}")
                    ->assertOk("Gagal render halaman produk {$product->name}");
            }
        }
    }

    public function test_an_unpublished_store_is_not_public_but_the_owner_can_preview(): void
    {
        $store = Store::live()->firstOrFail();
        $store->update(['is_published' => false]);

        $this->get("/{$store->username}")->assertNotFound();

        $this->actingAs($store->owner)
            ->get("/{$store->username}/preview")
            ->assertOk();
    }

    public function test_a_stranger_cannot_preview_someone_elses_draft(): void
    {
        $store = Store::live()->firstOrFail();
        $stranger = $this->makeUser([Role::CUSTOMER]);

        $this->actingAs($stranger)
            ->get("/{$store->username}/preview")
            ->assertForbidden();
    }

    public function test_creator_dashboard_pages_render(): void
    {
        $creator = $this->creator();
        $store = $creator->store;
        $product = $store->products()->firstOrFail();
        $order = $store->orders()->firstOrFail();
        $customer = $store->customers()->firstOrFail();

        $pages = [
            '/dashboard',
            '/dashboard/toko',
            '/dashboard/produk',
            '/dashboard/produk/create',
            "/dashboard/produk/{$product->id}/edit",
            '/dashboard/pesanan',
            "/dashboard/pesanan/{$order->number}",
            '/dashboard/pelanggan',
            "/dashboard/pelanggan/{$customer->id}",
            '/dashboard/leads',
            '/dashboard/kupon',
            '/dashboard/kupon/create',
            '/dashboard/affiliate',
            '/dashboard/integrasi',
            '/dashboard/saldo',
            '/dashboard/penarikan',
            '/dashboard/analitik',
            '/dashboard/langganan',
            '/dashboard/pengaturan',
        ];

        foreach ($pages as $uri) {
            $this->actingAs($creator)->get($uri)->assertOk("Gagal render {$uri}");
        }
    }

    public function test_checkout_pages_render(): void
    {
        $order = Order::firstOrFail();

        $this->get("/checkout/{$order->number}")->assertOk();
        $this->get("/checkout/{$order->number}/status")->assertOk();
    }

    public function test_member_area_pages_render(): void
    {
        $buyer = User::where('email', 'pembeli@jualanyok.test')->firstOrFail();

        // Link the guest purchases made with this email to the account.
        Customer::where('email', $buyer->email)->update(['user_id' => $buyer->id]);

        $this->actingAs($buyer)->get('/member')->assertOk();
        $this->actingAs($buyer)->get('/member/pembelian')->assertOk();
        $this->actingAs($buyer)->get('/member/kelas')->assertOk();
        $this->actingAs($buyer)->get('/member/profil')->assertOk();

        $order = Order::where('customer_email', $buyer->email)->first();

        if ($order) {
            $this->actingAs($buyer)->get("/member/pembelian/{$order->number}")->assertOk();
        }
    }

    public function test_a_student_can_open_their_course(): void
    {
        $enrollment = Enrollment::with('customer')->first();

        if (! $enrollment) {
            $this->markTestSkipped('Demo data produced no enrolment.');
        }

        $buyer = User::where('email', $enrollment->customer->email)->first()
            ?? $this->makeUser([Role::CUSTOMER], ['email' => $enrollment->customer->email]);

        $enrollment->customer->update(['user_id' => $buyer->id]);

        $this->actingAs($buyer)
            ->get("/member/kelas/{$enrollment->id}")
            ->assertOk();
    }

    public function test_affiliate_pages_render(): void
    {
        $affiliate = $this->affiliate();

        foreach (['/affiliate', '/affiliate/marketplace', '/affiliate/link', '/affiliate/komisi'] as $uri) {
            $this->actingAs($affiliate)->get($uri)->assertOk("Gagal render {$uri}");
        }
    }

    public function test_admin_pages_render(): void
    {
        $admin = $this->superAdmin();
        $user = User::first();
        $order = Order::firstOrFail();

        $pages = [
            '/admin',
            '/admin/pengguna',
            "/admin/pengguna/{$user->id}",
            '/admin/toko',
            '/admin/pesanan',
            "/admin/pesanan/{$order->number}",
            '/admin/refund',
            '/admin/ledger',
            '/admin/penarikan',
            '/admin/paket',
            '/admin/pengaturan',
            '/admin/audit',
        ];

        foreach ($pages as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk("Gagal render {$uri}");
        }
    }

    public function test_finance_admin_can_reach_the_money_screens(): void
    {
        $finance = $this->financeAdmin();

        foreach (['/admin', '/admin/penarikan', '/admin/refund', '/admin/ledger'] as $uri) {
            $this->actingAs($finance)->get($uri)->assertOk("Gagal render {$uri}");
        }
    }

    public function test_demo_seed_produces_a_populated_storefront(): void
    {
        $store = Store::where('username', 'kreatorkita')->firstOrFail();

        $this->assertGreaterThanOrEqual(8, $store->blocks()->count(), 'Storefront has enough blocks.');
        $this->assertGreaterThan(0, $store->products()->count());
        $this->assertGreaterThan(0, $store->orders()->paid()->count());
        $this->assertGreaterThan(0, $store->customers()->count());
        $this->assertGreaterThan(0, $store->analyticsSummaries()->count());
        $this->assertGreaterThan(0, (float) $store->owner->walletOrCreate()->lifetime_earned);
    }

    public function test_the_builder_sends_each_templates_block_order(): void
    {
        // The template picker draws its miniature from this list, so a missing
        // or empty blueprint would silently reduce every preview to a blank card.
        $creator = $this->creator();

        $this->actingAs($creator)
            ->get('/dashboard/toko')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->has('templates', 7)
                    ->has('templates.0.blocks')
                    ->where('store.storefront_template_id', $creator->store->storefront_template_id),
            );
    }
}
