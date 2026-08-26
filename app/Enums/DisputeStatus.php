<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case Open = 'OPEN';
    case SellerResponded = 'SELLER_RESPONDED';
    case UnderReview = 'UNDER_REVIEW';
    case Resolved = 'RESOLVED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Menunggu respons penjual',
            self::SellerResponded => 'Penjual sudah merespons',
            self::UnderReview => 'Ditinjau JualanYok',
            self::Resolved => 'Selesai',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan pembeli',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::SellerResponded, self::UnderReview], true);
    }

    public function isClosed(): bool
    {
        return ! $this->isOpen();
    }
}
