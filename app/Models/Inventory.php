<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    protected $table = 'inventories';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'track_stock' => 'boolean',
            'allow_backorder' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function availableQuantity(): int
    {
        return max(0, $this->quantity - $this->reserved);
    }

    public function canFulfil(int $quantity): bool
    {
        if (! $this->track_stock || $this->allow_backorder) {
            return true;
        }

        return $this->availableQuantity() >= $quantity;
    }

    public function isLowStock(): bool
    {
        return $this->track_stock && $this->availableQuantity() <= $this->low_stock_threshold;
    }
}
