<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Turns a merchant's static QRIS code into a dynamic one with the amount baked in.
 *
 * QRIS payloads are EMVCo TLV strings: each field is a two-digit tag, a two-digit
 * length, then the value. A static code (tag 01 = "11") can be scanned any number
 * of times and the payer types the amount themselves. A dynamic code (tag 01 =
 * "12") carries the amount in tag 54, so the payer's app fills it in and cannot
 * change it — which is what makes an exact-amount match trustworthy on our side.
 *
 * The last eight characters are always "6304" plus a CRC-16/CCITT-FALSE checksum
 * over everything before it, so any edit means recomputing the checksum.
 */
class Qris
{
    /** Fields that only belong to a dynamic code; stripped before rebuilding. */
    private const AMOUNT_TAGS = ['54', '55', '56', '57'];

    private const TAG_POINT_OF_INITIATION = '01';

    private const TAG_COUNTRY = '58';

    private const TAG_CRC = '6304';

    /**
     * @param  int  $amount  Rupiah, whole numbers only.
     * @param  int|null  $feeFixed  Flat service fee added on top, in rupiah.
     * @param  float|null  $feePercent  Percentage service fee; ignored when a flat fee is given.
     */
    public static function dynamic(string $staticPayload, int $amount, ?int $feeFixed = null, ?float $feePercent = null): string
    {
        if ($amount < 1) {
            throw new InvalidArgumentException('Nominal QRIS harus lebih dari nol.');
        }

        $body = str_ends_with(substr($staticPayload, -8, 4), '6304')
            ? substr($staticPayload, 0, -8)
            : $staticPayload;

        $fields = array_values(array_filter(
            self::parse($body),
            fn (array $field) => ! in_array($field[0], self::AMOUNT_TAGS, true),
        ));

        foreach ($fields as $index => [$tag, $value]) {
            if ($tag === self::TAG_POINT_OF_INITIATION) {
                // 11 = reusable static code, 12 = single-use dynamic code.
                $fields[$index] = [$tag, '12'];
            }
        }

        $extra = [['54', (string) $amount]];

        if ($feeFixed !== null) {
            $extra[] = ['55', '02'];
            $extra[] = ['56', (string) $feeFixed];
        } elseif ($feePercent !== null) {
            $extra[] = ['55', '03'];
            $extra[] = ['57', rtrim(rtrim(number_format($feePercent, 2, '.', ''), '0'), '.')];
        }

        // The amount fields belong ahead of the country code, per EMVCo ordering.
        $countryIndex = null;

        foreach ($fields as $index => [$tag]) {
            if ($tag === self::TAG_COUNTRY) {
                $countryIndex = $index;
                break;
            }
        }

        if ($countryIndex === null) {
            throw new InvalidArgumentException('QRIS statis tidak valid: tag negara (58) tidak ditemukan.');
        }

        array_splice($fields, $countryIndex, 0, $extra);

        $payload = '';

        foreach ($fields as [$tag, $value]) {
            $payload .= self::tlv($tag, $value);
        }

        $payload .= self::TAG_CRC;

        return $payload.self::crc16($payload);
    }

    /** Cheap sanity check before a merchant code is accepted into config. */
    public static function looksValid(string $payload): bool
    {
        if (strlen($payload) < 20 || substr($payload, -8, 4) !== '6304') {
            return false;
        }

        $body = substr($payload, 0, -8);
        $expected = strtoupper(substr($payload, -4));

        if (self::crc16($body.self::TAG_CRC) !== $expected) {
            return false;
        }

        $tags = array_column(self::parse($body), 0);

        return in_array(self::TAG_COUNTRY, $tags, true);
    }

    /** Merchant name (tag 59), used to show the payer who they are paying. */
    public static function merchantName(string $payload): ?string
    {
        $body = str_ends_with(substr($payload, -8, 4), '6304') ? substr($payload, 0, -8) : $payload;

        foreach (self::parse($body) as [$tag, $value]) {
            if ($tag === '59') {
                return trim($value) ?: null;
            }
        }

        return null;
    }

    /**
     * Splits the top level of an EMVCo string into [tag, value] pairs.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private static function parse(string $payload): array
    {
        $fields = [];
        $offset = 0;
        $length = strlen($payload);

        while ($offset + 4 <= $length) {
            $tag = substr($payload, $offset, 2);
            $size = (int) substr($payload, $offset + 2, 2);

            if ($size <= 0 || $offset + 4 + $size > $length) {
                break;
            }

            $fields[] = [$tag, substr($payload, $offset + 4, $size)];
            $offset += 4 + $size;
        }

        return $fields;
    }

    private static function tlv(string $tag, string $value): string
    {
        return $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    /** CRC-16/CCITT-FALSE, the checksum EMVCo specifies for tag 63. */
    private static function crc16(string $data): string
    {
        $crc = 0xFFFF;

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = $crc & 0x8000
                    ? (($crc << 1) ^ 0x1021) & 0xFFFF
                    : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
