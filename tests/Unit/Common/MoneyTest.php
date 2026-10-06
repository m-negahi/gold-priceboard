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
}
