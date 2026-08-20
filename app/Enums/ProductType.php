<?php

namespace App\Enums;

enum ProductType: string
{
    case Digital = 'DIGITAL';
    case Physical = 'PHYSICAL';
    case Course = 'COURSE';
    case Event = 'EVENT';
    case Service = 'SERVICE';
    case Membership = 'MEMBERSHIP';
    case Donation = 'DONATION';
    case External = 'EXTERNAL';

    public function label(): string
    {
        return match ($this) {
            self::Digital => 'Produk Digital',
            self::Physical => 'Produk Fisik',
            self::Course => 'Kelas Online',
            self::Event => 'Webinar / Event',
            self::Service => 'Jasa / Konsultasi',
            self::Membership => 'Membership',
            self::Donation => 'Donasi / Bayar Seikhlasnya',
            self::External => 'Produk Affiliate',
        };
    }

    /** Delivered by the system the moment payment lands, with no seller action. */
    public function isAutoFulfilled(): bool
    {
        return in_array($this, [self::Digital, self::Course, self::Membership, self::Donation], true);
    }

    public function needsShipping(): bool
    {
        return $this === self::Physical;
    }

    public function tracksStock(): bool
    {
        return $this === self::Physical;
    }

    /** Product is bought and paid for on JualanYok. */
    public function isPurchasable(): bool
    {
        return $this !== self::External;
    }
}
