<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates designed cover art for products and store avatars.
 *
 * Digital products (e-books, templates, courses) genuinely ship with designed
 * covers rather than photographs, so a generated cover is an honest stand-in —
 * unlike a stock photo, it never implies a physical item we do not have.
 *
 * Output is a deterministic SVG: the same name always yields the same artwork,
 * so re-seeding does not shuffle the demo storefront's appearance.
 */
final class CoverArt
{
    /** Palettes picked to stay legible with white type on top. */
    private const PALETTES = [
        ['#6D28D9', '#A855F7'],
        ['#0EA5E9', '#2563EB'],
        ['#047857', '#10B981'],
        ['#DB2777', '#F472B6'],
        ['#EA580C', '#FB923C'],
        ['#B45309', '#F59E0B'],
        ['#4F46E5', '#818CF8'],
        ['#0F766E', '#14B8A6'],
    ];

    /**
     * Writes a square cover to the public disk and returns its storage path.
     */
    public static function product(string $name, string $typeLabel, string $directory = 'demo/products'): string
    {
        $slug = Str::slug($name) ?: 'produk';
        $path = "{$directory}/{$slug}.svg";

        Storage::disk('public')->put($path, self::productSvg($name, $typeLabel));

        return $path;
    }

    /**
     * @param  string|null  $primary  Store theme colour; falls back to a hashed palette.
     */
    public static function avatar(string $name, ?string $primary = null, ?string $accent = null, string $directory = 'demo/avatars'): string
    {
        $slug = Str::slug($name) ?: 'toko';
        $path = "{$directory}/{$slug}.svg";

        Storage::disk('public')->put($path, self::avatarSvg($name, $primary, $accent));

        return $path;
    }

    public static function cover(string $name, ?string $primary = null, ?string $accent = null, string $directory = 'demo/covers'): string
    {
        $slug = Str::slug($name) ?: 'toko';
        $path = "{$directory}/{$slug}-cover.svg";

        Storage::disk('public')->put($path, self::coverSvg($name, $primary, $accent));

        return $path;
    }

    /* ------------------------------------------------------------------ */

    /** Store theme colours when available, otherwise a stable hashed pair. */
    private static function brandPalette(string $seed, ?string $primary, ?string $accent): array
    {
        if ($primary) {
            return [$primary, $accent ?: $primary];
        }

        return self::paletteFor($seed);
    }

    private static function paletteFor(string $seed): array
    {
        $index = abs(crc32($seed)) % count(self::PALETTES);

        return self::PALETTES[$index];
    }

    /** Wraps a title onto at most three lines that fit the cover width. */
    private static function wrap(string $text, int $perLine = 16, int $maxLines = 3): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";

            if (mb_strlen($candidate) > $perLine && $current !== '') {
                $lines[] = $current;
                $current = $word;

                if (count($lines) === $maxLines) {
                    break;
                }
            } else {
                $current = $candidate;
            }
        }

        if (count($lines) < $maxLines && $current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function productSvg(string $name, string $typeLabel): string
    {
        [$from, $to] = self::paletteFor($name);

        $lines = self::wrap($name);
        $startY = 470 - (count($lines) - 1) * 46;

        $title = '';
        foreach ($lines as $i => $line) {
            $y = $startY + $i * 62;
            $title .= sprintf(
                '<text x="72" y="%d" font-family="Plus Jakarta Sans, Segoe UI, sans-serif" font-size="52" font-weight="800" fill="#fff">%s</text>',
                $y,
                self::esc($line),
            );
        }

        $seed = abs(crc32($name));
        $blobX = 620 + ($seed % 120);
        $blobY = 160 + (($seed >> 3) % 100);

        $typeLabelUpper = self::esc(mb_strtoupper($typeLabel));
        $typeLabelWidth = 48 + mb_strlen($typeLabelUpper) * 15;
        $underlineY = $startY + (count($lines) - 1) * 62 + 34;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800" role="img" aria-label="{$name}">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$from}"/>
      <stop offset="100%" stop-color="{$to}"/>
    </linearGradient>
    <pattern id="dots" width="34" height="34" patternUnits="userSpaceOnUse">
      <circle cx="3" cy="3" r="2.5" fill="#fff" opacity="0.18"/>
    </pattern>
  </defs>

  <rect width="800" height="800" fill="url(#g)"/>
  <rect width="800" height="800" fill="url(#dots)"/>

  <circle cx="{$blobX}" cy="{$blobY}" r="150" fill="#fff" opacity="0.10"/>
  <circle cx="120" cy="700" r="190" fill="#000" opacity="0.07"/>

  <rect x="72" y="72" width="{$typeLabelWidth}" height="46" rx="23" fill="#fff" opacity="0.22"/>
  <text x="96" y="103" font-family="Plus Jakarta Sans, Segoe UI, sans-serif" font-size="22" font-weight="700" fill="#fff" letter-spacing="1.5">{$typeLabelUpper}</text>

  {$title}

  <rect x="72" y="{$underlineY}" width="120" height="8" rx="4" fill="#fff" opacity="0.85"/>
</svg>
SVG;
    }

    private static function avatarSvg(string $name, ?string $primary, ?string $accent): string
    {
        [$from, $to] = self::brandPalette($name, $primary, $accent);
        $initial = self::esc(mb_strtoupper(mb_substr(trim($name), 0, 1)));

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400" role="img" aria-label="{$name}">
  <defs>
    <linearGradient id="a" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$from}"/>
      <stop offset="100%" stop-color="{$to}"/>
    </linearGradient>
  </defs>
  <rect width="400" height="400" fill="url(#a)"/>
  <circle cx="320" cy="80" r="110" fill="#fff" opacity="0.12"/>
  <text x="200" y="258" text-anchor="middle" font-family="Plus Jakarta Sans, Segoe UI, sans-serif" font-size="190" font-weight="800" fill="#fff">{$initial}</text>
</svg>
SVG;
    }

    private static function coverSvg(string $name, ?string $primary, ?string $accent): string
    {
        [$from, $to] = self::brandPalette($name, $primary, $accent);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="500" viewBox="0 0 1600 500" role="img" aria-label="Sampul {$name}">
  <defs>
    <linearGradient id="c" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$from}"/>
      <stop offset="100%" stop-color="{$to}"/>
    </linearGradient>
    <pattern id="cd" width="46" height="46" patternUnits="userSpaceOnUse">
      <circle cx="4" cy="4" r="3" fill="#fff" opacity="0.16"/>
    </pattern>
  </defs>
  <rect width="1600" height="500" fill="url(#c)"/>
  <rect width="1600" height="500" fill="url(#cd)"/>
  <circle cx="1320" cy="90" r="230" fill="#fff" opacity="0.10"/>
  <circle cx="240" cy="470" r="210" fill="#000" opacity="0.08"/>
</svg>
SVG;
    }
}
