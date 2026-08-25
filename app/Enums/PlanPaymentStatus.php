<?php

namespace App\Enums;

enum PlanPaymentStatus: string
{
    /** QR issued, waiting for the payer to transfer. */
    case Pending = 'PENDING';

    /** Payer says they have sent it; an admin still has to confirm. */
    case AwaitingReview = 'AWAITING_REVIEW';

    case Paid = 'PAID';

    case Rejected = 'REJECTED';

    case Failed = 'FAILED';

    case Expired = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pembayaran',
            self::AwaitingReview => 'Menunggu konfirmasi admin',
            self::Paid => 'Lunas',
            self::Rejected => 'Ditolak',
            self::Failed => 'Gagal dibuat',
            self::Expired => 'Kedaluwarsa',
        };
    }

    /** The amount is still reserved for this payment. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::AwaitingReview], true);
    }

    public function isSettled(): bool
    {
        return $this === self::Paid;
    }
}
