<?php

namespace App\Services;

use App\Models\DigitalAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves purchased files.
 *
 * Storage paths are never exposed. The client only ever sees a short-lived
 * signed URL keyed by an opaque access token, and every condition is checked
 * again at download time — a link that was valid when generated can still be
 * refused if the access was revoked or the quota ran out.
 */
class DigitalDeliveryService
{
    public function signedUrl(DigitalAccess $access, int $minutes = 15): string
    {
        return URL::temporarySignedRoute(
            'downloads.serve',
            now()->addMinutes($minutes),
            ['token' => $access->token],
        );
    }

    public function download(DigitalAccess $access, Request $request): StreamedResponse
    {
        abort_unless($access->isDownloadable(), 403, 'Akses download sudah tidak berlaku.');

        $file = $access->file;
        abort_unless($file, 404);

        // External-hosted products redirect instead of streaming.
        abort_if($file->external_url !== null, 409, 'File ini dihosting di luar JualanYok.');

        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->path), 404, 'File tidak ditemukan.');

        $access->increment('download_count');
        $access->downloads()->create([
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        $filename = $this->safeFilename($file->name);

        return $disk->download($file->path, $filename);
    }

    /** Strips anything that could escape the download directory or a header. */
    private function safeFilename(string $name): string
    {
        $name = preg_replace('/[^\pL\pN\s._-]+/u', '', $name) ?? 'file';

        return trim(substr($name, 0, 120)) ?: 'file';
    }
}
