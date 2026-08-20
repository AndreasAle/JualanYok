<?php

namespace App\Support;

/**
 * Builds public URLs for stored images.
 *
 * Always root-relative on purpose. Absolute URLs would bake the current host
 * into block content and product records, so every image would break the
 * moment the app moves to another domain, gains a custom domain, or simply
 * runs on a different port than APP_URL claims.
 */
final class Media
{
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        // Already a full URL (an image the creator linked from elsewhere).
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }
}
