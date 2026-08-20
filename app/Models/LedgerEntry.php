<?php

namespace App\Models;

use App\Enums\BalanceBucket;
use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Append-only ledger. Entries are written once by LedgerService and are never
 * mutated afterwards — a correction is always a new, opposite-signed entry.
 */
class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'bucket' => BalanceBucket::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Ledger entries are immutable.');
        });

        static::deleting(function () {
            throw new RuntimeException('Ledger entries cannot be deleted.');
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function isCredit(): bool
    {
        return (float) $this->amount > 0;
    }
}
