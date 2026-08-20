<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'TRIALING';
    case Active = 'ACTIVE';
    case PastDue = 'PAST_DUE';
    case Cancelled = 'CANCELLED';
    case Expired = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Masa Coba',
            self::Active => 'Aktif',
            self::PastDue => 'Menunggak',
            self::Cancelled => 'Dibatalkan',
            self::Expired => 'Kedaluwarsa',
        };
    }

    /**
     * Plan features stay granted while past due so the creator keeps a grace
     * period to settle the invoice before the store degrades to the free plan.
     */
    public function grantsAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue], true);
    }
}
