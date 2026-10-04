<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\TimeSeries;

use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Spool e line-protocol vaghti Influx dar dastres nist.
 *
 * ── Chera in emn ast ────────────────────────────────────────────────
 * Hoviyat e yek point tu Influx `(measurement, tagset, field, timestamp)`
 * ast، va `TickTime` hamishe ru grid e 15 saniye-i floor mishe. Pas replay
 * kardan e yek batch **overwrite** mikone، na duplicate. Bedun e un floor،
 * har drain yek radif e takrari misakht.
 *
 * `LTRIM` saghf mizare: ~40k khat ≈ 2 saat tick. Ba'd az un ghadimi tarin
 * ha mioftan — amdi: yek ghat'i ye tulani tar az un، behtar ast ba backfill
 * az `price_changes` jobran beshe ta inke Redis por beshe.
 */
final readonly class InfluxSpool
{
    private const CONNECTION = 'prices';

    private const KEY = 'pb:influx:spool';

    public function __construct(
        private int $maxLines = 40_000,
    ) {}

    /**
     * Rikhtan e yek batch tu spool.
     *
     * Hargez throw nemikone: spool khodesh mekanism e jobran e kharabi ast،
     * pas agar un ham kharab bashe nabayad tick ro bokoshe.
     *
     * @param  array<int, string>  $lines
     */
    public function push(array $lines): bool
    {
        if ($lines === []) {
            return true;
        }

        try {
            Redis::connection(self::CONNECTION)->pipeline(function ($pipe) use ($lines): void {
                $pipe->rpush(self::KEY, ...$lines);
                $pipe->ltrim(self::KEY, -$this->maxLines, -1);
            });

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Bardashtan e ta `$limit` khat baraye replay.
     *
     * ⚠️ `LPOP` ba count — khat ha bardashte mishan. Agar neveshtan e dobare
     * shekast bokhore، caller bayad `push()` eshun kone.
     *
     * @return array<int, string>
     */
    public function pull(int $limit = 5_000): array
    {
        try {
            $lines = Redis::connection(self::CONNECTION)->lpop(self::KEY, $limit);
        } catch (Throwable) {
            return [];
        }

        if (! is_array($lines)) {
            return $lines === null || $lines === false ? [] : [(string) $lines];
        }

        return array_map('strval', $lines);
    }

    public function size(): int
    {
        try {
            return (int) Redis::connection(self::CONNECTION)->llen(self::KEY);
        } catch (Throwable) {
            return 0;
        }
    }

    public function clear(): void
    {
        try {
            Redis::connection(self::CONNECTION)->del(self::KEY);
        } catch (Throwable) {
            // Spool e pak-nashode zarar e amali nadare.
        }
    }
}
