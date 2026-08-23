<?php

namespace App\Http\Controllers\Creator;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\DigitalAccess;
use App\Models\Product;
use App\Models\ProductFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The deliverables a buyer receives after paying for a digital product.
 *
 * These files are the thing being sold, so unlike storefront imagery they are
 * written to the private disk and never get a public URL. Buyers only ever
 * reach them through a short-lived signed link keyed by their access token
 * (see DigitalDeliveryService).
 */
class ProductFileController extends Controller
{
    /** Digital products deliver files; other types have their own fulfilment path. */
    private const SUPPORTED_TYPES = [ProductType::Digital];

    public function store(Request $request, Product $product)
    {
        $this->authorizeProduct($request, $product);

        $data = $request->validate([
            'file' => [
                'required_without:external_url',
                'file',
                'mimes:'.implode(',', config('jualanyok.uploads.file_mimes')),
                'max:'.config('jualanyok.uploads.file_max_kb'),
            ],
            'external_url' => ['required_without:file', 'nullable', 'url', 'max:500'],
            'name' => ['nullable', 'string', 'max:190'],
            'version' => ['nullable', 'string', 'max:32'],
            'download_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'access_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'watermark_pdf' => ['boolean'],
        ], [
            'file.mimes' => 'Format yang didukung: '.implode(', ', config('jualanyok.uploads.file_mimes')).'.',
            'file.max' => 'Ukuran maksimal '.$this->maxMb().' MB.',
            'file.required_without' => 'Unggah file atau isi tautan eksternal.',
            'external_url.required_without' => 'Unggah file atau isi tautan eksternal.',
        ]);

        $upload = $request->file('file');

        $attributes = [
            'name' => ($data['name'] ?? null) ?: ($upload?->getClientOriginalName() ?? 'File'),
            'version' => ($data['version'] ?? null) ?: '1.0',
            'download_limit' => $data['download_limit'] ?? null,
            'access_days' => $data['access_days'] ?? null,
            'watermark_pdf' => $request->boolean('watermark_pdf'),
            'position' => (int) $product->files()->max('position') + 1,
        ];

        if ($upload) {
            $attributes += [
                'disk' => 'local',
                // Private disk, randomised filename: the download name shown to
                // the buyer is rebuilt from `name`, so the real path stays hidden.
                'path' => $upload->store($this->directory($product), 'local'),
                'mime_type' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
                'external_url' => null,
            ];
        } else {
            $attributes += [
                'disk' => 'local',
                'path' => null,
                'external_url' => $data['external_url'],
                'size' => 0,
            ];
        }

        $product->files()->create($attributes);

        return back()->with('success', 'File ditambahkan. Pembeli menerimanya otomatis setelah membayar.');
    }

    /**
     * Swaps the stored file while keeping the row — and therefore every existing
     * buyer's access — intact. This is how a creator ships an updated edition
     * without stranding the people who already paid.
     */
    public function replace(Request $request, Product $product, ProductFile $file)
    {
        $this->authorizeProduct($request, $product);
        $this->authorizeFile($product, $file);

        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', config('jualanyok.uploads.file_mimes')),
                'max:'.config('jualanyok.uploads.file_max_kb'),
            ],
            'version' => ['nullable', 'string', 'max:32'],
        ], [
            'file.mimes' => 'Format yang didukung: '.implode(', ', config('jualanyok.uploads.file_mimes')).'.',
            'file.max' => 'Ukuran maksimal '.$this->maxMb().' MB.',
        ]);

        $previousPath = $file->path;
        $upload = $request->file('file');

        $file->update([
            'disk' => 'local',
            'path' => $upload->store($this->directory($product), 'local'),
            'mime_type' => $upload->getClientMimeType(),
            'size' => $upload->getSize(),
            'external_url' => null,
            'version' => ($data['version'] ?? null) ?: $this->bumpVersion($file->version),
        ]);

        // Only drop the old blob once the replacement is safely recorded.
        if ($previousPath) {
            Storage::disk('local')->delete($previousPath);
        }

        return back()->with('success', 'File diperbarui. Semua pembeli lama otomatis mendapat versi terbaru.');
    }

    public function update(Request $request, Product $product, ProductFile $file)
    {
        $this->authorizeProduct($request, $product);
        $this->authorizeFile($product, $file);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'version' => ['nullable', 'string', 'max:32'],
            'download_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'access_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'watermark_pdf' => ['boolean'],
        ]);

        $file->update([
            'name' => $data['name'],
            'version' => ($data['version'] ?? null) ?: $file->version,
            'download_limit' => $data['download_limit'] ?? null,
            'access_days' => $data['access_days'] ?? null,
            'watermark_pdf' => $request->boolean('watermark_pdf'),
        ]);

        return back()->with('success', 'Detail file disimpan.');
    }

    public function destroy(Request $request, Product $product, ProductFile $file)
    {
        $this->authorizeProduct($request, $product);
        $this->authorizeFile($product, $file);

        // digital_accesses cascades on delete, so removing a file people already
        // bought would silently revoke their downloads. Replacing is the right
        // move there, and the UI offers it.
        $purchases = DigitalAccess::where('product_file_id', $file->id)->count();

        if ($purchases > 0) {
            throw ValidationException::withMessages([
                'file' => "File ini sudah dibeli {$purchases} kali. Menghapusnya akan mencabut akses pembeli — pakai \"Ganti file\" untuk mengunggah versi baru.",
            ]);
        }

        if ($file->path) {
            Storage::disk('local')->delete($file->path);
        }

        $file->delete();

        return back()->with('success', 'File dihapus.');
    }

    public function reorder(Request $request, Product $product)
    {
        $this->authorizeProduct($request, $product);

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        // Scoped to this product, so an id from another store is simply ignored.
        foreach ($data['ids'] as $position => $id) {
            $product->files()->whereKey($id)->update(['position' => $position]);
        }

        return back();
    }

    private function directory(Product $product): string
    {
        return "stores/{$product->store_id}/products/{$product->id}";
    }

    private function maxMb(): int
    {
        return (int) round(config('jualanyok.uploads.file_max_kb') / 1024);
    }

    /** "1.0" becomes "1.1"; anything unparseable just gains a suffix. */
    private function bumpVersion(?string $version): string
    {
        if ($version && preg_match('/^(\d+)\.(\d+)$/', $version, $matches)) {
            return $matches[1].'.'.($matches[2] + 1);
        }

        return $version ? $version.'-baru' : '1.1';
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->store_id === $request->user()->store?->id, 403);
        abort_unless(in_array($product->type, self::SUPPORTED_TYPES, true), 422);
    }

    private function authorizeFile(Product $product, ProductFile $file): void
    {
        abort_unless($file->product_id === $product->id, 403);
    }
}
