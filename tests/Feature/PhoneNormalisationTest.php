<?php

namespace Tests\Feature;

use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phone numbers as people actually type them.
 *
 * A gateway's risk check rejects a malformed number by refusing the whole
 * transaction, and the refusal says nothing about the phone field — so a stray
 * space reads as "your buyer looks suspicious". Normalising first removes the
 * most common cause of a rejection nobody can explain.
 */
class PhoneNormalisationTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function numbers(): array
    {
        return [
            'sudah rapi' => ['081234567890', '081234567890'],
            'pakai spasi' => ['0812 3456 7890', '081234567890'],
            'pakai strip' => ['0812-3456-7890', '081234567890'],
            'kode negara +62' => ['+62 812 3456 7890', '081234567890'],
            'kode negara 62' => ['62812345 67890', '081234567890'],
            'kode negara 0062' => ['0062812 3456 7890', '081234567890'],
            'tanpa nol depan' => ['81234567890', '081234567890'],
            'pakai titik' => ['0812.3456.7890', '081234567890'],
            'ada teks' => ['wa: 0812-3456-7890', '081234567890'],

            'kosong' => ['', null],
            'null' => [null, null],
            'huruf saja' => ['hubungi saya', null],
            'terlalu pendek' => ['0812345', null],
            'terlalu panjang' => ['0812345678901234', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_a_number_is_normalised_or_refused(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, Phone::local($input));
    }

    public function test_the_international_form_matches_the_local_one(): void
    {
        $this->assertSame('6281234567890', Phone::international('0812 3456 7890'));
        $this->assertSame('6281234567890', Phone::international('+62-812-3456-7890'));
        $this->assertNull(Phone::international('bukan nomor'));
    }

    public function test_a_normalised_number_never_carries_anything_but_digits(): void
    {
        foreach (self::numbers() as [$input, $expected]) {
            $result = Phone::local($input);

            if ($result !== null) {
                $this->assertMatchesRegularExpression(
                    '/^\d+$/',
                    $result,
                    "Anything but digits is what the gateway chokes on: {$input}",
                );
            }
        }
    }
}
