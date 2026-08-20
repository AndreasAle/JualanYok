<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'minimum_price' => 'decimal:2',
            'is_pay_what_you_want' => 'boolean',
            'affiliate_enabled' => 'boolean',
            'tags' => 'array',
            'custom_fields' => 'array',
            'settings' => 'array',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('position');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class)->orderBy('position');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function course(): HasOne
    {
        return $this->hasOne(Course::class);
    }

    public function event(): HasOne
    {
        return $this->hasOne(Event::class);
    }

    public function service(): HasOne
    {
        return $this->hasOne(Service::class);
    }

    public function membershipPlans(): HasMany
    {
        return $this->hasMany(MembershipPlan::class);
    }

    public function affiliateProgram(): HasOne
    {
        return $this->hasOne(AffiliateProgram::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /* ------------------------------------------------------------------ */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active->value);
    }

    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query->active()->where('visibility', 'public');
    }

    /** Price actually charged today, honouring a scheduled promo price. */
    public function effectivePrice(): float
    {
        return (float) $this->price;
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_price !== null
            && (float) $this->compare_at_price > (float) $this->price;
    }

    public function discountPercent(): int
    {
        if (! $this->isOnSale()) {
            return 0;
        }

        $compare = (float) $this->compare_at_price;

        return (int) round((($compare - (float) $this->price) / $compare) * 100);
    }

    /**
     * Whether the product can be added to a cart right now. Covers status,
     * visibility, the sale window and the overall sales limit.
     */
    public function isBuyable(): bool
    {
        if (! $this->status->isBuyable() || ! $this->type->isPurchasable()) {
            return false;
        }
        if ($this->sale_starts_at && $this->sale_starts_at->isFuture()) {
            return false;
        }
        if ($this->sale_ends_at && $this->sale_ends_at->isPast()) {
            return false;
        }
        if ($this->sales_limit !== null && $this->sales_count >= $this->sales_limit) {
            return false;
        }

        return true;
    }

    public function thumbnailUrl(): ?string
    {
        return Media::url($this->thumbnail_path);
    }
}
