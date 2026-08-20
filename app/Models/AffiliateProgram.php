<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateProgram extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'commission_value' => 'decimal:2',
            'auto_approve' => 'boolean',
            'is_active' => 'boolean',
            'promo_materials' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(AffiliateApplication::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(AffiliateLink::class);
    }

    /** Commission owed on a given line total. */
    public function commissionFor(float $baseAmount): float
    {
        $value = (float) $this->commission_value;

        $amount = $this->commission_type === 'fixed'
            ? $value
            : $baseAmount * $value / 100;

        return round(min($amount, $baseAmount), 2);
    }

    public function rate(): float
    {
        return $this->commission_type === 'percentage' ? (float) $this->commission_value / 100 : 0.0;
    }
}
