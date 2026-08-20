<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public const FREE = 'free';

    public const CREATOR = 'creator';

    public const PRO = 'pro';

    public const BUSINESS = 'business';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'transaction_fee_percent' => 'decimal:4',
            'transaction_fee_fixed' => 'decimal:2',
            'highlights' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public static function free(): self
    {
        return static::where('slug', self::FREE)->firstOrFail();
    }

    public function feature(string $key): ?PlanFeature
    {
        return $this->relationLoaded('features')
            ? $this->features->firstWhere('key', $key)
            : $this->features()->where('key', $key)->first();
    }
}
