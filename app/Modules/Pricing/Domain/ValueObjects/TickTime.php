<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * Zaman e yek tick — HAMISHE floor-shode ru grid e N saniye-i.
 *
 * Yek `TickTime` baraye KOL e yek tick sakhte mishe va hame ye provider ha,
 * hame ye symbol ha va hame ye side ha hamun ro migiran.
 *
 * ── Chera floor ──────────────────────────────────────────────────────
 * 1. IDEMPOTENCY: hoviyat e yek point tu Influx `(measurement, tagset, field,
 *    timestamp)` ast. Ba timestamp e floor-shode, replay kardan e spool ya
 *    ejra ye dobare ye `pricing:tick` tu hamun panjere OVERWRITE mikone,
 *    na duplicate. Hamin chizi-e ke spool ro emn mikone.
 * 2. HAMTARAZI: tu site e moghayese bayad betuni provider A ro ba B ru yek
 *    `x` pivot koni. Ba timestamp e millisecond e floor-nashode har `pivot`
 *    radif haye nim-khali misaze.
 *
 * Hazine: do tick tu yek panjere yeki mishan. Tu cadence e 15 saniye-i ke
 * candle e 1m az ~4 sample sakhte mishe, bi-ahamiyat ast.
 *
 * ⚠️ Halghe ye worker ham bayad grid-aligned bashe
 * (`sleep $((15 - $(date +%s) % 15))`), vagarna `sleep 15` e sade yavash
 * yavash drift mikone va tick ha ru grid nemioftan.
 */
final readonly class TickTime
{
    private function __construct(
        public CarbonImmutable $at,
        public int $gridSeconds,
    ) {}

    public static function now(int $gridSeconds = 15): self
    {
        return self::fromTimestamp(time(), $gridSeconds);
    }

    public static function fromTimestamp(int $timestamp, int $gridSeconds = 15): self
    {
        $floored = intdiv($timestamp, $gridSeconds) * $gridSeconds;

        return new self(CarbonImmutable::createFromTimestampUTC($floored), $gridSeconds);
    }

    public static function fromCarbon(CarbonImmutable $at, int $gridSeconds = 15): self
    {
        return self::fromTimestamp($at->getTimestamp(), $gridSeconds);
    }

    public function timestamp(): int
    {
        return $this->at->getTimestamp();
    }

    public function timestampMs(): int
    {
        return $this->at->getTimestamp() * 1000;
    }

    /** Chand saniye in tick az `$upstreamAt` aghab ast (hargez manfi nemishe). */
    public function lagSecondsFrom(?CarbonImmutable $upstreamAt): int
    {
        if ($upstreamAt === null) {
            return 0;
        }

        return max(0, $this->timestamp() - $upstreamAt->getTimestamp());
    }

    /**
     * Ekhtelaf e ELAMATDAR — baraye kashf e `ts` e ayande (clock skew ya
     * timestamp e kharab e upstream). Meghdar e manfi ya'ni upstream mige
     * gheymat baraye AYANDE ast.
     */
    public function signedLagSecondsFrom(?CarbonImmutable $upstreamAt): int
    {
        if ($upstreamAt === null) {
            return 0;
        }

        return $this->timestamp() - $upstreamAt->getTimestamp();
    }

    public function toString(): string
    {
        return $this->at->toIso8601String();
    }
}
