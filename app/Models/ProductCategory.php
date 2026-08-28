<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class ProductCategory extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        $forgetMarketplaceCache = static fn () => Cache::forget('marketplace.categories');

        static::saved($forgetMarketplaceCache);
        static::deleted($forgetMarketplaceCache);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function marketplaceProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'marketplace_category_id');
    }
}
