<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Interval;

/**
 * Yek series e candle + meta-i ke KLineCharts lazem dare.
 *
 * `displayDivisor = 1`: IRR tu UI ham rial ast; USD ba scale e khodesh mimune.
 * Storage va frontend hich tabdil e rial/toman nadaran.
 */
final readonly class CandleSeriesDto
{
    /** @param array<int, CandleDto> $candles */
    public function __construct(
        public array $candles,
        public Instrument $instrument,
        public Interval $interval,
        public string $providerKey,
        public int $spreadVersion = 0,
        /** `true` = az cache e `:last` umad chun Influx javab nadad. */
        public bool $degraded = false,
    ) {}

    public function isEmpty(): bool
    {
        return $this->candles === [];
    }

    public function count(): int
    {
        return count($this->candles);
    }

    public function etag(): string
    {
        $last = $this->candles === [] ? 0 : $this->candles[count($this->candles) - 1]->timestamp;

        return sprintf('"c%d-s%d-%d"', $last, $this->spreadVersion, $this->count());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'symbol' => $this->instrument->value,
            'provider' => $this->providerKey,
            'interval' => $this->interval->value,
            'quote' => $this->instrument->quote()->value,
            'precision' => $this->instrument->quote()->displayPrecision(),
            'displayDivisor' => $this->instrument->quote()->displayDivisor(),
            'spreadVersion' => $this->spreadVersion,
            'degraded' => $this->degraded,
            'data' => array_map(static fn (CandleDto $c): array => $c->toArray(), $this->candles),
        ];
    }
}
