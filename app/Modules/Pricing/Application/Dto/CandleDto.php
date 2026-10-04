<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

/**
 * Yek bar e OHLC — daghighan tu shekl-i ke KLineCharts mikhahe.
 *
 * `timestamp` be MILLISECOND ast (sharт e KLineCharts) va be lahze ye BAZ
 * shodan e bar eshare mikone، na baste shodan. (Tu Flux ba `timeSrc: "_start"`
 * ta'min mishe.)
 *
 * `volume` tedad e tick ast، na hajm e moamele — ma hajm nadarim va pane ye
 * sefr e saf tu chart shekaste be nazar mirese.
 */
final readonly class CandleDto
{
    public function __construct(
        public int $timestamp,
        public float $open,
        public float $high,
        public float $low,
        public float $close,
        public float $volume = 0.0,
    ) {}

    /** @return array{timestamp: int, open: float, high: float, low: float, close: float, volume: float} */
    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp,
            'open' => $this->open,
            'high' => $this->high,
            'low' => $this->low,
            'close' => $this->close,
            'volume' => $this->volume,
        ];
    }

    /** @param array{timestamp: int|string, open: float|string, high: float|string, low: float|string, close: float|string, volume?: float|string} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['timestamp'],
            (float) $data['open'],
            (float) $data['high'],
            (float) $data['low'],
            (float) $data['close'],
            (float) ($data['volume'] ?? 0),
        );
    }
}
