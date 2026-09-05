<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreShippingProfile;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The dropped pin, and what it is actually for.
 *
 * Instant couriers cannot price a job from a district name — Biteship returns
 * them only when coordinates are supplied. So a pin is not decoration: without
 * it, same-day delivery silently does not exist as an option. It stays
 * optional, and an order placed without one must still be quotable.
 */
class ShippingCoordinateTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        config([
            'shipping.default' => 'biteship',
            'shipping.providers.biteship.enabled' => true,
            'shipping.providers.biteship.token' => 'biteship-uji',
            'shipping.providers.biteship.base_url' => 'https://api.biteship.com',
            'shipping.providers.biteship.couriers' => ['jne'],
        ]);

        $this->store = $this->makeStore();

        StoreShippingProfile::create([
            'store_id' => $this->store->id,
            'contact_name' => 'Gudang',
            'contact_phone' => '081234567890',
            'address_line' => 'Jl. Contoh 1',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'postal_code' => '12250',
            'area_id' => 'IDNP6IDNC148IDND843IDZ12250',
            'latitude' => -6.26,
            'longitude' => 106.78,
            'is_active' => true,
        ]);
    }

    private function fakeRates(): void
    {
        Http::fake([
            '*/v1/rates/couriers' => Http::response([
                'pricing' => [[
                    'courier_code' => 'jne', 'courier_name' => 'JNE', 'courier_service_name' => 'REG',
                    'courier_service_code' => 'reg', 'price' => 12000, 'duration' => '2 hari',
                    'available_for_order' => true,
                ]],
            ]),
        ]);
    }

    private function destination(array $extra = []): array
    {
        return array_merge([
            'address_line' => 'Jl. Merdeka 18',
            'district' => 'Ilir Barat I',
            'city' => 'Palembang',
            'province' => 'Sumatera Selatan',
            'postal_code' => '30137',
            'area_id' => 'IDNP26IDNC378IDND5000IDZ30137',
        ], $extra);
    }

    private function lines(): array
    {
        $product = $this->makeProduct($this->store, ['type' => 'PHYSICAL', 'price' => 50000, 'weight_gram' => 500]);

        return [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]];
    }

    public function test_a_dropped_pin_reaches_biteship(): void
    {
        $this->fakeRates();

        app(ShippingService::class)->quotes(
            $this->store,
            $this->lines(),
            $this->destination(['latitude' => -2.9761, 'longitude' => 104.7754]),
        );

        Http::assertSent(function (ClientRequest $request) {
            if (! str_contains($request->url(), '/v1/rates/couriers')) {
                return true;
            }

            $body = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);

            $this->assertEqualsWithDelta(-2.9761, $body['destination_latitude'], 0.0001);
            $this->assertEqualsWithDelta(104.7754, $body['destination_longitude'], 0.0001);

            // The shop's own coordinates matter just as much: an instant
            // courier prices the leg between two points, not one.
            $this->assertEqualsWithDelta(-6.26, $body['origin_latitude'], 0.0001);
            $this->assertEqualsWithDelta(106.78, $body['origin_longitude'], 0.0001);

            // Area ids still travel, so regular couriers are untouched.
            $this->assertNotEmpty($body['destination_area_id']);

            return true;
        });
    }

    public function test_skipping_the_map_still_quotes(): void
    {
        $this->fakeRates();

        $quotes = app(ShippingService::class)->quotes($this->store, $this->lines(), $this->destination());

        $this->assertNotEmpty($quotes, 'Tanpa pin, kurir reguler harus tetap muncul.');

        Http::assertSent(function (ClientRequest $request) {
            if (! str_contains($request->url(), '/v1/rates/couriers')) {
                return true;
            }

            $body = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);

            // Absent, not null: a null coordinate is a value Biteship has to
            // reject, while an absent one simply means "use the area".
            $this->assertArrayNotHasKey('destination_latitude', $body);
            $this->assertArrayNotHasKey('destination_longitude', $body);

            return true;
        });
    }

    public function test_a_nonsense_coordinate_is_dropped_rather_than_forwarded(): void
    {
        $this->fakeRates();

        app(ShippingService::class)->quotes(
            $this->store,
            $this->lines(),
            // 0,0 is the Atlantic, and the usual shape of an uninitialised field.
            $this->destination(['latitude' => 0, 'longitude' => 0]),
        );

        Http::assertSent(function (ClientRequest $request) {
            if (! str_contains($request->url(), '/v1/rates/couriers')) {
                return true;
            }

            $body = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertArrayNotHasKey('destination_latitude', $body);

            return true;
        });
    }

    public function test_the_quote_token_commits_to_the_pin_it_was_priced_for(): void
    {
        $this->fakeRates();

        $lines = $this->lines();

        $quote = app(ShippingService::class)->quotes(
            $this->store,
            $lines,
            $this->destination(['latitude' => -2.9761, 'longitude' => 104.7754]),
        )[0];

        $verified = app(ShippingService::class)->verifyQuote($this->store, $lines, $quote['token']);

        $this->assertEqualsWithDelta(-2.9761, $verified['destination']['latitude'], 0.0001);
    }
}
