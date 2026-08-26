<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Scheduled = 'scheduled';
    case Allocated = 'allocated';
    case PickingUp = 'picking_up';
    case Picked = 'picked';
    case InTransit = 'in_transit';
    case DroppingOff = 'dropping_off';
    case Delivered = 'delivered';
    case OnHold = 'on_hold';
    case ReturnInTransit = 'return_in_transit';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case CourierNotFound = 'courier_not_found';
    case Cancelled = 'cancelled';
    case Disposed = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu booking',
            self::Confirmed => 'Siap dijemput',
            self::Scheduled => 'Penjemputan dijadwalkan',
            self::Allocated => 'Kurir dialokasikan',
            self::PickingUp => 'Kurir menuju penjual',
            self::Picked => 'Paket sudah dijemput',
            self::InTransit => 'Dalam perjalanan',
            self::DroppingOff => 'Kurir menuju penerima',
            self::Delivered => 'Paket diterima',
            self::OnHold => 'Pengiriman tertahan',
            self::ReturnInTransit => 'Dikembalikan ke penjual',
            self::Returned => 'Sudah kembali ke penjual',
            self::Rejected => 'Pengiriman ditolak',
            self::CourierNotFound => 'Kurir tidak tersedia',
            self::Cancelled => 'Pengiriman dibatalkan',
            self::Disposed => 'Paket dimusnahkan',
        };
    }

    public function isMoving(): bool
    {
        return in_array($this, [
            self::Allocated, self::PickingUp, self::Picked, self::InTransit, self::DroppingOff,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Delivered, self::Returned, self::Rejected, self::CourierNotFound,
            self::Cancelled, self::Disposed,
        ], true);
    }
}
