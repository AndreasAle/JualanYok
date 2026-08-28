<?php

namespace Tests\Feature;

use App\Enums\MarketplaceStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\AuditLog;
use App\Models\MarketplaceEvent;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CreatorMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->category = ProductCategory::where('slug', 'e-book-panduan')->firstOrFail();
    }

    public function test_only_eligible_products_appear_in_marketplace(): void
    {
        $store = $this->makeStore();
        $approved = $this->approvedProduct($store, ['name' => 'Panduan Lolos Marketplace']);
        $this->approvedProduct($store, ['name' => 'Produk Ditolak', 'marketplace_status' => MarketplaceStatus::Rejected]);
        $this->approvedProduct($store, ['name' => 'Produk Hidden', 'visibility' => 'hidden']);
        $this->approvedProduct($store, ['name' => 'Produk Draft', 'status' => ProductStatus::Draft]);

        $this->get('/explore')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Marketplace/Explore')
            ->has('products.data', 1)
            ->where('products.data.0.id', $approved->id));
    }

    public function test_suspended_creator_and_empty_physical_stock_are_hidden(): void
    {
        $suspendedStore = $this->makeStore();
        $this->approvedProduct($suspendedStore, ['name' => 'Milik Creator Suspend']);
        $suspendedStore->owner->forceFill(['status' => 'suspended'])->save();

        $activeStore = $this->makeStore();
        $this->approvedProduct($activeStore, [
            'name' => 'Stok Kosong',
            'type' => ProductType::Physical,
            'stock' => 0,
        ]);
        $ready = $this->approvedProduct($activeStore, [
            'name' => 'Stok Tersedia',
            'type' => ProductType::Physical,
            'stock' => 3,
        ]);

        $this->get('/explore')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $ready->id));
    }

    public function test_search_matches_product_creator_and_category(): void
    {
        $store = $this->makeStore(attributes: ['name' => 'Studio Angkasa', 'username' => 'studioangkasa']);
        $product = $this->approvedProduct($store, ['name' => 'Workbook Orbit Kreatif']);

        foreach (['Orbit', 'Studio Angkasa', 'E-book'] as $term) {
            $this->get('/explore?q='.urlencode($term))->assertInertia(fn (AssertableInertia $page) => $page
                ->where('products.data.0.id', $product->id));
        }

        $this->assertDatabaseHas('marketplace_search_terms', ['term' => 'orbit']);
    }

    public function test_category_product_detail_and_privacy_analytics_are_connected(): void
    {
        $store = $this->makeStore();
        $product = $this->approvedProduct($store, ['name' => 'Kelas Rapi']);

        $this->get(route('marketplace.categories.show', $this->category->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Marketplace/Explore')
                ->where('category.id', $this->category->id)
                ->where('products.data.0.id', $product->id));

        $this->get(route('marketplace.products.show', [$store->username, $product->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Marketplace/Product')
                ->where('product.id', $product->id)
                ->where('product.store.username', $store->username));

        $event = MarketplaceEvent::where('name', 'marketplace_product_click')->sole();
        $this->assertSame($product->id, $event->product_id);
        $this->assertNotEmpty($event->visitor_hash);
        $this->assertNotEmpty($event->session_hash);
    }

    public function test_marketplace_sitemap_contains_only_eligible_products(): void
    {
        $store = $this->makeStore();
        $approved = $this->approvedProduct($store, ['name' => 'Masuk Sitemap']);
        $rejected = $this->approvedProduct($store, ['name' => 'Jangan Masuk Sitemap', 'marketplace_status' => MarketplaceStatus::Rejected]);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee(route('marketplace.products.show', [$store->username, $approved->slug]), false);
        $response->assertDontSee(route('marketplace.products.show', [$store->username, $rejected->slug]), false);
    }

    public function test_only_authorized_admin_can_moderate_and_action_is_audited(): void
    {
        $store = $this->makeStore();
        $product = $this->approvedProduct($store, [
            'name' => 'Menunggu Review',
            'marketplace_status' => MarketplaceStatus::PendingReview,
        ]);
        $customer = $this->makeUser([Role::CUSTOMER]);

        $this->actingAs($customer)
            ->post(route('admin.marketplace.moderate', $product), ['decision' => 'approve'])
            ->assertForbidden();

        $support = $this->makeUser([Role::SUPPORT_ADMIN]);
        $this->actingAs($support)
            ->post(route('admin.marketplace.moderate', $product), ['decision' => 'approve'])
            ->assertRedirect();

        $this->assertSame(MarketplaceStatus::Approved, $product->fresh()->marketplace_status);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $support->id,
            'action' => 'marketplace.product.approve',
            'auditable_id' => $product->id,
        ]);
        $this->assertSame(1, AuditLog::where('action', 'marketplace.product.approve')->count());
    }

    private function approvedProduct($store, array $attributes = []): Product
    {
        $stock = $attributes['stock'] ?? 5;
        unset($attributes['stock']);

        return $this->makeProduct($store, array_merge([
            'marketplace_category_id' => $this->category->id,
            'is_marketplace_listed' => true,
            'marketplace_status' => MarketplaceStatus::Approved,
            'marketplace_quality_score' => 90,
        ], $attributes, ['stock' => $stock]));
    }
}
