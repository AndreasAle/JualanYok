<?php

namespace Tests;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\AffiliateProgram;
use App\Models\Inventory;
use App\Models\PayoutMethod;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\Role;
use App\Models\Store;
use App\Models\StoreTheme;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\PlatformSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /** Seeds only the platform reference data — never the demo content. */
    protected function seedPlatform(): void
    {
        $this->seed([RolePermissionSeeder::class, PlanSeeder::class, PlatformSeeder::class]);
    }

    protected function makeUser(array $roles = [Role::CUSTOMER], array $attributes = []): User
    {
        $suffix = Str::lower(Str::random(8));

        $user = User::create(array_merge([
            'name' => 'Test User',
            'username' => "user{$suffix}",
            'email' => "{$suffix}@example.test",
            'password' => 'password123',
            'email_verified_at' => now(),
            'tos_accepted_at' => now(),
        ], $attributes));

        $user->roles()->attach(Role::whereIn('slug', $roles)->pluck('id'));
        $user->walletOrCreate();

        return $user->fresh();
    }

    protected function makeStore(?User $owner = null, array $attributes = []): Store
    {
        $owner ??= $this->makeUser([Role::CREATOR]);

        $store = Store::create(array_merge([
            'user_id' => $owner->id,
            'username' => $owner->username,
            'name' => 'Toko '.$owner->username,
            'is_published' => true,
            'published_at' => now(),
        ], $attributes));

        StoreTheme::create(['store_id' => $store->id]);
        $owner->forceFill(['is_creator' => true])->save();

        return $store->fresh();
    }

    protected function makeProduct(Store $store, array $attributes = []): Product
    {
        $name = $attributes['name'] ?? 'Produk '.Str::random(6);
        $stock = $attributes['stock'] ?? 5;
        // Digital products need a file to be sellable; tests that specifically
        // exercise the empty case opt out with 'without_files' => true.
        $withoutFiles = (bool) ($attributes['without_files'] ?? false);
        unset($attributes['stock'], $attributes['without_files']);

        $product = Product::create(array_merge([
            'store_id' => $store->id,
            'type' => ProductType::Digital,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'price' => 100000,
            'status' => ProductStatus::Active,
            'visibility' => 'public',
        ], $attributes));

        if ($product->type === ProductType::Digital && ! $withoutFiles) {
            ProductFile::create([
                'product_id' => $product->id,
                'name' => 'berkas.pdf',
                'disk' => 'local',
                'path' => "stores/{$store->id}/products/{$product->id}/berkas.pdf",
                'mime_type' => 'application/pdf',
                'size' => 1024,
            ]);
        }

        if ($product->type->tracksStock()) {
            Inventory::create([
                'product_id' => $product->id,
                'quantity' => $stock,
                'track_stock' => true,
            ]);
        }

        return $product->fresh();
    }

    protected function makeAffiliateProgram(Store $store, float $percent = 20): AffiliateProgram
    {
        return AffiliateProgram::create([
            'store_id' => $store->id,
            'product_id' => null,
            'commission_type' => 'percentage',
            'commission_value' => $percent,
            'cookie_days' => 30,
            'auto_approve' => true,
            'is_active' => true,
        ]);
    }

    protected function makeVerifiedPayoutMethod(User $user): PayoutMethod
    {
        return PayoutMethod::create([
            'user_id' => $user->id,
            'type' => 'bank',
            'provider' => 'BCA',
            'account_name' => $user->name,
            'account_number' => '1234567890',
            'account_number_last4' => '7890',
            'is_default' => true,
            'status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    protected function freePlan(): Plan
    {
        return Plan::where('slug', Plan::FREE)->firstOrFail();
    }
}
