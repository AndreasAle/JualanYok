<?php

namespace App\Enums;

enum CommissionStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Paid = 'PAID';
    case Rejected = 'REJECTED';
    case Reversed = 'REVERSED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Paid => 'Dibayar',
            self::Rejected => 'Ditolak',
            self::Reversed => 'Dibatalkan',
        };
    }
}
