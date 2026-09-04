<?php

namespace App\Support;

/**
 * Indonesian mobile numbers, in the shape payment gateways expect.
 *
 * People type their number every way imaginable — `+62 812-3456-7890`,
 * `0812 3456 7890`, `62812.3456.7890`. Passing that straight through is enough
 * for a gateway's risk check to reject the whole transaction, and the rejection
 * comes back as something vague about the buyer rather than "your phone field
 * had spaces in it".
 */
class Phone
{
    /**
     * Digits only, starting `08`. Returns null when there is nothing usable,
     * so the caller can ask for a real number instead of sending a broken one.
     */
    public static function local(?string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        /*
         * Strip the country code however it was written, then re-add the 0.
         * `0062` is checked before `62`, since the international dialling prefix
         * is a perfectly ordinary way to write it and would otherwise survive
         * into the number.
         */
        if (str_starts_with($digits, '0062')) {
            $digits = '0'.substr($digits, 4);
        } elseif (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        } elseif (! str_starts_with($digits, '0')) {
            $digits = '0'.$digits;
        }

        // An Indonesian mobile is 08 plus 8–12 digits; anything outside that is
        // a typo or a landline, and better refused than silently rejected later.
        if (! preg_match('/^08\d{8,12}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    /** The same number as `628…`, which some gateways insist on instead. */
    public static function international(?string $raw): ?string
    {
        $local = self::local($raw);

        return $local === null ? null : '62'.substr($local, 1);
    }
}
