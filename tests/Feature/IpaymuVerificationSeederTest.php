<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Block;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\IpaymuVerificationSeeder;
use Database\Seeders\StorefrontTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IpaymuVerificationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_an_idempotent_creator_only_review_workspace(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seedPlatform();
        $this->seed(StorefrontTemplateSeeder::class);

        config(['jualanyok.ipaymu_review' => [
            'name' => 'Tim Review iPaymu',
            'email' => 'review@ipaymu.example',
            'username' => 'reviewipaymu',
            'password' => 'Password-Review-123!',
            'store_username' => 'uji-bayar-jualanyok',
            'store_name' => 'Toko Uji Pembayaran',
        ]]);

        $this->seed(IpaymuVerificationSeeder::class);
        $this->seed(IpaymuVerificationSeeder::class);

        $user = User::where('email', 'review@ipaymu.example')->firstOrFail();
        $store = Store::where('user_id', $user->id)->firstOrFail();
        $product = Product::where('store_id', $store->id)->firstOrFail();

        $this->assertTrue(Hash::check('Password-Review-123!', $user->password));
        $this->assertTrue($user->is_creator);
        $this->assertFalse($user->isAdmin());
        $this->assertSame([Role::CREATOR], $user->roles()->pluck('slug')->all());
        $this->assertSame('ipaymu-verification', data_get($user->profile->onboarding_state, 'provisioned_for'));

        $this->assertTrue($store->isLive());
        $this->assertSame('light', $store->theme->color_scheme);
        $this->assertSame(ProductType::Digital, $product->type);
        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertSame('10000.00', $product->price);
        $this->assertTrue($product->isDeliverable());
        $this->assertCount(1, $product->files);
        Storage::disk('local')->assertExists('verification/ipaymu/panduan-uji-pembayaran.txt');

        $this->assertSame(1, User::where('email', 'review@ipaymu.example')->count());
        $this->assertSame(1, Store::where('user_id', $user->id)->count());
        $this->assertSame(1, Product::where('store_id', $store->id)->count());
        $this->assertSame(4, Block::where('store_id', $store->id)->count());

        $this->get(route('storefront.show', $store->username))->assertOk();
        $this->get(route('storefront.product', [$store->username, $product->slug]))->assertOk();
        $this->post(route('login'), [
            'login' => 'review@ipaymu.example',
            'password' => 'Password-Review-123!',
            'remember' => false,
        ])->assertRedirect(route('creator.dashboard'));
    }
}
