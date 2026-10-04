<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Arz e marja' e har instrument.
 *
 * `scale` ya'ni chand raghame ashar tu meghdar e `minor` jaa shode:
 *   IRR -> 0  (rial sahih ast, ashar ma'ni nadare)
 *   USD -> 4  (ons e tala 4140.1900 -> minor = 41_401_900)
 *
 * In adad ru HAR radif e DB va tu HAR payload e Redis ham zakhire mishe,
 * ta hich reader-i baraye tafsir e adad majbur nabashe in enum ro bebine.
 */
enum QuoteCurrency: string
{
    use EnumTrait;

    case IRR = 'IRR';
    case USD = 'USD';

    public function scale(): int
    {
        return match ($this) {
            self::IRR => 0,
            self::USD => 4,
        };
    }

    /**
     * Faghat baraye NAMAYESH: rial -> toman.
     * 🔴 Hargez tu storage estefade nashe — DB va Influx hamishe rial negah midaran.
     */
    public function displayDivisor(): int
    {
        return $this === self::IRR ? 10 : 1;
    }

    /** Chand raghame ashar tu UI neshun dade she. */
    public function displayPrecision(): int
    {
        return $this === self::IRR ? 0 : 2;
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::IRR->value => 'ریال',
            self::USD->value => 'دلار',
        ];
    }
}
