<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'custom_fields' => 'array',
            'shipping_address' => 'array',
            'utm' => 'array',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'payment_fee' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'seller_net' => 'decimal:2',
            'affiliate_commission' => 'decimal:2',
            'refunded_total' => 'decimal:2',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'shipped_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /**
     * Human-readable but not guessable: the date gives support staff context
     * while the random suffix keeps order volume private.
     */
    public static function generateNumber(): string
    {
        do {
            $number = sprintf('JY-%s-%s', now()->format('Ymd'), Str::upper(Str::random(6)));
        } while (static::where('number', $number)->exists());

        return $number;
    }

    /* ------------------------------------------------------------------ */

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affiliate_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Every order gets its permanent delivery key at creation, so a receipt can
     * always link the buyer straight to what they bought — account or not.
     */
    protected static function booted(): void
    {
        static::creating(function (self $order) {
            $order->access_token ??= Str::random(48);
        });
    }

    /** Public, login-free page where the buyer collects their purchase. */
    public function deliveryUrl(): string
    {
        return route('order.access', $this->access_token);
    }

    public function digitalAccesses(): HasMany
    {
        return $this->hasMany(DigitalAccess::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    /* ------------------------------------------------------------------ */

    public function scopePaid(Builder $query): Builder
    {
        return $query->whereIn('status', [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Completed->value,
        ]);
    }

    public function isPayable(): bool
    {
        return $this->status->isOpen() && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function requiresShipping(): bool
    {
        return $this->items->contains(fn (OrderItem $item) => $item->product_type === 'PHYSICAL');
    }

    public function refundableAmount(): float
    {
        return max(0, (float) $this->grand_total - (float) $this->refunded_total);
    }
}
