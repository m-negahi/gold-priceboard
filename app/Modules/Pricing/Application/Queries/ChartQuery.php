<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Application\Dto\CandleDto;
use App\Modules\Pricing\Application\Dto\CandleRequestDto;
use App\Modules\Pricing\Application\Support\CandleAggregator;
use App\Modules\Pricing\Application\Support\JalaliBucket;
use App\Modules\Pricing\Domain\Enums\ChartPeriod;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Domain\Services\PriorityChain;

/**
 * Series e chart e panel e admin (P5-08).
 *
 * Baze haye kutah = hamun `CandleQuery` (ba spread e e'mal-shode، mesl e
 * chart e omumi). `w1`/`mo1`/`y1` = candle e ruzane + tajmi' e JALALI.
 *
 * Provider e pish-farz = avvalin manba' e zanjire ye OLAVIAT e hamun symbol
 * (P5-07) — chart va feed e mo'asser az yek manba' harf mizanan.
 */
final readonly class ChartQuery
{
    public function __construct(
        private CandleQuery $candles,
        private SymbolPriorityQuery $priorities,
        private ClockPort $clock,
    ) {}

    /**
     * @return array{candles: list<CandleDto>, provider: string, degraded: bool}
     */
    public function series(Instrument $instrument, string $providerKey, ChartPeriod $period): array
    {
        $chain = PriorityChain::order(
            $this->priorities->globalOrder(),
            $this->priorities->customMap()[$instrument->value] ?? [],
        );

        if (! in_array($providerKey, $chain, true)) {
            $providerKey = $chain[0] ?? '';
        }

        if ($providerKey === '') {
            return ['candles' => [], 'provider' => '', 'degraded' => true];
        }

        $to = $this->clock->now()->getTimestamp();

        $series = $this->candles->candles(CandleRequestDto::make(
            providerKey: $providerKey,
            instrument: $instrument,
            interval: $period->base(),
            from: $to - $period->lookbackSeconds(),
            to: $to,
            side: Side::Last,
        ));

        $bars = array_values($series->candles);
        $unit = $period->calendar();

        if ($unit !== null) {
            $bars = CandleAggregator::aggregate($bars, static fn (int $ms): string => JalaliBucket::key($unit, $ms));
        }

        return ['candles' => $bars, 'provider' => $providerKey, 'degraded' => $series->degraded];
    }
}
