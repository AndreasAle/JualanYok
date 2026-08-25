<?php

namespace App\Models;

use App\Enums\PlanPaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PlanPayment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PlanPaymentStatus::class,
            'gateway_fee' => 'decimal:2',
            'instructions' => 'array',
            'gateway_response' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'paid_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public static function generateReference(): string
    {
        return 'SUB-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PlanPaymentStatus::Pending->value,
            PlanPaymentStatus::AwaitingReview->value,
        ]);
    }

    public function hasExpired(): bool
    {
        return $this->status->isOpen() && $this->expires_at->isPast();
    }

    /** Seconds the payer has left, floored at zero. */
    public function secondsLeft(): int
    {
        return max(0, now()->diffInSeconds($this->expires_at, false));
    }
}
