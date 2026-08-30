<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\StoreShippingProfile;
use App\Models\User;
use App\Notifications\BusinessNotification;
use App\Notifications\OrderReceipt;
use App\Payments\PaymentResult;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicOrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        config()->set('shipping.default', 'manual');
        config()->set('shipping.providers.manual.flat_rate', 18000);
    }

    public function test_tracking_landing_page_is_available_for_guests_and_authenticated_users(): void
    {
        $this->get('/lacak')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Marketing/TrackOrder')
                ->where('tracking', null));

        $user = $this->makeUser();

        $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Version', (string) Inertia::getVersion())
            ->get('/lacak')
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Marketing/TrackOrder')
            ->assertJsonPath('props.tracking', null);
    }

    public function test_paid_purchase_gets_a_private_tracking_id_shown_in_email_and_checkout(): void
    {
        [$order] = $this->paidPhysicalOrder();

        $this->assertMatchesRegularExpression('/^JYT-[A-Z0-9]{16}$/', $order->tracking_code);
        $this->assertStringContainsString('/lacak/'.$order->tracking_code, $order->trackingUrl());

        $mail = (new OrderReceipt($order->load(['store', 'items.product', 'digitalAccesses'])))->toMail($order);
        $this->assertStringContainsString($order->tracking_code, implode(' ', $mail->introLines));

        $this->get("/checkout/{$order->number}/status")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.tracking_code', $order->tracking_code)
                ->where('order.tracking_url', $order->trackingUrl()));
    }

    public function test_public_lookup_only_accepts_paid_orders_and_exposes_no_private_buyer_data(): void
    {
        [$paid] = $this->paidPhysicalOrder();
        [$unpaid] = $this->paidPhysicalOrder();
        $unpaid->update([
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $this->post('/lacak', ['tracking_code' => $paid->tracking_code])
            ->assertRedirect($paid->trackingUrl());

        $response = $this->get($paid->trackingUrl())
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Marketing/TrackOrder')
                ->where('tracking.tracking_code', $paid->tracking_code)
                ->where('tracking.buyer_first_name', 'Pembeli')
                ->has('tracking.timeline', 2));

        $response->assertDontSee('fisik@example.test');
        $response->assertDontSee('Jl. Pembeli Nomor 2');
        $response->assertDontSee('143000');

        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) Inertia::getVersion(),
        ])->get($paid->trackingUrl())
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Marketing/TrackOrder')
            ->assertJsonPath('props.tracking.tracking_code', $paid->tracking_code);

        $this->post('/lacak', ['tracking_code' => $unpaid->tracking_code])
            ->assertSessionHasErrors('tracking_code');
    }

    public function test_creator_can_advance_preparation_status_but_cannot_rewind_or_edit_another_store(): void
    {
        [$order, $owner] = $this->paidPhysicalOrder();
        [$otherOrder, $otherOwner] = $this->paidPhysicalOrder();

        $this->actingAs($owner)->patch("/dashboard/pesanan/{$order->number}/status-pelacakan", [
            'stage' => 'packed',
            'description' => 'Barang sudah dibungkus aman.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('order_tracking_events', [
            'order_id' => $order->id,
            'actor_id' => $owner->id,
            'stage' => 'packed',
        ]);

        $this->actingAs($owner)->patch("/dashboard/pesanan/{$order->number}/status-pelacakan", [
            'stage' => 'processing',
        ])->assertSessionHasErrors('stage');

        $this->actingAs($otherOwner)->patch("/dashboard/pesanan/{$order->number}/status-pelacakan", [
            'stage' => 'ready_for_pickup',
        ])->assertForbidden();

        $this->assertNotSame($order->tracking_code, $otherOrder->tracking_code);
    }

    public function test_biteship_webhook_adds_a_real_timeline_checkpoint_and_driver_details(): void
    {
        Notification::fake();
        config()->set('shipping.providers.biteship.webhook_secret', 'webhook-secret');
        config()->set('shipping.providers.biteship.webhook_header', 'X-Callback-Token');
        [$order] = $this->paidPhysicalOrder();

        $shipment = Shipment::create([
            'order_id' => $order->id,
            'provider' => 'biteship',
            'external_id' => 'biteship-order-123',
            'courier_company' => 'jne',
            'courier_name' => 'JNE',
            'status' => ShipmentStatus::Confirmed,
        ]);

        $this->withHeader('X-Callback-Token', 'webhook-secret')
            ->postJson('/webhooks/shipping/biteship', [
                'event' => 'order.status',
                'order_id' => 'biteship-order-123',
                'status' => 'picked',
                'courier_tracking_id' => 'track-123',
                'courier_waybill_id' => 'JNE-9988',
                'courier_driver_name' => 'Budi Kurir',
                'courier_driver_photo_url' => 'https://example.test/driver.jpg',
                'courier_driver_plate_number' => 'B 1234 JY',
                'courier_link' => 'https://example.test/tracking/track-123',
                'updated_at' => now()->toIso8601String(),
            ])->assertOk()->assertJson(['status' => 'ok']);

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Picked, $shipment->status);
        $this->assertSame('Budi Kurir', $shipment->driver_name);
        $this->assertSame('JNE-9988', $shipment->waybill_id);
        $this->assertDatabaseHas('shipment_events', [
            'shipment_id' => $shipment->id,
            'status' => 'picked',
        ]);

        $this->get($order->trackingUrl())
            ->assertInertia(fn (Assert $page) => $page
                ->where('tracking.status', 'picked')
                ->where('tracking.shipment.driver_name', 'Budi Kurir')
                ->where('tracking.shipment.waybill_id', 'JNE-9988'));
    }

    public function test_buyer_receives_the_waybill_email_once_as_soon_as_biteship_confirms_it(): void
    {
        config()->set('shipping.providers.biteship.webhook_secret', 'webhook-secret');
        config()->set('shipping.providers.biteship.webhook_header', 'X-Callback-Token');
        [$order] = $this->paidPhysicalOrder();
        $shipment = Shipment::create([
            'order_id' => $order->id,
            'provider' => 'biteship',
            'external_id' => 'biteship-order-waybill',
            'courier_company' => 'tiki',
            'courier_type' => 'reg',
            'courier_name' => 'TIKI',
            'status' => ShipmentStatus::Pending,
        ]);
        Notification::fake();

        $payload = [
            'event' => 'order.status',
            'order_id' => 'biteship-order-waybill',
            'status' => 'confirmed',
            'courier_waybill_id' => 'TIKIBTS100000135408',
            'updated_at' => now()->toIso8601String(),
        ];

        $this->withHeader('X-Callback-Token', 'webhook-secret')
            ->postJson('/webhooks/shipping/biteship', $payload)
            ->assertOk();

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Confirmed, $shipment->status);
        $this->assertSame('TIKIBTS100000135408', $shipment->waybill_id);
        $this->assertNotNull($shipment->waybill_notified_at);

        Notification::assertSentOnDemand(
            BusinessNotification::class,
            function (BusinessNotification $notification) use ($order) {
                $this->assertSame('shipping.waybill_available', $notification->payload['type']);
                $this->assertSame('TIKIBTS100000135408', $notification->payload['meta']['waybill_id']);
                $this->assertContains('Nomor resi: TIKIBTS100000135408', $notification->payload['email_lines']);
                $this->assertSame($order->trackingUrl(), $notification->payload['url']);

                return true;
            },
        );

        // A repeated webhook or a manual sync must not send the same resi twice.
        $this->withHeader('X-Callback-Token', 'webhook-secret')
            ->postJson('/webhooks/shipping/biteship', $payload)
            ->assertOk();
        Notification::assertCount(1);
    }

    public function test_biteship_sync_uses_tracking_endpoint_without_overwriting_the_order_id(): void
    {
        Notification::fake();
        config()->set('shipping.providers.biteship.token', 'test-token');
        config()->set('shipping.providers.biteship.enabled', true);
        config()->set('shipping.providers.biteship.base_url', 'https://api.biteship.test');
        Http::fake([
            'https://api.biteship.test/v1/trackings/tracking-object-1' => Http::response([
                'success' => true,
                'object' => 'tracking',
                'id' => 'tracking-object-1',
                'waybill_id' => 'JNE-TRACK-1',
                'status' => 'in_transit',
                'courier' => ['company' => 'jne'],
                'history' => [[
                    'id' => 'history-1',
                    'status' => 'in_transit',
                    'note' => 'Paket tiba di pusat sortir.',
                    'location' => 'Jakarta Hub',
                    'updated_at' => now()->toIso8601String(),
                ]],
            ]),
        ]);
        [$order] = $this->paidPhysicalOrder();
        $shipment = Shipment::create([
            'order_id' => $order->id,
            'provider' => 'biteship',
            'external_id' => 'biteship-order-stays',
            'tracking_id' => 'tracking-object-1',
            'status' => ShipmentStatus::Picked,
        ]);

        $synced = app(ShippingService::class)->sync($shipment);

        $this->assertSame('biteship-order-stays', $synced->external_id);
        $this->assertSame('tracking-object-1', $synced->tracking_id);
        $this->assertSame(ShipmentStatus::InTransit, $synced->status);
        $this->assertDatabaseHas('shipment_events', [
            'shipment_id' => $shipment->id,
            'status' => 'in_transit',
            'location' => 'Jakarta Hub',
        ]);
    }

    /** @return array{0: Order, 1: User} */
    private function paidPhysicalOrder(): array
    {
        $owner = $this->makeUser([Role::CREATOR]);
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, [
            'type' => ProductType::Physical,
            'price' => 125000,
            'weight_gram' => 500,
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
                'idempotency_key' => 'tracking-'.str()->random(12),
                'shipping_total' => 18000,
                'shipping_provider' => 'manual',
                'shipping_method' => 'Reguler',
                'shipping_courier' => 'seller',
                'shipping_courier_type' => 'regular',
                'shipping_quote' => ['courier_company' => 'seller', 'courier_type' => 'regular'],
                'shipping_address' => [
                    'address_line' => 'Jl. Pembeli Nomor 2',
                    'city' => 'Jakarta',
                    'province' => 'DKI Jakarta',
                    'postal_code' => '10110',
                    'area_id' => 'manual-jakarta',
                ],
            ],
        );

        $payment = app(PaymentService::class)->createPayment($order, 'mock', 'qris', 'qris');
        app(PaymentService::class)->applyResult(new PaymentResult(
            status: PaymentStatus::Paid,
            reference: $payment->reference,
            amount: (float) $payment->amount,
            paidAt: now(),
            eventId: 'tracking-event-'.$order->id,
        ), 'mock');

        return [$order->fresh('items'), $owner];
    }
}
