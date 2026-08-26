<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\StoreShippingProfile;
use App\Models\User;
use App\Payments\PaymentResult;
use App\Services\CheckoutService;
use App\Services\DisputeService;
use App\Services\FulfillmentService;
use App\Services\PaymentService;
use App\Services\ShippingService;
use App\Shipping\Providers\BiteshipShippingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhysicalMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        config()->set('shipping.default', 'manual');
        config()->set('shipping.providers.manual.flat_rate', 18000);
    }

    public function test_physical_order_keeps_revenue_in_escrow_until_buyer_confirms_receipt(): void
    {
        [$order, $owner] = $this->paidPhysicalOrder();

        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertNull($order->funds_release_at);

        $shipment = app(ShippingService::class)->createShipment($order);
        $this->assertInstanceOf(Shipment::class, $shipment);

        app(FulfillmentService::class)->markShipped($order, 'RESI-001', 'JNE Reguler');
        app(FulfillmentService::class)->markDelivered($order->fresh('items'));

        $order->refresh()->load('items');
        $this->assertTrue($order->canBuyerConfirmReceipt());
        $this->assertNotNull($order->complaint_deadline_at);

        app(FulfillmentService::class)->confirmReceived($order);

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertNotNull($order->buyer_confirmed_at);
        $this->assertNotNull($order->funds_release_at);
        $this->assertGreaterThan(0, $owner->wallet->fresh()->pending_balance);
    }

    public function test_dispute_holds_funds_and_admin_can_route_buyer_win_to_refund_queue(): void
    {
        [$order] = $this->paidPhysicalOrder();
        app(FulfillmentService::class)->markShipped($order, 'RESI-002', 'JNE Reguler');

        $dispute = app(DisputeService::class)->open(
            $order->fresh('items'),
            'damaged',
            'Barang tiba dalam kondisi rusak dan tidak dapat dipakai.',
        );

        $this->assertSame(OrderStatus::Disputed, $order->fresh()->status);
        $this->assertNull($order->fresh()->funds_release_at);

        $admin = $this->makeUser([Role::FINANCE_ADMIN]);
        app(DisputeService::class)->resolve($dispute, $admin, 'buyer', 'Bukti kerusakan dan perjalanan paket mendukung pembeli.');

        $this->assertSame('buyer', $dispute->fresh()->resolution);
        $this->assertSame(1, Refund::where('order_id', $order->id)->where('status', 'REQUESTED')->count());
    }

    public function test_biteship_quote_and_booking_use_real_marketplace_shipping_contract(): void
    {
        config()->set('shipping.providers.biteship.token', 'test-token');
        config()->set('shipping.providers.biteship.enabled', true);
        config()->set('shipping.providers.biteship.base_url', 'https://api.biteship.test/v1');

        Http::fake([
            'https://api.biteship.test/*rates/couriers' => Http::response([
                'pricing' => [[
                    'available_for_order' => true,
                    'courier_code' => 'jne',
                    'courier_name' => 'JNE',
                    'courier_service_code' => 'reg',
                    'courier_service_name' => 'Reguler',
                    'price' => 12000,
                    'courier_insurance_fee' => 1000,
                    'duration' => '2 - 3 days',
                ]],
            ]),
            'https://api.biteship.test/*orders' => Http::response([
                'data' => [
                    'id' => 'biteship-order-1',
                    'status' => 'confirmed',
                    'price' => 13000,
                    'courier' => ['tracking_id' => 'track-1'],
                ],
            ]),
        ]);

        [$order] = $this->paidPhysicalOrder();
        $profile = $order->store->shippingProfile;
        $profile->update([
            'area_id' => 'IDNP6IDNC148IDND780IDZ40123',
            'collection_method' => 'drop_off',
            'enabled_couriers' => ['jne'],
            'default_insurance' => true,
        ]);

        $provider = app(BiteshipShippingProvider::class);
        $items = [[
            'name' => 'Produk Fisik', 'description' => 'Produk Fisik', 'value' => 125000,
            'quantity' => 1, 'weight' => 500, 'category' => 'others',
        ]];
        $quotes = $provider->quote($profile->fresh(), ['area_id' => 'destination-area'], $items);

        $this->assertSame(12000.0, $quotes[0]['delivery_fee']);
        $this->assertSame(1000.0, $quotes[0]['insurance_fee']);
        $this->assertSame(13000.0, $quotes[0]['amount']);
        $this->assertSame(125000, $quotes[0]['insurance_value']);

        $order->update([
            'shipping_provider' => 'biteship',
            'shipping_quote' => $quotes[0],
        ]);
        $result = $provider->createShipment($order->fresh(['store', 'items.product', 'items.variant']), $profile->fresh());

        $this->assertSame('biteship-order-1', $result['external_id']);
        Http::assertSent(function ($request) use ($order) {
            $this->assertSame('Basic '.base64_encode('test-token:'), $request->header('Authorization')[0] ?? null);

            if (! str_ends_with($request->url(), '/v1/orders')) {
                return true;
            }

            $payload = $request->data();

            return $payload['origin_collection_method'] === 'drop_off'
                && $payload['delivery_type'] === 'now'
                && $payload['courier_insurance'] === 125000
                && $payload['reference_id'] === $order->number
                && $payload['items'][0]['category'] === 'others';
        });
    }

    public function test_storefront_area_search_explains_biteship_authentication_failure(): void
    {
        config()->set('shipping.default', 'biteship');
        config()->set('shipping.providers.biteship.enabled', true);
        config()->set('shipping.providers.biteship.token', 'invalid-live-token');
        config()->set('shipping.providers.biteship.base_url', 'https://api.biteship.test/v1');

        Http::fake([
            'https://api.biteship.test/*maps/areas*' => Http::response([
                'success' => false,
                'message' => 'Authorization failed',
            ], 401),
        ]);

        $store = $this->makeStore();

        $this->getJson(route('storefront.shipping.areas', [
            'store' => $store,
            'q' => 'palembang',
        ]))
            ->assertStatus(503)
            ->assertJson([
                'code' => 'SHIPPING_AUTH_FAILED',
                'message' => 'Koneksi Biteship belum valid. Admin perlu memperbarui token API pengiriman.',
            ]);
    }

    /** @return array{0:Order,1:User} */
    private function paidPhysicalOrder(): array
    {
        $owner = $this->makeUser([Role::CREATOR]);
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, [
            'type' => ProductType::Physical,
            'price' => 125000,
            'weight_gram' => 500,
            'length_cm' => 20,
            'width_cm' => 15,
            'height_cm' => 8,
        ]);

        StoreShippingProfile::create([
            'store_id' => $store->id,
            'contact_name' => $owner->name,
            'contact_phone' => '081234567890',
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
            ['name' => 'Pembeli Fisik', 'email' => 'fisik@example.test', 'phone' => '081298765432'],
            [
                'idempotency_key' => 'physical-'.str()->random(8),
                'shipping_total' => 18000,
                'shipping_provider' => 'manual',
                'shipping_method' => 'Reguler',
                'shipping_courier' => 'seller',
                'shipping_courier_type' => 'regular',
                'shipping_quote' => ['courier_company' => 'seller', 'courier_type' => 'regular'],
                'shipping_address' => [
                    'address_line' => 'Jl. Pembeli Nomor 2', 'city' => 'Jakarta', 'province' => 'DKI Jakarta',
                    'postal_code' => '10110', 'area_id' => 'manual-jakarta',
                ],
            ],
        );

        $payment = app(PaymentService::class)->createPayment($order, 'mock', 'qris', 'qris');
        app(PaymentService::class)->applyResult(new PaymentResult(
            status: PaymentStatus::Paid,
            reference: $payment->reference,
            amount: (float) $payment->amount,
            paidAt: now(),
            eventId: 'evt-'.$order->id,
        ), 'mock');

        return [$order->fresh('items'), $owner->fresh('wallet')];
    }
}
