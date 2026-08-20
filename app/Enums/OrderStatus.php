<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'DRAFT';
    case PendingPayment = 'PENDING_PAYMENT';
    case Paid = 'PAID';
    case Processing = 'PROCESSING';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
    case Expired = 'EXPIRED';
    case RefundRequested = 'REFUND_REQUESTED';
    case Refunded = 'REFUNDED';
    case PartiallyRefunded = 'PARTIALLY_REFUNDED';
    case Disputed = 'DISPUTED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingPayment => 'Menunggu Pembayaran',
            self::Paid => 'Dibayar',
            self::Processing => 'Diproses',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
            self::Expired => 'Kedaluwarsa',
            self::RefundRequested => 'Refund Diajukan',
            self::Refunded => 'Direfund',
            self::PartiallyRefunded => 'Refund Sebagian',
            self::Disputed => 'Sengketa',
        };
    }

    /** Order states where the money has actually been captured. */
    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::Processing, self::Completed], true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::PendingPayment], true);
    }
}
