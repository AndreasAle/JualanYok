<?php

namespace App\Models;

use App\Enums\DisputeStatus;
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
            'shipping_insurance' => 'decimal:2',
            'shipping_quote' => 'array',
            'tax_total' => 'decimal:2',
            'commission_base' => 'decimal:2',
            'platform_fee_rate' => 'decimal:4',
            'platform_fee' => 'decimal:2',
            'payment_fee' => 'decimal:2',
            'gateway_fee_estimated' => 'decimal:2',
            'gateway_fee_actual' => 'decimal:2',
            'split_fee_actual' => 'decimal:2',
            'contribution_margin' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'seller_net' => 'decimal:2',
            'reserve_amount' => 'decimal:2',
            'reserve_rate' => 'decimal:4',
            'debt_offset' => 'decimal:2',
            'shipping_cost_actual' => 'decimal:2',
            'shipping_variance' => 'decimal:2',
            'affiliate_commission' => 'decimal:2',
            'refunded_total' => 'decimal:2',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'auto_complete_at' => 'datetime',
            'complaint_deadline_at' => 'datetime',
            'funds_release_at' => 'datetime',
            'reserve_release_at' => 'datetime',
            'buyer_confirmed_at' => 'datetime',
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

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(OrderDispute::class);
    }

    public function openDispute(): HasOne
    {
        return $this->hasOne(OrderDispute::class)->whereIn('status', [
            DisputeStatus::Open->value,
            DisputeStatus::SellerResponded->value,
            DisputeStatus::UnderReview->value,
        ])->latestOfMany();
    }

    /**
     * Every order gets its permanent delivery key at creation, so a receipt can
     * always link the buyer straight to what they bought — account or not.
     */
    protected static function booted(): void
    {
        static::creating(function (self $order) {
            $order->access_token ??= Str::random(48);
            $order->tracking_code ??= static::generateTrackingCode();
        });
    }

    /** Public, login-free page where the buyer collects their purchase. */
    public function deliveryUrl(): string
    {
        return route('order.access', $this->access_token);
    }

    public static function generateTrackingCode(): string
    {
        do {
            $code = 'JYT-'.Str::upper(Str::random(16));
        } while (static::where('tracking_code', $code)->exists());

        return $code;
    }

    public function trackingUrl(): string
    {
        return route('tracking.show', $this->tracking_code);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(OrderTrackingEvent::class)->orderBy('occurred_at');
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

    public function canBuyerConfirmReceipt(): bool
    {
        return $this->requiresShipping()
            && $this->fulfillment_status === FulfillmentStatus::Delivered
            && $this->status === OrderStatus::Processing
            && ! $this->disputes()->whereIn('status', [
                DisputeStatus::Open->value,
                DisputeStatus::SellerResponded->value,
                DisputeStatus::UnderReview->value,
            ])->exists();
    }

    public function canOpenDispute(): bool
    {
        return $this->requiresShipping()
            && in_array($this->fulfillment_status, [FulfillmentStatus::Shipped, FulfillmentStatus::Delivered], true)
            && $this->status === OrderStatus::Processing
            && ($this->complaint_deadline_at === null || $this->complaint_deadline_at->isFuture())
            && ! $this->disputes()->whereIn('status', [
                DisputeStatus::Open->value,
                DisputeStatus::SellerResponded->value,
                DisputeStatus::UnderReview->value,
            ])->exists();
    }
}
