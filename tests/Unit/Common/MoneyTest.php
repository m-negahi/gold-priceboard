<?php

declare(strict_types=1);

namespace Tests\Unit\Common;

use App\Modules\Common\Helpers\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    /** @return array<string, array{int, string}> */
    public static function displayCases(): array
    {
        return [
            'sefr' => [0, '0'],
            'kamtar az yek toman' => [5, '0.5'],
            'gerd' => [1_234_560, '123,456'],
            'ba ashar' => [1_234_565, '123,456.5'],
            'manfi' => [-1_234_560, '-123,456'],
            'bozorg' => [999_999_999_999_990, '99,999,999,999,999'],
        ];
    }

    #[DataProvider('displayCases')]
    public function test_rial_to_toman(int $rial, string $expected): void
    {
        $this->assertSame($expected, Money::rialToToman($rial));
    }

    /** @return array<string, array{string, int|null}> */
    public static function parseCases(): array
    {
        return [
            'sade' => ['125000', 1_250_000],
            'ba comma' => ['1,250,000', 12_500_000],
            'raghame farsi' => ['۱۲۵۰۰۰', 1_250_000],
            'separator e farsi' => ['۱٬۲۵۰٬۰۰۰', 12_500_000],
            'ashar' => ['12.5', 125],
            'ashar e farsi' => ['۱۲٫۵', 125],
            // 🔴 Float in ro 0.30000000000000004 mikard.
            'daghighat' => ['0.3', 3],
            'bozorg bedun e gom kardan' => ['99999999999999', 999_999_999_999_990],
            'do ragham ashar' => ['12.55', null],
            'manfi' => ['-5', null],
            'horuf' => ['abc', null],
            'khali' => ['', null],
            'kheyli bozorg' => ['999999999999999', null],
        ];
    }

    #[DataProvider('parseCases')]
    public function test_toman_to_rial(string $input, ?int $expected): void
    {
        $this->assertSame($expected, Money::tomanToRial($input));
    }
}
