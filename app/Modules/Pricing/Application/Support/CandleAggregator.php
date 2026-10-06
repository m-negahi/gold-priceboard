<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Support;

use App\Modules\Pricing\Application\Dto\CandleDto;

/**
 * Tajmi' e OHLC e bar haye riz be bar haye dorosht (P5-08) — mahz.
 *
 * Har bucket: open = open e AVVALIN bar، close = close e AKHARIN، high/low =
 * max/min، volume = jam'، timestamp = baz shodan e avvalin bar. OHLC-of-OHLC
 * daghigh ast (hamun cascade e task haye Influx).
 */
final class CandleAggregator
{
    /**
     * @param  list<CandleDto>  $bars
     * @param  callable(int): string  $bucketKey  timestamp (ms) -> kelid e bucket
     * @return list<CandleDto> moratab ba zaman
     */
    public static function aggregate(array $bars, callable $bucketKey): array
    {
        // Open/close az ZAMAN miad، na tartib e vorudi.
        usort($bars, static fn (CandleDto $a, CandleDto $b): int => $a->timestamp <=> $b->timestamp);

        /** @var array<string, CandleDto> $buckets */
        $buckets = [];

        foreach ($bars as $bar) {
            $key = $bucketKey($bar->timestamp);
            $current = $buckets[$key] ?? null;

            $buckets[$key] = $current === null
                ? $bar
                : new CandleDto(
                    timestamp: $current->timestamp,
                    open: $current->open,
                    high: max($current->high, $bar->high),
                    low: min($current->low, $bar->low),
                    close: $bar->close,
                    volume: $current->volume + $bar->volume,
                );
        }

        return array_values($buckets);
    }
}
