<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        Storage::fake('public');
    }

    /** Base payload — the settings form always submits the whole profile. */
    private function profile(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Toko Uji',
            'username' => 'tokouji',
            'tagline' => 'Tagline',
            'show_platform_branding' => true,
        ], $overrides);
    }

    public function test_a_creator_can_upload_an_avatar_and_banner(): void
    {
        $store = $this->makeStore(attributes: ['username' => 'tokouji']);

        $this->actingAs($store->owner)
            ->put('/dashboard/toko', $this->profile([
                'avatar' => UploadedFile::fake()->image('avatar.jpg', 400, 400),
                'cover' => UploadedFile::fake()->image('cover.jpg', 1600, 500),
            ]))
            ->assertSessionHasNoErrors();

        $store->refresh();

        $this->assertNotNull($store->avatar_path);
        $this->assertNotNull($store->cover_path);

        Storage::disk('public')->assertExists($store->avatar_path);
        Storage::disk('public')->assertExists($store->cover_path);

        // The public payload exposes a usable URL, not the raw disk path.
        $this->assertStringContainsString('/storage/', $store->avatarUrl());
    }

    public function test_replacing_an_image_deletes_the_previous_file(): void
    {
        $store = $this->makeStore(attributes: ['username' => 'tokouji']);

        $this->actingAs($store->owner)->put('/dashboard/toko', $this->profile([
            'avatar' => UploadedFile::fake()->image('first.jpg'),
        ]));

        $first = $store->fresh()->avatar_path;

        $this->actingAs($store->owner)->put('/dashboard/toko', $this->profile([
            'avatar' => UploadedFile::fake()->image('second.jpg'),
        ]));

        $second = $store->fresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_a_creator_can_remove_the_banner(): void
    {
        $store = $this->makeStore(attributes: ['username' => 'tokouji']);

        $this->actingAs($store->owner)->put('/dashboard/toko', $this->profile([
            'cover' => UploadedFile::fake()->image('cover.jpg'),
        ]));

        $path = $store->fresh()->cover_path;
        $this->assertNotNull($path);

        $this->actingAs($store->owner)->put('/dashboard/toko', $this->profile([
            'remove_cover' => true,
        ]));

        $this->assertNull($store->fresh()->cover_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        $store = $this->makeStore(attributes: ['username' => 'tokouji']);

        $this->actingAs($store->owner)
            ->put('/dashboard/toko', $this->profile([
                'avatar' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php'),
            ]))
            ->assertSessionHasErrors('avatar');

        $this->assertNull($store->fresh()->avatar_path);
    }

    public function test_an_oversized_image_is_rejected(): void
    {
        $store = $this->makeStore(attributes: ['username' => 'tokouji']);

        $tooBig = (int) config('jualanyok.uploads.image_max_kb') + 1024;

        $this->actingAs($store->owner)
            ->put('/dashboard/toko', $this->profile([
                'avatar' => UploadedFile::fake()->image('huge.jpg')->size($tooBig),
            ]))
            ->assertSessionHasErrors('avatar');
    }

    public function test_uploaded_files_get_a_randomised_name(): void
    {
        $store = $this->makeStore(attributes: ['username' => 'tokouji']);

        $this->actingAs($store->owner)->put('/dashboard/toko', $this->profile([
            'avatar' => UploadedFile::fake()->image('../../rahasia saya.jpg'),
        ]));

        $path = $store->fresh()->avatar_path;

        // The original filename must never reach the disk.
        $this->assertStringNotContainsString('rahasia', $path);
        $this->assertStringNotContainsString('..', $path);
        $this->assertStringStartsWith('stores/avatars/', $path);
    }

    public function test_a_creator_can_upload_a_product_thumbnail(): void
    {
        $store = $this->makeStore();

        $this->actingAs($store->owner)
            ->post('/dashboard/produk', [
                'type' => 'DIGITAL',
                'name' => 'Produk Bergambar',
                'price' => 50000,
                'status' => 'ACTIVE',
                'visibility' => 'public',
                'min_quantity' => 1,
                'thumbnail' => UploadedFile::fake()->image('produk.png', 800, 800),
            ])
            ->assertSessionHasNoErrors();

        $product = $store->products()->firstOrFail();

        $this->assertNotNull($product->thumbnail_path);
        Storage::disk('public')->assertExists($product->thumbnail_path);
    }

    public function test_replacing_a_product_thumbnail_deletes_the_old_one(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);

        $base = [
            'type' => 'DIGITAL',
            'name' => $product->name,
            'price' => 50000,
            'status' => 'ACTIVE',
            'visibility' => 'public',
            'min_quantity' => 1,
        ];

        $this->actingAs($store->owner)->put("/dashboard/produk/{$product->id}", $base + [
            'thumbnail' => UploadedFile::fake()->image('satu.png'),
        ]);

        $first = $product->fresh()->thumbnail_path;

        $this->actingAs($store->owner)->put("/dashboard/produk/{$product->id}", $base + [
            'thumbnail' => UploadedFile::fake()->image('dua.png'),
        ]);

        $second = $product->fresh()->thumbnail_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_a_creator_can_upload_multiple_product_gallery_images(): void
    {
        $store = $this->makeStore();

        $this->actingAs($store->owner)
            ->post('/dashboard/produk', [
                'type' => 'DIGITAL',
                'name' => 'Produk Dengan Galeri',
                'price' => 50000,
                'status' => 'ACTIVE',
                'visibility' => 'public',
                'min_quantity' => 1,
                'gallery' => [
                    UploadedFile::fake()->image('depan.jpg'),
                    UploadedFile::fake()->image('samping.jpg'),
                ],
            ])
            ->assertSessionHasNoErrors();

        $product = $store->products()->where('name', 'Produk Dengan Galeri')->firstOrFail();

        $this->assertCount(2, $product->media);
        $product->media->each(fn ($media) => Storage::disk('public')->assertExists($media->path));
    }

    public function test_a_creator_can_remove_and_add_product_gallery_images(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        $removed = $product->media()->create([
            'path' => UploadedFile::fake()->image('lama.jpg')->store('products/gallery', 'public'),
            'position' => 1,
        ]);

        $this->actingAs($store->owner)
            ->put("/dashboard/produk/{$product->id}", [
                'type' => 'DIGITAL',
                'name' => $product->name,
                'price' => 50000,
                'status' => 'ACTIVE',
                'visibility' => 'public',
                'min_quantity' => 1,
                'removed_media_ids' => [$removed->id],
                'gallery' => [UploadedFile::fake()->image('baru.jpg')],
            ])
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($removed->path);
        $this->assertCount(1, $product->fresh()->media);
        Storage::disk('public')->assertExists($product->fresh()->media->first()->path);
    }

    public function test_a_creator_cannot_upload_to_another_store(): void
    {
        $mine = $this->makeStore();
        $theirs = $this->makeStore();
        $theirProduct = $this->makeProduct($theirs);

        $this->actingAs($mine->owner)
            ->put("/dashboard/produk/{$theirProduct->id}", [
                'type' => 'DIGITAL',
                'name' => 'Dibajak',
                'price' => 1,
                'status' => 'ACTIVE',
                'visibility' => 'public',
                'min_quantity' => 1,
                'thumbnail' => UploadedFile::fake()->image('x.png'),
            ])
            ->assertForbidden();

        $this->assertNull($theirProduct->fresh()->thumbnail_path);
    }

    /* ------------------------------------------------------------------
     | Block media endpoint
     ------------------------------------------------------------------ */

    public function test_a_creator_can_upload_a_block_image(): void
    {
        $store = $this->makeStore();

        $response = $this->actingAs($store->owner)
            ->postJson('/dashboard/media', [
                'file' => UploadedFile::fake()->image('banner.png', 1200, 600),
            ])
            ->assertOk()
            ->assertJsonStructure(['path', 'url']);

        $path = $response->json('path');

        // Files land in a folder scoped to the uploading store.
        $this->assertStringStartsWith("stores/{$store->id}/blocks/", $path);
        $this->assertStringNotContainsString('banner', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_block_media_rejects_a_non_image(): void
    {
        $store = $this->makeStore();

        $this->actingAs($store->owner)
            ->postJson('/dashboard/media', [
                'file' => UploadedFile::fake()->create('payload.php', 8, 'application/x-php'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_block_media_rejects_an_oversized_image(): void
    {
        $store = $this->makeStore();

        $this->actingAs($store->owner)
            ->postJson('/dashboard/media', [
                'file' => UploadedFile::fake()->image('huge.jpg')->size((int) config('jualanyok.uploads.image_max_kb') + 512),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_a_guest_cannot_upload_block_media(): void
    {
        $this->postJson('/dashboard/media', [
            'file' => UploadedFile::fake()->image('x.png'),
        ])->assertStatus(401);
    }

    public function test_a_creator_cannot_delete_another_stores_media(): void
    {
        $mine = $this->makeStore();
        $theirs = $this->makeStore();

        $theirPath = $this->actingAs($theirs->owner)
            ->postJson('/dashboard/media', ['file' => UploadedFile::fake()->image('milik-orang.png')])
            ->json('path');

        $this->actingAs($mine->owner)
            ->deleteJson('/dashboard/media', ['path' => $theirPath])
            ->assertForbidden();

        Storage::disk('public')->assertExists($theirPath);
    }

    public function test_a_creator_can_delete_their_own_block_media(): void
    {
        $store = $this->makeStore();

        $path = $this->actingAs($store->owner)
            ->postJson('/dashboard/media', ['file' => UploadedFile::fake()->image('punyaku.png')])
            ->json('path');

        $this->actingAs($store->owner)
            ->deleteJson('/dashboard/media', ['path' => $path])
            ->assertOk();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_media_urls_are_host_independent(): void
    {
        // Absolute URLs would be baked into block JSON and break the moment the
        // app moves domain, gains a custom domain, or runs on another port.
        config(['app.url' => 'http://localhost']);

        $store = $this->makeStore();

        $url = $this->actingAs($store->owner)
            ->postJson('/dashboard/media', ['file' => UploadedFile::fake()->image('a.png')])
            ->json('url');

        $this->assertStringStartsWith('/storage/', $url);
        $this->assertStringNotContainsString('http://', $url);
    }

    public function test_an_externally_hosted_url_is_passed_through_untouched(): void
    {
        $this->assertSame(
            'https://cdn.contoh.test/gambar.png',
            Media::url('https://cdn.contoh.test/gambar.png'),
        );

        $this->assertNull(Media::url(null));
    }

    public function test_an_uploaded_url_persists_into_the_block_content(): void
    {
        $store = $this->makeStore();

        $url = $this->actingAs($store->owner)
            ->postJson('/dashboard/media', ['file' => UploadedFile::fake()->image('isi-block.png')])
            ->json('url');

        $block = Block::create([
            'store_id' => $store->id,
            'type' => 'IMAGE',
            'position' => 0,
            'content' => [],
        ]);

        $this->actingAs($store->owner)
            ->put("/dashboard/blocks/{$block->id}", [
                'content' => ['url' => $url, 'alt' => 'Contoh gambar'],
            ])
            ->assertSessionHasNoErrors();

        $block->refresh();

        $this->assertSame($url, $block->draft_content['url']);
        $this->assertSame('Contoh gambar', $block->draft_content['alt']);
    }

    public function test_gallery_block_keeps_every_uploaded_image(): void
    {
        $store = $this->makeStore();

        $urls = collect(['a.png', 'b.png', 'c.png'])->map(
            fn ($name) => $this->actingAs($store->owner)
                ->postJson('/dashboard/media', ['file' => UploadedFile::fake()->image($name)])
                ->json('url'),
        )->all();

        $block = Block::create([
            'store_id' => $store->id,
            'type' => 'GALLERY',
            'position' => 0,
            'content' => ['images' => []],
        ]);

        $this->actingAs($store->owner)->put("/dashboard/blocks/{$block->id}", [
            'content' => ['images' => array_map(fn ($u) => ['url' => $u, 'alt' => ''], $urls)],
        ]);

        $saved = $block->fresh()->draft_content['images'];

        $this->assertCount(3, $saved);
        $this->assertSame($urls, array_column($saved, 'url'));
    }
}
