<?php

namespace App\Models;

use App\Enums\BalanceBucket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'pending_balance' => 'decimal:2',
            'available_balance' => 'decimal:2',
            'held_balance' => 'decimal:2',
            'reserve_balance' => 'decimal:2',
            'negative_balance' => 'decimal:2',
            'withdrawn_balance' => 'decimal:2',
            'lifetime_earned' => 'decimal:2',
            'is_frozen' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function balance(BalanceBucket $bucket): float
    {
        return (float) $this->{$bucket->column()};
    }

    /**
     * Recomputes the cached buckets straight from the immutable ledger.
     * Used by tests and by the admin reconciliation command — the cached
     * columns should always already agree with this.
     */
    public function recalculateFromLedger(): array
    {
        $totals = [];

        foreach (BalanceBucket::cases() as $bucket) {
            $totals[$bucket->column()] = (float) $this->entries()
                ->where('bucket', $bucket->value)
                ->sum('amount');
        }

        return $totals;
    }
}
