<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_creator_can_adjust_physical_stock_with_an_audit_movement(): void
    {
        $owner = $this->makeUser([Role::CREATOR]);
        $product = $this->makeProduct($this->makeStore($owner), [
            'type' => ProductType::Physical,
            'stock' => 8,
        ]);
        $inventory = $product->inventories()->firstOrFail();

        $this->actingAs($owner)
            ->patch("/dashboard/produk/{$product->id}/stok", [
                'inventory_id' => $inventory->id,
                'quantity' => 20,
                'low_stock_threshold' => 4,
                'reason' => 'restock',
                'note' => 'Barang masuk dari gudang utama.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 20,
            'low_stock_threshold' => 4,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_id' => $inventory->id,
            'change' => 12,
            'balance_after' => 20,
            'reason' => 'restock',
            'user_id' => $owner->id,
        ]);
    }

    public function test_physical_product_can_start_with_real_stock(): void
    {
        $owner = $this->makeUser([Role::CREATOR]);
        $store = $this->makeStore($owner);

        $this->actingAs($owner)->post('/dashboard/produk', [
            'type' => ProductType::Physical->value,
            'name' => 'Sepatu Baru',
            'price' => 350000,
            'status' => 'DRAFT',
            'visibility' => 'public',
            'initial_stock' => 14,
            'min_quantity' => 1,
            'weight_gram' => 800,
            'length_cm' => 32,
            'width_cm' => 20,
            'height_cm' => 12,
            'shipping_category' => 'fashion',
        ])->assertSessionHasNoErrors();

        $product = Product::where('store_id', $store->id)->where('name', 'Sepatu Baru')->firstOrFail();
        $inventory = $product->inventories()->firstOrFail();

        $this->assertSame(14, $inventory->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_id' => $inventory->id,
            'change' => 14,
            'reason' => 'initial',
            'user_id' => $owner->id,
        ]);
    }

    public function test_stock_cannot_be_lowered_below_units_reserved_by_buyers(): void
    {
        $owner = $this->makeUser([Role::CREATOR]);
        $product = $this->makeProduct($this->makeStore($owner), [
            'type' => ProductType::Physical,
            'stock' => 8,
        ]);
        $inventory = $product->inventories()->firstOrFail();
        $inventory->update(['reserved' => 3]);

        $this->actingAs($owner)
            ->patch("/dashboard/produk/{$product->id}/stok", [
                'inventory_id' => $inventory->id,
                'quantity' => 2,
                'low_stock_threshold' => 3,
                'reason' => 'correction',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(8, $inventory->fresh()->quantity);
    }

    public function test_creator_cannot_adjust_inventory_owned_by_another_store(): void
    {
        $owner = $this->makeUser([Role::CREATOR]);
        $other = $this->makeUser([Role::CREATOR]);
        $mine = $this->makeProduct($this->makeStore($owner), ['type' => ProductType::Physical]);
        $theirs = $this->makeProduct($this->makeStore($other), ['type' => ProductType::Physical]);

        $this->actingAs($owner)
            ->patch("/dashboard/produk/{$mine->id}/stok", [
                'inventory_id' => $theirs->inventories()->firstOrFail()->id,
                'quantity' => 99,
                'low_stock_threshold' => 3,
                'reason' => 'restock',
            ])
            ->assertNotFound();
    }
}
