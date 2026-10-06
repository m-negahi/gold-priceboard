<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Baze ye chart e panel (P5-08) — ALLOWLIST e vorudi ye `/dashboard/charts`.
 *
 * Baze haye kutah mostaghim yek `Interval` (yek bucket e Influx)-and.
 * `w1`/`mo1`/`y1` bucket nadaran: az candle e RUZANE ba taghvim e JALALI
 * tajmi' mishan (hafte az shanbe، mah/sal e shamsi) — Flux faghat mah e
 * miladi mifahme. (`candles_1d` retention e bi-nahayat dare.)
 */
enum ChartPeriod: string
{
    use EnumTrait;

    case M5 = 'm5';
    case M15 = 'm15';
    case H1 = 'h1';
    case H4 = 'h4';
    case D1 = 'd1';
    case W1 = 'w1';
    case MO1 = 'mo1';
    case Y1 = 'y1';

    public static function default(): self
    {
        return self::D1;
    }

    /** Bucket e Influx ke data azash khunde mishe. */
    public function base(): Interval
    {
        return match ($this) {
            self::M5 => Interval::M5,
            self::M15 => Interval::M15,
            self::H1 => Interval::H1,
            self::H4 => Interval::H4,
            self::D1, self::W1, self::MO1, self::Y1 => Interval::D1,
        };
    }

    /**
     * Vahed e tajmi' e taghvimi — `null` ya'ni hamun bar e Influx.
     *
     * @return 'week'|'month'|'year'|null
     */
    public function calendar(): ?string
    {
        return match ($this) {
            self::W1 => 'week',
            self::MO1 => 'month',
            self::Y1 => 'year',
            default => null,
        };
    }

    /**
     * Che moddat be aghab.
     *
     * 🔴 Bayad zir e `CandleRequestDto::MAX_LIMIT` (5000 bar) bemune — vagarna
     * chart SAKET az aval boride mishe. Test ham hamin ro check mikone.
     */
    public function lookbackSeconds(): int
    {
        return match ($this) {
            self::M5 => 2 * 86_400,
            self::M15 => 7 * 86_400,
            self::H1 => 30 * 86_400,
            self::H4 => 120 * 86_400,
            self::D1 => 365 * 86_400,
            self::W1 => 2 * 365 * 86_400,
            self::MO1 => 5 * 365 * 86_400,
            self::Y1 => 13 * 365 * 86_400,
        };
    }

    public static function labels(): array
    {
        return [
            self::M5->value => '۵ دقیقه',
            self::M15->value => '۱۵ دقیقه',
            self::H1->value => '۱ ساعت',
            self::H4->value => '۴ ساعت',
            self::D1->value => 'روزانه',
            self::W1->value => 'هفتگی',
            self::MO1->value => 'ماهانه',
            self::Y1->value => 'سالانه',
        ];
    }
}
