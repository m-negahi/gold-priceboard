<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Bazeh ye candle baraye chart.
 *
 * In yek ALLOWLIST ast: hich reshte ye dast-saz be Flux nemirese. Esm e bucket
 * ham az hamin miad, pas yek meghdar e na-motabar nemitune be yek bucket e
 * delkhah eshare kone.
 *
 * ⚠️ `m1` amdan `ticks` ro mikhune (na yek bucket e candle): hamishe be-rooz
 * ast va be latency e task e Influx vabaste nist.
 */
enum Interval: string
{
    use EnumTrait;

    case M1 = 'm1';
    case M5 = 'm5';
    case M15 = 'm15';
    case H1 = 'h1';
    case H4 = 'h4';
    case D1 = 'd1';

    public static function default(): self
    {
        return self::M5;
    }

    /** Tool e har bar be saniye — baraye quantize kardan e bazeh. */
    public function seconds(): int
    {
        return match ($this) {
            self::M1 => 60,
            self::M5 => 300,
            self::M15 => 900,
            self::H1 => 3_600,
            self::H4 => 14_400,
            self::D1 => 86_400,
        };
    }

    /** Vahed e `every` tu Flux. */
    public function fluxWindow(): string
    {
        return match ($this) {
            self::M1 => '1m',
            self::M5 => '5m',
            self::M15 => '15m',
            self::H1 => '1h',
            self::H4 => '4h',
            self::D1 => '1d',
        };
    }

    /**
     * Bucket-i ke in interval azash khunde mishe.
     * `m1` az `ticks` mikhune va ru parvaz aggregate mishe.
     */
    public function bucket(): string
    {
        return match ($this) {
            self::M1 => 'ticks',
            self::M5 => 'candles_5m',
            self::M15 => 'candles_15m',
            self::H1 => 'candles_1h',
            self::H4 => 'candles_4h',
            self::D1 => 'candles_1d',
        };
    }

    public function readsRawTicks(): bool
    {
        return $this === self::M1;
    }

    /** TTL e tabagheh ye «taze» ye cache — motanaseb ba andaze ye bar. */
    public function cacheSeconds(): int
    {
        return match ($this) {
            self::M1 => 15,
            self::M5 => 60,
            self::M15 => 120,
            self::H1 => 300,
            self::H4 => 600,
            self::D1 => 900,
        };
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::M1->value => '۱ دقیقه',
            self::M5->value => '۵ دقیقه',
            self::M15->value => '۱۵ دقیقه',
            self::H1->value => '۱ ساعت',
            self::H4->value => '۴ ساعت',
            self::D1->value => '۱ روز',
        ];
    }
}
