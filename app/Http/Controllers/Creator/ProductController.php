<?php

namespace App\Http\Controllers\Creator;

use App\Enums\MarketplaceStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\DigitalAccess;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\InventoryService;
use App\Services\NotificationCenterService;
use App\Services\PlanService;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly PlanService $plans,
        private readonly InventoryService $inventory,
        private readonly NotificationCenterService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $store = $request->user()->store;

        $products = $store->products()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->query('q').'%'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->withCount([
                'orderItems',
                'files',
                'analyticsEvents as external_clicks_count' => fn ($query) => $query
                    ->where('name', AnalyticsEvent::AFFILIATE_CLICK),
            ])
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'type' => $p->type->value,
                'type_label' => $p->type->label(),
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'price' => (float) $p->price,
                'compare_at_price' => $p->compare_at_price ? (float) $p->compare_at_price : null,
                'thumbnail_url' => $p->thumbnailUrl(),
                'sales_count' => $p->sales_count,
                'external_clicks' => (int) $p->external_clicks_count,
                'external_provider' => $p->externalProvider(),
                'view_count' => $p->view_count,
                'affiliate_enabled' => (bool) $p->affiliate_enabled,
                // Flags a digital product that would take money and deliver nothing.
                'needs_file' => $p->type === ProductType::Digital
                    && $p->files_count === 0
                    && ! $p->external_url,
                'public_url' => route('storefront.product', [$store->username, $p->slug]),
            ]);

        return Inertia::render('Creator/Products/Index', [
            'products' => $products,
            'filters' => $request->only(['q', 'type', 'status']),
            'types' => $this->typeOptions(),
            'limits' => [
                'limit' => $this->plans->limit($request->user(), PlanService::PRODUCTS_LIMIT),
                'used' => $store->products()->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Creator/Products/Form', [
            'product' => null,
            'firstProduct' => $request->boolean('first'),
            'types' => $this->typeOptions(),
            'categories' => ProductCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $store = $request->user()->store;

        $this->plans->ensureWithinLimit(
            $request->user(),
            PlanService::PRODUCTS_LIMIT,
            $store->products()->count(),
            'produk',
        );

        $validated = $this->validated($request);
        $gallery = $request->file('gallery', []);
        unset($validated['gallery'], $validated['gallery_posters'], $validated['removed_media_ids']);
        $data = $this->prepareMarketplaceData($this->normalizeTypeData($validated));
        $initialStock = (int) ($data['initial_stock'] ?? 0);
        unset($data['initial_stock']);

        $product = DB::transaction(function () use ($store, $data, $request, $initialStock, $gallery) {
            $product = $store->products()->create($data + [
                'slug' => $this->uniqueSlug($store->id, $data['name']),
                'thumbnail_path' => $this->storeThumbnail($request),
            ]);

            $this->syncTypeExtras($product);
            $this->storeGallery($product, $gallery, $request->file('gallery_posters', []));

            if ($product->type === ProductType::Physical && $initialStock > 0) {
                $this->inventory->setQuantity(
                    $product->inventories()->whereNull('product_variant_id')->firstOrFail(),
                    $initialStock,
                    'initial',
                    $request->user(),
                    'Stok awal saat produk dibuat.',
                );
            }

            return $product;
        });

        // A digital product is not sellable until a file is attached, so send the
        // creator to the upload step rather than to a preview of an empty product.
        if ($product->type === ProductType::Digital && ! $product->isDeliverable()) {
            return redirect()
                ->route('creator.products.edit', $product)
                ->with('warning', 'Produk tersimpan. Unggah file yang akan diterima pembeli agar produk bisa dijual.');
        }

        return redirect()
            ->route('creator.builder', ['first_product' => 1])
            ->with('success', 'Produk berhasil dibuat dan sudah masuk ke pratinjau. Periksa lalu publikasikan toko.');
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorizeProduct($request, $product);

        $product->load(['media', 'files', 'variants', 'inventories.variant', 'course.sections.lessons', 'event.tickets', 'service.availabilityRules', 'membershipPlans']);

        return Inertia::render('Creator/Products/Form', [
            'product' => [
                'id' => $product->id,
                'type' => $product->type->value,
                'name' => $product->name,
                'slug' => $product->slug,
                'short_description' => $product->short_description,
                'description' => $product->description,
                'price' => (float) $product->price,
                'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
                'is_pay_what_you_want' => (bool) $product->is_pay_what_you_want,
                'minimum_price' => $product->minimum_price ? (float) $product->minimum_price : null,
                'status' => $product->status->value,
                'visibility' => $product->visibility,
                'product_category_id' => $product->product_category_id,
                'is_marketplace_listed' => (bool) $product->is_marketplace_listed,
                'marketplace_category_id' => $product->marketplace_category_id,
                'marketplace_status' => $product->marketplace_status->value,
                'marketplace_status_label' => $product->marketplace_status->label(),
                'rejection_reason' => $product->rejection_reason,
                'tags' => $product->tags ?? [],
                'min_quantity' => $product->min_quantity,
                'max_quantity' => $product->max_quantity,
                'sales_limit' => $product->sales_limit,
                'sale_starts_at' => $product->sale_starts_at?->toDateTimeString(),
                'sale_ends_at' => $product->sale_ends_at?->toDateTimeString(),
                'seo_title' => $product->seo_title,
                'seo_description' => $product->seo_description,
                'checkout_message' => $product->checkout_message,
                'post_purchase_message' => $product->post_purchase_message,
                'terms' => $product->terms,
                'affiliate_enabled' => (bool) $product->affiliate_enabled,
                'external_url' => $product->external_url,
                'custom_fields' => $product->custom_fields ?? [],
                'thumbnail_url' => $product->thumbnailUrl(),
                'media' => $product->media->map(fn ($media) => [
                    'id' => $media->id,
                    'url' => Media::url($media->path),
                    'kind' => $media->kind ?? 'image',
                    'poster' => Media::url($media->poster_path),
                    'alt' => $media->alt,
                ])->values(),
                'sku' => $product->sku,
                'weight_gram' => $product->weight_gram,
                'length_cm' => $product->length_cm,
                'width_cm' => $product->width_cm,
                'height_cm' => $product->height_cm,
                'shipping_category' => $product->shipping_category,
                'is_fragile' => (bool) $product->is_fragile,
                'files' => $product->files->map(fn ($f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'size' => $f->size,
                    'version' => $f->version,
                    'download_limit' => $f->download_limit,
                    'access_days' => $f->access_days,
                    'watermark_pdf' => (bool) $f->watermark_pdf,
                    'mime_type' => $f->mime_type,
                    'external_url' => $f->external_url,
                    // Drives the "replace instead of delete" hint in the UI.
                    'purchase_count' => DigitalAccess::where('product_file_id', $f->id)->count(),
                ]),
                'is_deliverable' => $product->isDeliverable(),
                'variants' => $product->variants,
                'inventory' => $product->inventories
                    ->when($product->variants->isNotEmpty(), fn ($items) => $items->whereNotNull('product_variant_id'))
                    ->map(fn (Inventory $i) => [
                        'id' => $i->id,
                        'variant_id' => $i->product_variant_id,
                        'quantity' => $i->quantity,
                        'reserved' => $i->reserved,
                        'available' => $i->availableQuantity(),
                        'track_stock' => (bool) $i->track_stock,
                        'low_stock_threshold' => $i->low_stock_threshold,
                        'variant_name' => $i->variant?->name,
                        'sku' => $i->variant?->sku ?: $product->sku,
                    ])->values(),
                'public_url' => route('storefront.product', [$request->user()->store->username, $product->slug]),
            ],
            'types' => $this->typeOptions(),
            'categories' => ProductCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'uploadLimits' => $this->uploadLimits(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeProduct($request, $product);

        $validated = $this->validated($request, $product);
        $gallery = $request->file('gallery', []);
        $removedMediaIds = array_map('intval', $validated['removed_media_ids'] ?? []);
        unset($validated['gallery'], $validated['gallery_posters'], $validated['removed_media_ids']);
        $data = $this->prepareMarketplaceData($this->normalizeTypeData($validated), $product);
        unset($data['initial_stock']);

        if ($thumbnail = $this->storeThumbnail($request)) {
            // Drop the replaced file, unless it is shared demo artwork.
            if ($product->thumbnail_path && ! str_starts_with($product->thumbnail_path, 'demo/')) {
                Storage::disk(config('jualanyok.uploads.disk'))->delete($product->thumbnail_path);
            }

            $data['thumbnail_path'] = $thumbnail;
        }

        $this->ensureDeliverableWhenActive($product, $data);

        $product->update($data);
        $this->syncTypeExtras($product);
        $this->removeGallery($product, $removedMediaIds);
        $this->storeGallery($product, $gallery, $request->file('gallery_posters', []));

        return back()->with('success', 'Produk diperbarui.');
    }

    public function show(Request $request, Product $product)
    {
        return redirect()->route('creator.products.edit', $product);
    }

    public function destroy(Request $request, Product $product)
    {
        $this->authorizeProduct($request, $product);

        // Soft delete keeps historical order lines intact.
        $product->delete();

        return redirect()->route('creator.products.index')->with('success', 'Produk dihapus.');
    }

    public function duplicate(Request $request, Product $product)
    {
        $this->authorizeProduct($request, $product);

        $copy = $product->replicate(['sales_count', 'view_count']);
        $copy->name = $product->name.' (salinan)';
        $copy->slug = $this->uniqueSlug($product->store_id, $copy->name);
        $copy->status = ProductStatus::Draft;
        $copy->sales_count = 0;
        $copy->view_count = 0;
        $copy->save();

        return redirect()->route('creator.products.edit', $copy)->with('success', 'Produk diduplikasi.');
    }

    public function updateStock(Request $request, Product $product)
    {
        $this->authorizeProduct($request, $product);
        abort_unless($product->type === ProductType::Physical, 404);

        $data = $request->validate([
            'inventory_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['required', Rule::in(['restock', 'correction', 'damaged', 'return'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var Inventory $inventory */
        $inventory = $product->inventories()->whereKey($data['inventory_id'])->firstOrFail();
        $updated = $this->inventory->setQuantity(
            $inventory,
            $data['quantity'],
            $data['reason'],
            $request->user(),
            $data['note'] ?? null,
            $data['low_stock_threshold'],
        );

        if ($updated->availableQuantity() <= $updated->low_stock_threshold) {
            $empty = $updated->availableQuantity() === 0;
            $this->notifications->sendOnce($request->user(), [
                'type' => $empty ? 'inventory.out' : 'inventory.low',
                'category' => 'inventory',
                'priority' => $empty ? 'high' : 'normal',
                'title' => $empty ? 'Stok produk habis' : 'Stok produk menipis',
                'message' => sprintf('%s tersisa %d unit dan perlu diperiksa.', $product->name, $updated->availableQuantity()),
                'url' => route('creator.products.edit', $product),
                'action_label' => 'Perbarui stok',
                'action_required' => true,
                'group_key' => 'inventory:'.$updated->id.':'.($empty ? 'out' : 'low'),
                'tone' => $empty ? 'danger' : 'warning',
                'email_required' => $empty,
                'meta' => ['product_id' => $product->id, 'inventory_id' => $updated->id],
            ], 12);
        }

        return back()->with('success', sprintf(
            'Stok %s diperbarui menjadi %d unit (%d tersedia).',
            $updated->variant?->name ?: $product->name,
            $updated->quantity,
            $updated->availableQuantity(),
        ));
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(ProductType::class)],
            'name' => ['required', 'string', 'max:190'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:50000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'gte:price'],
            'is_pay_what_you_want' => ['boolean'],
            'minimum_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'visibility' => ['required', Rule::in(['public', 'unlisted', 'private'])],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'is_marketplace_listed' => ['boolean'],
            'marketplace_category_id' => [
                Rule::requiredIf($request->boolean('is_marketplace_listed')),
                'nullable',
                'exists:product_categories,id',
            ],
            'tags' => ['nullable', 'array'],
            'sku' => ['nullable', 'string', 'max:64'],
            'weight_gram' => [Rule::requiredIf($request->input('type') === ProductType::Physical->value), 'nullable', 'integer', 'min:1', 'max:1000000'],
            'length_cm' => [Rule::requiredIf($request->input('type') === ProductType::Physical->value), 'nullable', 'integer', 'min:1', 'max:500'],
            'width_cm' => [Rule::requiredIf($request->input('type') === ProductType::Physical->value), 'nullable', 'integer', 'min:1', 'max:500'],
            'height_cm' => [Rule::requiredIf($request->input('type') === ProductType::Physical->value), 'nullable', 'integer', 'min:1', 'max:500'],
            'shipping_category' => ['nullable', Rule::in(['fashion', 'food', 'electronics', 'health_beauty', 'documents', 'others'])],
            'is_fragile' => ['boolean'],
            'min_quantity' => ['integer', 'min:1'],
            'max_quantity' => ['nullable', 'integer', 'gte:min_quantity'],
            'sales_limit' => ['nullable', 'integer', 'min:1'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date', 'after:sale_starts_at'],
            'seo_title' => ['nullable', 'string', 'max:190'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'checkout_message' => ['nullable', 'string', 'max:2000'],
            'post_purchase_message' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'affiliate_enabled' => ['boolean'],
            'external_url' => [
                Rule::requiredIf($request->input('type') === ProductType::External->value),
                'nullable',
                'url:http,https',
                'max:500',
            ],
            'custom_fields' => ['nullable', 'array'],
            'initial_stock' => [
                Rule::requiredIf($request->input('type') === ProductType::Physical->value && ! $product),
                'nullable',
                'integer',
                'min:0',
                'max:100000000',
            ],
            'gallery' => ['nullable', 'array', 'max:8'],
            'gallery.*' => [
                'file',
                /*
                 * Judged by the file's real type rather than its name: this
                 * lands in a publicly served folder, so an executable renamed
                 * .jpg must never get in.
                 */
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm',
                'max:'.config('jualanyok.uploads.video_max_kb'),
            ],
            'removed_media_ids' => ['nullable', 'array', 'max:8'],
            'removed_media_ids.*' => ['integer'],
            // A still captured from each video in the browser, keyed by its
            // position in the gallery.
            'gallery_posters' => ['nullable', 'array', 'max:8'],
            'gallery_posters.*' => ['file', 'mimetypes:image/jpeg,image/webp', 'max:1024'],
        ]);

        $newImages = count($request->file('gallery', []));
        $keptImages = 0;

        if ($product) {
            $removedIds = array_map('intval', $data['removed_media_ids'] ?? []);
            $removedOwned = $removedIds === [] ? 0 : $product->media()->whereKey($removedIds)->count();
            $keptImages = max(0, $product->media()->count() - $removedOwned);
        }

        if ($keptImages + $newImages > 8) {
            throw ValidationException::withMessages([
                'gallery' => 'Galeri produk maksimal berisi 8 file.',
            ]);
        }

        // An image cap that also applied to video would let one long clip use
        // the whole allowance, so each kind is checked against its own limit.
        foreach ($request->file('gallery', []) as $index => $file) {
            $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');
            $limitKb = (int) config($isVideo ? 'jualanyok.uploads.video_max_kb' : 'jualanyok.uploads.image_max_kb');

            if ($file->getSize() > $limitKb * 1024) {
                throw ValidationException::withMessages([
                    "gallery.{$index}" => $isVideo
                        ? 'Video maksimal '.round($limitKb / 1024).' MB.'
                        : 'Gambar maksimal '.round($limitKb / 1024).' MB.',
                ]);
            }
        }

        return $data;
    }

    /** External products are catalog links, never JualanYok checkout lines. */
    private function normalizeTypeData(array $data): array
    {
        if (($data['type'] ?? null) !== ProductType::External->value) {
            return $data;
        }

        return array_merge($data, [
            'price' => 0,
            'compare_at_price' => null,
            'is_pay_what_you_want' => false,
            'minimum_price' => null,
            'min_quantity' => 1,
            'max_quantity' => null,
            'sales_limit' => null,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
            'affiliate_enabled' => false,
        ]);
    }

    /** Creator controls distribution, while only moderation can approve it. */
    private function prepareMarketplaceData(array $data, ?Product $product = null): array
    {
        $listed = (bool) ($data['is_marketplace_listed'] ?? false);

        if (! $listed) {
            return array_merge($data, [
                'marketplace_status' => MarketplaceStatus::Draft,
                'marketplace_category_id' => null,
                'rejection_reason' => null,
                'moderated_at' => null,
                'moderated_by' => null,
                'featured_at' => null,
                'featured_until' => null,
            ]);
        }

        $listingChanged = ! $product
            || ! $product->is_marketplace_listed
            || (int) $product->marketplace_category_id !== (int) ($data['marketplace_category_id'] ?? 0)
            || $product->name !== ($data['name'] ?? $product->name)
            || $product->short_description !== ($data['short_description'] ?? null)
            || (float) $product->price !== (float) ($data['price'] ?? $product->price);

        return array_merge($data, [
            'marketplace_status' => $listingChanged
                ? MarketplaceStatus::PendingReview
                : $product->marketplace_status,
            'rejection_reason' => $listingChanged ? null : $product->rejection_reason,
            'moderated_at' => $listingChanged ? null : $product->moderated_at,
            'moderated_by' => $listingChanged ? null : $product->moderated_by,
            'marketplace_quality_score' => collect([
                ! empty($data['name']),
                ! empty($data['short_description']),
                ! empty($data['description']),
                ! empty($data['marketplace_category_id']),
                ! empty($data['seo_description']),
                (bool) ($product?->thumbnail_path),
            ])->filter()->count() * 15,
        ]);
    }

    /**
     * Refuses to publish a digital product that has nothing to deliver.
     *
     * Without this the product would sit on the storefront looking normal,
     * take payment, and hand the buyer an empty order.
     */
    private function ensureDeliverableWhenActive(Product $product, array $data): void
    {
        if (($data['status'] ?? null) !== ProductStatus::Active->value) {
            return;
        }

        if (($data['type'] ?? $product->type->value) !== ProductType::Digital->value) {
            return;
        }

        if (! empty($data['external_url']) || $product->files()->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'Produk digital butuh minimal satu file (atau tautan eksternal) sebelum bisa diaktifkan. Buka tab "File".',
        ]);
    }

    private function uploadLimits(): array
    {
        return [
            'mimes' => config('jualanyok.uploads.file_mimes'),
            'max_kb' => config('jualanyok.uploads.file_max_kb'),
        ];
    }

    /** Creates the type-specific companion row the product needs. */
    private function syncTypeExtras(Product $product): void
    {
        match ($product->type) {
            ProductType::Course => $product->course()->firstOrCreate([]),
            ProductType::Event => $product->event()->firstOrCreate([], ['starts_at' => now()->addWeek()]),
            ProductType::Service => $product->service()->firstOrCreate([]),
            ProductType::Physical => Inventory::firstOrCreate(
                ['product_id' => $product->id, 'product_variant_id' => null],
                ['quantity' => 0, 'track_stock' => true],
            ),
            default => null,
        };
    }

    private function storeThumbnail(Request $request): ?string
    {
        if (! $request->hasFile('thumbnail')) {
            return null;
        }

        $request->validate([
            'thumbnail' => [
                'image',
                'mimes:'.implode(',', config('jualanyok.uploads.image_mimes')),
                'max:'.config('jualanyok.uploads.image_max_kb'),
            ],
        ]);

        // Randomised name: the original filename never reaches the disk.
        return $request->file('thumbnail')->store('products/thumbnails', config('jualanyok.uploads.disk'));
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int|string, UploadedFile>  $posters
     */
    private function storeGallery(Product $product, array $files, array $posters = []): void
    {
        $position = (int) $product->media()->max('position');
        $disk = config('jualanyok.uploads.disk');

        foreach ($files as $index => $file) {
            $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');
            $poster = $isVideo ? ($posters[$index] ?? null) : null;

            $product->media()->create([
                'path' => $file->store('products/gallery', $disk),
                'kind' => $isVideo ? 'video' : 'image',
                'poster_path' => $poster?->store('products/posters', $disk),
                'alt' => $product->name,
                'position' => ++$position,
            ]);
        }
    }

    /** @param array<int, int> $ids */
    private function removeGallery(Product $product, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $product->media()->whereKey($ids)->get()->each(function ($media) {
            if (! str_starts_with($media->path, 'demo/')) {
                Storage::disk(config('jualanyok.uploads.disk'))->delete(
                    array_filter([$media->path, $media->poster_path]),
                );
            }

            $media->delete();
        });
    }

    private function uniqueSlug(int $storeId, string $name): string
    {
        $base = Str::slug($name) ?: 'produk';
        $slug = $base;
        $i = 1;

        while (Product::where('store_id', $storeId)->where('slug', $slug)->withTrashed()->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    private function typeOptions(): array
    {
        return collect(ProductType::cases())
            ->map(fn (ProductType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'needs_shipping' => $t->needsShipping(),
                'auto_fulfilled' => $t->isAutoFulfilled(),
            ])
            ->all();
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->store_id === $request->user()->store->id, 403);
    }
}
