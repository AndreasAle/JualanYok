<?php

namespace App\Enums;

enum WithdrawalStatus: string
{
    case Requested = 'REQUESTED';
    case UnderReview = 'UNDER_REVIEW';
    case Approved = 'APPROVED';
    case Processing = 'PROCESSING';
    case Paid = 'PAID';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';
    case Failed = 'FAILED';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Diajukan',
            self::UnderReview => 'Sedang Ditinjau',
            self::Approved => 'Disetujui',
            self::Processing => 'Diproses',
            self::Paid => 'Dana Cair',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
            self::Failed => 'Gagal',
        };
    }

    /** Funds are still held against the wallet in these states. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::UnderReview, self::Approved, self::Processing], true);
    }

    public function isCancellableByOwner(): bool
    {
        return in_array($this, [self::Requested, self::UnderReview], true);
    }

    /** Held funds must be returned to the available bucket. */
    public function isReversal(): bool
    {
        return in_array($this, [self::Rejected, self::Cancelled, self::Failed], true);
    }
}
