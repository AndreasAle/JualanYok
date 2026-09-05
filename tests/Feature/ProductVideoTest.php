<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Video in the product gallery.
 *
 * A row of stills cannot show how a fabric falls or how big a thing really is,
 * which is the question buyers open the chat to ask. The gallery is public and
 * served straight off disk, so what gets in is judged by the file's real type
 * rather than the name it arrived under.
 */
class ProductVideoTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        Storage::fake('public');

        $this->store = $this->makeStore();
        $this->store->forceFill(['is_published' => true])->save();
    }

    private function submit(array $gallery)
    {
        return $this->actingAs($this->store->owner)->post('/dashboard/produk', [
            'type' => 'PHYSICAL',
            'name' => 'Piyama Busui',
            'price' => 110000,
            'status' => 'DRAFT',
            'visibility' => 'public',
            'initial_stock' => 5,
            // A physical product cannot be shipped without these, so the form
            // insists on them; they are beside the point of this test.
            'weight_gram' => 500,
            'length_cm' => 20,
            'width_cm' => 15,
            'height_cm' => 5,
            'gallery' => $gallery,
        ]);
    }

    public function test_a_creator_can_add_a_video_to_the_gallery(): void
    {
        $this->submit([
            UploadedFile::fake()->image('depan.jpg'),
            UploadedFile::fake()->create('pakai.mp4', 2048, 'video/mp4'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $media = Product::firstOrFail()->media;

        $this->assertCount(2, $media);
        $this->assertSame(['image', 'video'], $media->pluck('kind')->all());

        foreach ($media as $item) {
            Storage::disk('public')->assertExists($item->path);
        }
    }

    public function test_a_file_pretending_to_be_a_video_is_refused(): void
    {
        // The gallery folder is served publicly, so the real type decides.
        $this->submit([UploadedFile::fake()->create('exploit.mp4', 10, 'application/x-httpd-php')])
            ->assertSessionHasErrors('gallery.0');

        $this->assertSame(0, Product::count());
    }

    public function test_an_oversized_video_is_refused_by_its_own_limit(): void
    {
        // 60 MB: fine for nothing, and well past the video allowance.
        $this->submit([UploadedFile::fake()->create('panjang.mp4', 60 * 1024, 'video/mp4')])
            ->assertSessionHasErrors();

        $this->assertSame(0, Product::count());
    }

    public function test_an_image_is_still_held_to_the_smaller_image_limit(): void
    {
        // Comfortably under the video cap, deliberately over the image one:
        // a photo must not borrow the allowance meant for a clip.
        $this->submit([UploadedFile::fake()->create('besar.jpg', 8 * 1024, 'image/jpeg')])
            ->assertSessionHasErrors();

        $this->assertSame(0, Product::count());
    }

    public function test_the_storefront_says_which_items_are_video(): void
    {
        $product = $this->makeProduct($this->store, ['name' => 'Piyama']);

        $product->media()->create(['path' => 'products/gallery/a.jpg', 'kind' => 'image', 'position' => 1]);
        $product->media()->create(['path' => 'products/gallery/b.mp4', 'kind' => 'video', 'position' => 2]);

        $this->get("/{$this->store->username}/p/{$product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('product.media.0.kind', 'image')
                ->where('product.media.1.kind', 'video'));
    }

    public function test_gallery_rows_that_predate_video_still_read_as_images(): void
    {
        $product = $this->makeProduct($this->store);

        // Written before the column existed; the default has to keep them
        // meaning what they meant.
        $product->media()->create(['path' => 'products/gallery/lama.jpg', 'position' => 1]);

        $this->assertSame('image', $product->media()->firstOrFail()->kind);
    }
}
