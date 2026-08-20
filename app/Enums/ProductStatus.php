<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'DRAFT';
    case Active = 'ACTIVE';
    case Archived = 'ARCHIVED';
    case Suspended = 'SUSPENDED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Aktif',
            self::Archived => 'Diarsipkan',
            self::Suspended => 'Ditangguhkan',
        };
    }

    public function isBuyable(): bool
    {
        return $this === self::Active;
    }
}
