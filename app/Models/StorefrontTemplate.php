<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorefrontTemplate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'theme' => 'array',
            'blueprint' => 'array',
            'is_premium' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }
}
