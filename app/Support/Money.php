<?php

namespace App\Support;

/**
 * Money helpers. Every amount in the system is a decimal string/float with two
 * decimal places — never a float accumulated over many operations. Rounding is
 * applied at each boundary so the ledger always balances to the cent.
 */
final class Money
{
    public static function round(float $amount): float
    {
        return round($amount, 2);
    }

    /** Percentage of an amount, rounded to the cent. */
    public static function percent(float $amount, float $percent): float
    {
        return self::round($amount * $percent / 100);
    }

    public static function format(float|string|null $amount, string $currency = 'IDR'): string
    {
        $value = (float) ($amount ?? 0);

        if ($currency === 'IDR') {
            return 'Rp'.number_format($value, 0, ',', '.');
        }

        return $currency.' '.number_format($value, 2, '.', ',');
    }

    /** Compares two money values tolerating float representation noise. */
    public static function equals(float $a, float $b): bool
    {
        return abs($a - $b) < 0.005;
    }

    public static function isAtLeast(float $amount, float $minimum): bool
    {
        return $amount > $minimum || self::equals($amount, $minimum);
    }
}
