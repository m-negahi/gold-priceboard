<?php

declare(strict_types=1);

namespace Tests\Unit\Common;

use App\Modules\Common\Helpers\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_rial_display_preserves_integer_boundaries_without_rounding(): void
    {
        self::assertSame('0', Money::rialDisplay(0));
        self::assertSame('5', Money::rialDisplay(5));
        self::assertSame('1,234,565', Money::rialDisplay(1_234_565));
        self::assertSame('9,223,372,036,854,775,807', Money::rialDisplay(PHP_INT_MAX));
        self::assertSame('-9,223,372,036,854,775,808', Money::rialDisplay(PHP_INT_MIN));
    }

    /** @return array<string, array{?string, ?int}> */
    public static function parseCases(): array
    {
        return [
            'rial bedun e zarib' => ['125000', 125000],
            'comma' => ['1,250,000', 1250000],
            'farsi' => ['۱٬۲۵۰٬۰۰۰', 1250000],
            'bozorg e daghigh' => ['999999999999999', 999999999999999],
            'sefr' => ['0', 0],
            'ashar' => ['12.5', null],
            'ashar farsi' => ['۱۲٫۵', null],
            'exponent' => ['1e6', null],
            'manfi' => ['-5', null],
            'khali' => ['', null],
            'null' => [null, null],
            'overflow' => ['9223372036854775808', null],
        ];
    }

    #[DataProvider('parseCases')]
    public function test_rial_input_is_exact_and_rejects_non_integer_amounts(?string $input, ?int $expected): void
    {
        self::assertSame($expected, Money::rialFromInput($input));
    }

    /** @return array<string, array{int, int, string}> */
    public static function tomanDisplayCases(): array
    {
        return [
            'sefr' => [0, 0, '0'],
            'gerd' => [266_400_000, 0, '26,640,000'],
            'nim-be-bala' => [267_561_155, 0, '26,756,116'],
            'zir e nim paeen' => [267_561_154, 0, '26,756,115'],
            'ba yek ashar' => [267_561_155, 1, '26,756,115.5'],
            'manfi' => [-1_234_560, 0, '-123,456'],
            'manfi ke gerd mishe sefr' => [-4, 0, '0'],
            'bozorg' => [999_999_999_999_990, 0, '99,999,999,999,999'],
        ];
    }

    #[DataProvider('tomanDisplayCases')]
    public function test_toman_display(int $rial, int $precision, string $expected): void
    {
        self::assertSame($expected, Money::tomanDisplay($rial, $precision));
    }

    /** Divisor faghat baraye rial 10 ast — dollar dast nemikhore. */
    public function test_toman_divisor_only_applies_to_rial(): void
    {
        self::assertSame(10, Money::RIAL_PER_TOMAN);
        self::assertSame(10, Money::tomanDivisor('IRR'));
        self::assertSame(1, Money::tomanDivisor('USD'));
    }
}
