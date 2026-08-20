<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget('platform.settings');

        static::saved($flush);
        static::deleted($flush);
    }

    /** All settings as a flat key => value map, cached for the request cycle. */
    public static function all_settings(): array
    {
        return Cache::remember('platform.settings', now()->addHour(), function () {
            return static::query()->pluck('value', 'key')->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all_settings()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value, string $group = 'general'): self
    {
        return tap(static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]));
    }
}
