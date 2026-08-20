<?php

namespace App\Support;

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Usernames double as the public storefront URL, so they must be URL-safe,
 * unique across both users and stores, and must never shadow a platform route.
 */
final class Username
{
    public const PATTERN = '/^[a-z0-9](?:[a-z0-9._-]{1,28})[a-z0-9]$/';

    public static function normalize(string $value): string
    {
        return Str::lower(trim($value));
    }

    public static function isReserved(string $value): bool
    {
        return in_array(self::normalize($value), config('jualanyok.reserved_usernames', []), true);
    }

    public static function isValidFormat(string $value): bool
    {
        return (bool) preg_match(self::PATTERN, self::normalize($value));
    }

    public static function isTaken(string $value, ?int $ignoreUserId = null): bool
    {
        $value = self::normalize($value);

        $userTaken = User::where('username', $value)
            ->when($ignoreUserId, fn ($q) => $q->whereKeyNot($ignoreUserId))
            ->withTrashed()
            ->exists();

        $storeTaken = Store::where('username', $value)
            ->when($ignoreUserId, fn ($q) => $q->where('user_id', '!=', $ignoreUserId))
            ->withTrashed()
            ->exists();

        return $userTaken || $storeTaken;
    }

    /** @return array{available: bool, reason: ?string} */
    public static function check(string $value, ?int $ignoreUserId = null): array
    {
        $value = self::normalize($value);

        return match (true) {
            ! self::isValidFormat($value) => [
                'available' => false,
                'reason' => 'Username 3–30 karakter, huruf kecil/angka, boleh titik, strip, underscore di tengah.',
            ],
            self::isReserved($value) => [
                'available' => false,
                'reason' => 'Username ini dipakai sistem JualanYok.',
            ],
            self::isTaken($value, $ignoreUserId) => [
                'available' => false,
                'reason' => 'Yah, username ini sudah diambil.',
            ],
            default => ['available' => true, 'reason' => null],
        };
    }

    /** Derives an available username from a display name. */
    public static function suggestFrom(string $name): string
    {
        $base = Str::of($name)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->limit(20, '')->value();
        $base = $base !== '' ? $base : 'creator';

        $candidate = $base;
        $i = 1;

        while (self::isReserved($candidate) || self::isTaken($candidate) || ! self::isValidFormat($candidate)) {
            $candidate = $base.(++$i);
        }

        return $candidate;
    }
}
