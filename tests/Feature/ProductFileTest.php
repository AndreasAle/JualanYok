<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\DigitalAccess;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\Role;
use App\Payments\PaymentResult;
use App\Services\CheckoutService;
use App\Services\DigitalDeliveryService;
use App\Services\FulfillmentService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The deliverable side of a digital product: uploading it, protecting it, and
 * making sure the buyer actually receives it.
 */
class ProductFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        Storage::fake('local');
    }

    private function digitalProduct($store): Product
    {
        return $this->makeProduct($store, [
            'type' => 'DIGITAL',
            'name' => 'E-book Belajar Desain',
            'price' => 150000,
            'status' => 'DRAFT',
            'without_files' => true,
        ]);
    }

    /** Activates the product and runs a real paid purchase through it. */
    private function purchase($store, Product $product, string $key): void
    {
        $product->forceFill(['status' => 'ACTIVE', 'visibility' => 'public'])->save();

        $order = app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => $key],
        );

        $payments = app(PaymentService::class);
        $payment = $payments->createPayment($order, 'mock', 'qris', 'qris');

        $payments->applyResult(new PaymentResult(
            status: PaymentStatus::Paid,
            reference: $payment->reference,
            amount: (float) $payment->amount,
            paidAt: now(),
            eventId: $key.'-evt',
        ), 'mock');
    }

    public function test_a_creator_uploads_the_file_their_buyers_will_receive(): void
    {
        $store = $this->makeStore();
        $product = $this->digitalProduct($store);

        $this->actingAs($store->owner)
            ->post("/dashboard/produk/{$product->id}/files", [
                'file' => UploadedFile::fake()->create('panduan.pdf', 900, 'application/pdf'),
            ])
            ->assertRedirect();

        $file = $product->files()->firstOrFail();

        $this->assertSame('panduan.pdf', $file->name);
        $this->assertSame('1.0', $file->version);
        $this->assertSame('local', $file->disk);
        Storage::disk('local')->assertExists($file->path);

        // The stored path must not be guessable from the uploaded filename.
        $this->assertStringNotContainsString('panduan', $file->path);
        $this->assertStringStartsWith("stores/{$store->id}/products/{$product->id}/", $file->path);
    }

    public function test_an_uploaded_file_reaches_the_buyer_after_payment(): void
    {
        $store = $this->makeStore();
        $product = $this->digitalProduct($store);

        $this->actingAs($store->owner)
            ->post("/dashboard/produk/{$product->id}/files", [
                'file' => UploadedFile::fake()->create('panduan.pdf', 400, 'application/pdf'),
                'download_limit' => 3,
            ])
            ->assertRedirect();

        $product->refresh();

        // Now that a file exists the product can legitimately go on sale.
        $this->purchase($store, $product, 'file-1');

        $access = DigitalAccess::where('product_id', $product->id)->firstOrFail();

        $this->assertSame($product->files()->first()->id, $access->product_file_id);
        $this->assertSame(3, $access->download_limit);
        $this->assertTrue($access->isDownloadable());
    }

    public function test_the_buyer_receives_a_file_they_can_actually_open(): void
    {
        $store = $this->makeStore();
        $product = $this->digitalProduct($store);

        // A creator naming the file "Edisi Ketiga" must not hand the buyer an
        // extensionless blob their OS refuses to open.
        $this->actingAs($store->owner)->post("/dashboard/produk/{$product->id}/files", [
            'file' => UploadedFile::fake()->create('sumber.pdf', 20, 'application/pdf'),
            'name' => 'Edisi Ketiga',
        ]);

        $this->purchase($store, $product, 'open-1');

        $access = DigitalAccess::where('product_id', $product->id)->firstOrFail();
        $url = app(DigitalDeliveryService::class)->signedUrl($access);

        $response = $this->get($url);

        $response->assertOk();
        $this->assertStringContainsString(
            'filename="Edisi Ketiga.pdf"',
            $response->headers->get('content-disposition'),
        );
    }

    /**
     * Fulfilment runs inside a queued job, where nothing has been eager-loaded
     * for it. A missing eager-load costs a query per order line — and under the
     * strict local settings it killed the job outright, so the buyer paid and
     * silently received nothing.
     *
     * Measured as growth rather than a fixed budget: what matters is that extra
     * lines do not drag extra catalogue lookups along with them.
     */
    public function test_fulfilment_does_not_query_per_order_line(): void
    {
        $store = $this->makeStore();

        $cost = function (int $lines) use ($store) {
            $items = [];

            for ($i = 0; $i < $lines; $i++) {
                $items[] = [
                    'product_id' => $this->makeProduct($store, ['name' => "Produk {$lines}-{$i}"])->id,
                    'quantity' => 1,
                ];
            }

            $order = app(CheckoutService::class)->createOrder(
                $store,
                $items,
                ['name' => 'Rina', 'email' => 'rina@example.test'],
                ['idempotency_key' => "fulfil-{$lines}"],
            );

            DB::flushQueryLog();
            DB::enableQueryLog();

            // Retrieved fresh, exactly as the queued job receives it.
            app(FulfillmentService::class)->fulfil(Order::findOrFail($order->id));

            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $four = $cost(4);
        $eight = $cost(8);

        // Each granted line legitimately writes a few rows; anything beyond
        // that is the catalogue being re-fetched per item.
        $this->assertLessThanOrEqual(
            $four + 13,
            $eight,
            "Fulfilment went from {$four} queries at 4 lines to {$eight} at 8 — the order's relations are not eager-loaded.",
        );
    }

    public function test_the_download_limit_is_enforced(): void
    {
        $store = $this->makeStore();
        $product = $this->digitalProduct($store);

        $this->actingAs($store->owner)->post("/dashboard/produk/{$product->id}/files", [
            'file' => UploadedFile::fake()->create('panduan.pdf', 20, 'application/pdf'),
            'download_limit' => 1,
        ]);

        $this->purchase($store, $product, 'limit-1');

        $access = DigitalAccess::where('product_id', $product->id)->firstOrFail();
        $delivery = app(DigitalDeliveryService::class);

        $this->get($delivery->signedUrl($access))->assertOk();

        // A fresh, perfectly valid signature still cannot exceed the quota.
        $this->get($delivery->signedUrl($access->fresh()))->assertForbidden();
    }

    public function test_a_digital_product_without_a_file_cannot_be_activated(): void
    {
        $store = $this->makeStore();
        $product = $this->digitalProduct($store);

        $this->actingAs($store->owner)
            ->from("/dashboard/produk/{$product->id}/edit")
            ->put("/dashboard/produk/{$product->id}", [
                'type' => 'DIGITAL',
                'name' => $product->name,
                'price' => 150000,
                'status' => 'ACTIVE',
                'visibility' => 'public',
                'min_quantity' => 1,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('DRAFT', $product->fresh()->status->value);
    }

    public function test_a_digital_product_without_a_file_is_hidden_and_refused_at_checkout(): void
    {
        $store = $this->makeStore();
        $product = $this->digitalProduct($store);

        // Force it active the way stale data could be, bypassing the form guard.
        $product->forceFill(['status' => 'ACTIVE', 'visibility' => 'public'])->save();

        $this->assertFalse(
            $store->products()->publiclyListed()->whereKey($product->id)->exists(),
            'An undeliverable product must not appear on the storefront.',
        );

        $this->expectException(ValidationException::class);

        app(CheckoutService::class)->createOrder(
            $store,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['name' => 'Rina', 'email' => 'rina@example.test'],
            ['idempotency_key' => 'file-blocked'],
        );
    }

    public function test_replacing_a_file_keeps_existing_buyers_access(): void
    {
        $store = $this->makeStore();
        $product = $this->digitalProduct($store);

        $this->actingAs($store->owner)->post("/dashboard/produk/{$product->id}/files", [
            'file' => UploadedFile::fake()->create('edisi-1.pdf', 200, 'application/pdf'),
        ]);

        $file = $product->files()->firstOrFail();
        $originalPath = $file->path;

        $this->purchase($store, $product, 'replace-1');
        $access = DigitalAccess::where('product_file_id', $file->id)->firstOrFail();

        $this->actingAs($store->owner)
            ->post("/dashboard/produk/{$product->id}/files/{$file->id}/replace", [
                'file' => UploadedFile::fake()->create('edisi-2.pdf', 300, 'application/pdf'),
            ])
            ->assertRedirect();

        $file->refresh();

        $this->assertNotSame($originalPath, $file->path);
        $this->assertSame('1.1', $file->version, 'The version bumps so buyers can tell editions apart.');
        Storage::disk('local')->assertMissing($originalPath);
        $this->assertDatabaseHas('digital_accesses', ['id' => $access->id, 'product_file_id' => $file->id]);
    }

    public function test_deleting_a_file_someone_already_bought_is_refused(): void
    {
        $store = $this->makeStore();
        $product = $this->digitalProduct($store);

        $this->actingAs($store->owner)->post("/dashboard/produk/{$product->id}/files", [
            'file' => UploadedFile::fake()->create('panduan.pdf', 100, 'application/pdf'),
        ]);

        $file = $product->files()->firstOrFail();

        $this->purchase($store, $product, 'delete-1');

        $this->actingAs($store->owner)
            ->from("/dashboard/produk/{$product->id}/edit")
            ->delete("/dashboard/produk/{$product->id}/files/{$file->id}")
            ->assertSessionHasErrors('file');

        $this->assertDatabaseHas('product_files', ['id' => $file->id]);
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_a_creator_cannot_touch_another_stores_files(): void
    {
        $mine = $this->makeStore();
        $theirs = $this->makeStore();

        $product = $this->digitalProduct($theirs);

        $this->actingAs($mine->owner)
            ->post("/dashboard/produk/{$product->id}/files", [
                'file' => UploadedFile::fake()->create('curi.pdf', 50, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertSame(0, $product->files()->count());
    }

    public function test_a_file_from_another_product_cannot_be_edited_through_my_product(): void
    {
        $store = $this->makeStore();
        $other = $this->makeStore();

        $mine = $this->digitalProduct($store);
        $victimProduct = $this->digitalProduct($other);

        $victimFile = ProductFile::create([
            'product_id' => $victimProduct->id,
            'name' => 'Rahasia',
            'disk' => 'local',
            'path' => 'stores/x/secret.pdf',
        ]);

        $this->actingAs($store->owner)
            ->delete("/dashboard/produk/{$mine->id}/files/{$victimFile->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('product_files', ['id' => $victimFile->id]);
    }

    public function test_an_executable_upload_is_rejected(): void
    {
        $store = $this->makeStore();
        $product = $this->digitalProduct($store);

        $this->actingAs($store->owner)
            ->from("/dashboard/produk/{$product->id}/edit")
            ->post("/dashboard/produk/{$product->id}/files", [
                'file' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, $product->files()->count());
    }

    public function test_an_external_link_counts_as_a_deliverable(): void
    {
        $store = $this->makeStore();
        $product = $this->digitalProduct($store);

        $this->actingAs($store->owner)
            ->post("/dashboard/produk/{$product->id}/files", [
                'external_url' => 'https://drive.example.test/berkas',
            ])
            ->assertRedirect();

        $this->assertTrue($product->fresh()->isDeliverable());
        $this->assertNull($product->files()->first()->path);
    }

    public function test_the_file_panel_is_only_for_digital_products(): void
    {
        $store = $this->makeStore();

        $physical = $this->makeProduct($store, ['type' => 'PHYSICAL', 'name' => 'Kaos']);

        $this->actingAs($store->owner)
            ->post("/dashboard/produk/{$physical->id}/files", [
                'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    /** The `creator` middleware bounces non-creators before the controller runs. */
    public function test_a_customer_cannot_reach_the_upload_endpoint(): void
    {
        $store = $this->makeStore();
        $product = $this->digitalProduct($store);
        $stranger = $this->makeUser([Role::CUSTOMER]);

        $this->actingAs($stranger)
            ->post("/dashboard/produk/{$product->id}/files", [
                'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertSame(0, $product->files()->count());
    }
}
