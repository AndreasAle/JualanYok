<?php

namespace App\Enums;

/**
 * Wallet buckets. Every ledger entry lands in exactly one bucket, so the
 * cached wallet totals can always be rebuilt by replaying the ledger.
 */
enum BalanceBucket: string
{
    case Pending = 'PENDING';
    case Available = 'AVAILABLE';
    case Held = 'HELD';
    case Reserve = 'RESERVE';
    case Negative = 'NEGATIVE';
    case Withdrawn = 'WITHDRAWN';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Saldo Tertahan',
            self::Available => 'Saldo Tersedia',
            self::Held => 'Saldo Dibekukan',
            self::Reserve => 'Dana Cadangan',
            self::Negative => 'Saldo Negatif',
            self::Withdrawn => 'Sudah Ditarik',
        };
    }

    public function column(): string
    {
        return match ($this) {
            self::Pending => 'pending_balance',
            self::Available => 'available_balance',
            self::Held => 'held_balance',
            self::Reserve => 'reserve_balance',
            self::Negative => 'negative_balance',
            self::Withdrawn => 'withdrawn_balance',
        };
    }
}
