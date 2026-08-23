<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductVariant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'dimensions' => 'array',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    /**
     * Variants may inherit the parent product price.
     *
     * Resolved without touching the relation lazily: variants are almost always
     * loaded as a collection under their product, where an implicit lazy load
     * is both an N+1 and a hard failure under strict mode.
     */
    public function effectivePrice(): float
    {
        if ($this->price !== null) {
            return (float) $this->price;
        }

        $product = $this->relationLoaded('product')
            ? $this->product
            : Product::find($this->product_id);

        return (float) ($product?->price ?? 0);
    }
}
