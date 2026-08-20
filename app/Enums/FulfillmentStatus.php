<?php

namespace App\Enums;

enum FulfillmentStatus: string
{
    case Unfulfilled = 'UNFULFILLED';
    case Ready = 'READY';
    case Fulfilled = 'FULFILLED';
    case Shipped = 'SHIPPED';
    case Delivered = 'DELIVERED';
    case Cancelled = 'CANCELLED';
    case Returned = 'RETURNED';

    public function label(): string
    {
        return match ($this) {
            self::Unfulfilled => 'Belum Diproses',
            self::Ready => 'Siap Kirim',
            self::Fulfilled => 'Terkirim Otomatis',
            self::Shipped => 'Dikirim',
            self::Delivered => 'Diterima',
            self::Cancelled => 'Dibatalkan',
            self::Returned => 'Dikembalikan',
        };
    }
}
