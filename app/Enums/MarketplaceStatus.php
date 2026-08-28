<?php

namespace App\Enums;

enum MarketplaceStatus: string
{
    case Draft = 'DRAFT';
    case PendingReview = 'PENDING_REVIEW';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Suspended = 'SUSPENDED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Belum diajukan',
            self::PendingReview => 'Menunggu review',
            self::Approved => 'Tayang di marketplace',
            self::Rejected => 'Perlu diperbaiki',
            self::Suspended => 'Ditangguhkan',
        };
    }
}
