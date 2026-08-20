<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Support\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Shared image uploads for storefront blocks.
 *
 * Blocks store their images as URLs inside a JSON payload, so the file has to
 * be uploaded first and the resulting URL written into the block. Every file
 * lands in a folder scoped to the creator's own store.
 */
class MediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $store = $request->user()->store;

        abort_unless($store, 403);

        $request->validate([
            'file' => [
                'required',
                'image',
                'mimes:'.implode(',', config('jualanyok.uploads.image_mimes')),
                'max:'.config('jualanyok.uploads.image_max_kb'),
            ],
        ], [
            'file.image' => 'File harus berupa gambar.',
            'file.mimes' => 'Format yang didukung: '.implode(', ', config('jualanyok.uploads.image_mimes')).'.',
            'file.max' => 'Ukuran maksimal '.round(config('jualanyok.uploads.image_max_kb') / 1024).' MB.',
        ]);

        // Laravel generates a random filename, so the uploader's original name
        // never reaches the disk or the public URL.
        $path = $request->file('file')->store("stores/{$store->id}/blocks", 'public');

        return response()->json([
            'path' => $path,
            'url' => Media::url($path),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $store = $request->user()->store;

        abort_unless($store, 403);

        $data = $request->validate(['path' => ['required', 'string']]);

        // Scoping the delete to the store's own folder stops a crafted path
        // from removing someone else's file.
        abort_unless(str_starts_with($data['path'], "stores/{$store->id}/"), 403);

        Storage::disk('public')->delete($data['path']);

        return response()->json(['deleted' => true]);
    }
}
