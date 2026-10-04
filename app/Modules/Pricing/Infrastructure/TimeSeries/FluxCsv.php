<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\TimeSeries;

use App\Modules\Pricing\Application\Dto\CandleDto;
use Carbon\CarbonImmutable;

/**
 * Parser e «annotated CSV» e Influx — mostaghim be shekl e KLineCharts.
 *
 * ── Chera dasti va na SDK ────────────────────────────────────────────
 * SDK baraye har radif yek `FluxRecord` misaze. Yek series e `h1` e
 * yek-sale 8,760 radif x 5 field ≈ 44k object ast — faghat baraye inke
 * ba'dash be array tabdil shan. In parser mostaghim `CandleDto` misaze.
 *
 * ── Shekl e khoruji e Influx ─────────────────────────────────────────
 *   #datatype,string,long,dateTime:RFC3339,...
 *   #group,false,false,false,...
 *   #default,_result,,,...
 *   ,result,table,_time,open,high,low,close,count
 *   ,,0,2026-10-04T12:00:00Z,266400000,266500000,266300000,266480000,4
 *
 * Khat haye `#` annotation-and va rad mishan؛ khat e ba'd esh header ast.
 * Influx mitune CHAND block bede (har table yek header e jadid)، pas
 * header har bar ke did mishe dobare khunde mishe.
 */
final readonly class FluxCsv
{
    /** @return array<int, CandleDto> */
    public static function toCandles(string $csv): array
    {
        $candles = [];
        $header = null;

        foreach (preg_split('/\r\n|\n|\r/', $csv) ?: [] as $line) {
            $line = rtrim($line);

            if ($line === '') {
                // Khat e khali = payan e yek block. Header e ba'di tazast.
                $header = null;

                continue;
            }

            // Annotation ha (`#datatype`, `#group`, `#default`) rad mishan.
            if (str_starts_with($line, '#')) {
                continue;
            }

            $cells = str_getcsv($line, ',', '"', '\\');

            if ($header === null) {
                $header = array_flip(array_map('trim', $cells));

                continue;
            }

            $candle = self::toCandle($cells, $header);

            if ($candle !== null) {
                $candles[] = $candle;
            }
        }

        return $candles;
    }

    /**
     * @param  array<int, string|null>  $cells
     * @param  array<string, int>  $header
     */
    private static function toCandle(array $cells, array $header): ?CandleDto
    {
        $value = static function (string $column) use ($cells, $header): ?string {
            $index = $header[$column] ?? null;

            return $index === null ? null : ($cells[$index] ?? null);
        };

        $time = $value('_time');

        if ($time === null || $time === '') {
            return null;
        }

        // Radif-i ke yeki az field haye OHLC ro nadare na-kamel ast va be
        // dard e chart nemikhore — saket rad mishe.
        foreach (['open', 'high', 'low', 'close'] as $field) {
            if (($value($field) ?? '') === '') {
                return null;
            }
        }

        return new CandleDto(
            // KLineCharts MILLISECOND mikhad.
            timestamp: CarbonImmutable::parse($time)->getTimestampMs(),
            open: (float) $value('open'),
            high: (float) $value('high'),
            low: (float) $value('low'),
            close: (float) $value('close'),
            volume: (float) ($value('count') ?? 0),
        );
    }

    /** Avvalin meghdar e `_time` — baraye query haye salamati. */
    public static function firstTime(string $csv): ?CarbonImmutable
    {
        $candles = self::toCandles($csv);

        if ($candles !== []) {
            return CarbonImmutable::createFromTimestampMsUTC($candles[0]->timestamp);
        }

        // Query haye salamati faghat `_time` ro keep mikonan (bedun e OHLC)،
        // pas `toCandles()` chizi bar nemigardune. Dasti donbal esh begard.
        $header = null;

        foreach (preg_split('/\r\n|\n|\r/', $csv) ?: [] as $line) {
            $line = rtrim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $cells = str_getcsv($line, ',', '"', '\\');

            if ($header === null) {
                $header = array_flip(array_map('trim', $cells));

                continue;
            }

            $index = $header['_time'] ?? null;
            $time = $index === null ? null : ($cells[$index] ?? null);

            if ($time !== null && $time !== '') {
                return CarbonImmutable::parse($time);
            }
        }

        return null;
    }
}
