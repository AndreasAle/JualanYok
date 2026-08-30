<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Small, dependency-free Code 128-B renderer for courier waybill numbers.
 *
 * Waybills are printable ASCII. Rendering locally keeps recipient and shipment
 * data away from third-party barcode services and makes labels work offline.
 */
class Code128Barcode
{
    /** @var array<int, string> */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    public static function svg(string $value, int $height = 72, int $module = 2): string
    {
        if ($value === '' || ! preg_match('/^[\x20-\x7E]+$/', $value)) {
            throw new InvalidArgumentException('Code 128 hanya menerima karakter ASCII yang dapat dicetak.');
        }

        $codes = [104]; // Start Code B.
        $checksum = 104;

        foreach (str_split($value) as $position => $character) {
            $code = ord($character) - 32;
            $codes[] = $code;
            $checksum += $code * ($position + 1);
        }

        $codes[] = $checksum % 103;
        $codes[] = 106;

        $quietModules = 10;
        $x = $quietModules;
        $rectangles = [];

        foreach ($codes as $code) {
            $pattern = self::PATTERNS[$code];
            foreach (str_split($pattern) as $index => $width) {
                $width = (int) $width;
                if ($index % 2 === 0) {
                    $rectangles[] = sprintf(
                        '<rect x="%d" y="0" width="%d" height="%d"/>',
                        $x * $module,
                        $width * $module,
                        $height,
                    );
                }
                $x += $width;
            }
        }

        $width = ($x + $quietModules) * $module;

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Barcode nomor resi %s" viewBox="0 0 %d %d" width="100%%" height="%d" preserveAspectRatio="none"><g fill="#000">%s</g></svg>',
            htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            $width,
            $height,
            $height,
            implode('', $rectangles),
        );
    }
}
