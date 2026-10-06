<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Morilog\Jalali\Jalalian;

/**
 * Kelid e bucket e taghvimi baraye chart (P5-08): hafte (az SHANBE)، mah va
 * sal e SHAMSI.
 *
 * 🔴 Hame chiz be vaght e TEHRAN: bar e ruzane az 00:00 e Tehran shoru'
 * mishe، ya'ni timestamp esh `20:30Z` e rooz e GHABL ast. Bucket e UTC
 * 1 Mehr ro tu Shahrivar mindaze.
 */
final class JalaliBucket
{
    private const TIMEZONE = 'Asia/Tehran';

    /** @param 'week'|'month'|'year' $unit */
    public static function key(string $unit, int $timestampMs): string
    {
        $local = CarbonImmutable::createFromTimestampMs($timestampMs)->setTimezone(self::TIMEZONE);

        return match ($unit) {
            // Shanbe = 6 tu Carbon؛ chand rooz az shanbe gozashte؟
            'week' => $local->subDays(($local->dayOfWeek - CarbonImmutable::SATURDAY + 7) % 7)->format('Y-m-d'),
            'month' => Jalalian::fromDateTime($local)->format('Y-m'),
            'year' => Jalalian::fromDateTime($local)->format('Y'),
            default => throw new InvalidArgumentException("vahed e taghvim e nashenas: {$unit}"),
        };
    }
}
