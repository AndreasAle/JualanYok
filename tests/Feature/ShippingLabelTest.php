<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\StoreShippingProfile;
use App\Models\User;
use App\Services\CheckoutService;
use App\Support\Code128Barcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ShippingLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_creator_can_print_a_private_jualanyok_shipping_label(): void
    {
        [$order, $owner] = $this->physicalOrder();
        $shipment = Shipment::create([
            'order_id' => $order->id,
            'provider' => 'biteship',
            'external_id' => 'biteship-order-100',
            'waybill_id' => 'TIKIBTS100000135408',
            'courier_company' => 'tiki',
            'courier_type' => 'reg',
            'courier_name' => 'Reguler',
            'status' => ShipmentStatus::Confirmed,
            'quoted_price' => 7000,
            'request_payload' => [
                'origin_contact_name' => 'Toko Pengirim',
                'origin_contact_phone' => '081111111111',
                'origin_address' => 'Jl. Gudang Nomor 1, Bandung',
                'origin_postal_code' => '40123',
                'destination_contact_name' => 'Paskalia Revita',
                'destination_contact_phone' => '0895632271825',
                'destination_address' => 'Jl. Batu Karang Nomor 7',
                'destination_postal_code' => '30161',
                'destination_note' => 'Rumah pagar hitam',
                'items' => [[
                    'name' => 'Baju Atasan', 'description' => 'Baju Atasan', 'quantity' => 1,
                    'weight' => 500, 'length' => 20, 'width' => 15, 'height' => 10,
                ]],
            ],
            'provider_response' => ['courier' => ['routing_code' => 'PLM-01']],
        ]);

        $response = $this->actingAs($owner)->get(route('creator.orders.shipment.label', $order));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('Cetak / Simpan PDF')
            ->assertSee($shipment->waybill_id)
            ->assertSee('Paskalia Revita')
            ->assertSee('Baju Atasan')
            ->assertSee('Ongkir Rp7.000')
            ->assertSee('LUNAS · NON-COD')
            ->assertSee($order->tracking_code)
            ->assertSee('aria-label="Barcode nomor resi TIKIBTS100000135408"', false);
    }

    public function test_shipping_label_is_private_to_the_store_owner(): void
    {
        [$order] = $this->physicalOrder();
        Shipment::create([
            'order_id' => $order->id,
            'provider' => 'biteship',
            'waybill_id' => 'PRIVATE-RESI-001',
            'status' => ShipmentStatus::Confirmed,
        ]);
        $otherCreator = $this->makeUser([Role::CREATOR]);
        $this->makeStore($otherCreator);

        $this->actingAs($otherCreator)
            ->get(route('creator.orders.shipment.label', $order))
            ->assertForbidden();
    }

    public function test_label_waits_until_a_waybill_exists(): void
    {
        [$order, $owner] = $this->physicalOrder();

        $this->actingAs($owner)
            ->get(route('creator.orders.shipment.label', $order))
            ->assertStatus(409);
    }

    public function test_code128_rejects_non_printable_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Code128Barcode::svg("RESI\nPALSU");
    }

    /** @return array{0:Order,1:User} */
    private function physicalOrder(): array
    {
        $owner = $this->makeUser([Role::CREATOR]);
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, [
            'type' => ProductType::Physical,
            'name' => 'Baju Atasan',
            'price' => 10000,
            'weight_gram' => 500,
            'length_cm' => 20,
            'width_cm' => 15,
            'height_cm' => 10,
        ]);

        StoreShippingProfile::create([
            'store_id' => $store->id,
            'contact_name' => 'Toko Pengirim',
            'contact_phone' => '081111111111',
            'address_line' => 'Jl. Gudang Nomor 1',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40123',
            'collection_method' => 'pickup',
            'is_active' => true,
        ]);

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Paskalia Revita', 'email' => 'paskalia@example.test', 'phone' => '0895632271825'],
            [
                'idempotency_key' => 'label-'.str()->random(8),
                'shipping_total' => 7000,
                'shipping_provider' => 'biteship',
                'shipping_method' => 'Reguler',
                'shipping_courier' => 'tiki',
                'shipping_courier_type' => 'reg',
                'shipping_quote' => ['courier_company' => 'tiki', 'courier_type' => 'reg'],
                'shipping_address' => [
                    'address_line' => 'Jl. Batu Karang Nomor 7',
                    'district' => 'Sematang Borang',
                    'city' => 'Palembang',
                    'province' => 'Sumatera Selatan',
                    'postal_code' => '30161',
                    'area_id' => 'destination-palembang',
                ],
            ],
        );

        return [$order->fresh('items'), $owner];
    }
}
