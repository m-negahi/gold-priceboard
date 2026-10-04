<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Ports;

use App\Modules\Pricing\Application\Dto\CandleDto;
use App\Modules\Pricing\Application\Dto\CandleRequestDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\ValueObjects\TickTime;

/**
 * Tarikhche — hame ye tick va candle (InfluxDB 2.7).
 *
 * Chart FAGHAT az inja mikhune. `price_snapshots` tarikhche nadare.
 */
interface TimeSeriesPort
{
    /**
     * Neveshtan e yek batch e tick.
     *
     * HAME ye point ha hamun yek `TickTime` ro migiran. Chun hoviyat e yek
     * point tu Influx `(measurement, tagset, field, timestamp)` ast، timestamp
     * e floor-shode ya'ni replay e spool **overwrite** mikone na duplicate —
     * va hamin chizi-e ke replay ro emn mikone.
     *
     * @param  array<int, SnapshotDto>  $snapshots
     *
     * @throws \RuntimeException agar neveshtan shekast bokhore. Caller bayad
     *                           be spool berize، na inke tick ro bokoshe.
     */
    public function writeTicks(array $snapshots, TickTime $at): void;

    /**
     * @return array<int, CandleDto> be tartib e soudi ye zaman
     *
     * @throws \RuntimeException agar query shekast bokhore. Caller bayad be
     *                           cache e `:last` fallback kone.
     */
    public function candles(CandleRequestDto $request): array;

    public function isAvailable(): bool;
}
