<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\TimeSeries;

use App\Modules\Pricing\Application\Dto\CandleRequestDto;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Sakht e query haye Flux.
 *
 * ── 🔴 Injection ─────────────────────────────────────────────────────
 * Support e query-parameter e client e Influx za'if ast، pas **allowlist
 * defa' e ASLI ast، na yek laye ye ezafe**:
 *   - `symbol` / `interval` / `side` az enum miyan (`tryFrom`)
 *   - `provider` ba lista ye kelid e DB check mishe
 *   - `from`/`to` cast e `int` mishan
 *   - va ru hame، `tag()` har chiz e gheyr e `[a-z0-9_-]` ro RAD mikone
 *
 * ── Chahar tale-i ke chart ro saket kharab mikonan ───────────────────
 * 1. `timeSrc: "_start"` — default `"_stop"` ast va kol e chart ro yek
 *    candle JOLO mindaze.
 * 2. `drop(columns: ["_field"])` ghabl az `set(key: "_field", …)` —
 *    `_field` tu group key ast; `set` e tanha group key e bayat ja mizare
 *    va `union`/`pivot` **saket** soton mindaze.
 * 3. `sort(desc) -> limit -> sort(asc)` — `limit` e ba'd az sort e soudi
 *    GHADIMI tarin N ro mide، na jadid tarin (bug e chart e khali).
 * 4. `timezone.fixed(3h30m)` ru bazeh haye ruzane — bedun esh `window` be
 *    nime-shab e UTC = 03:30 Tehran align mishe va har candle e ruzane
 *    DO rooz e kari ye Irani ro sovar mishe.
 */
final readonly class Flux
{
    /**
     * Tanha shekl e mojaz baraye meghdar e yek tag.
     *
     * Hame ye symbol ha، side ha va kelid haye provider ba hamin ghaleb
     * sakhte mishan، pas in hich chiz e motabari ro rad nemikone.
     */
    private const TAG_PATTERN = '/^[a-z0-9_\-]+$/';

    public function __construct(
        private string $measurementTicks = 'price',
        private string $measurementCandles = 'ohlc',
        private string $timezoneOffset = '3h30m',
    ) {}

    /**
     * Darvaze ye nahayi ye zed e injection.
     *
     * @throws InvalidArgumentException agar meghdar shekl e entezari nadashte bashe.
     */
    public static function tag(string $value): string
    {
        if (preg_match(self::TAG_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException("Flux: meghdar e tag e na-motabar: «{$value}».");
        }

        return $value;
    }

    /** Query e candle — `m1` zende az `ticks`، baghi az bucket e candle. */
    public function candles(CandleRequestDto $request): string
    {
        return $request->interval->readsRawTicks()
            ? $this->candlesFromTicks($request)
            : $this->candlesFromBucket($request);
    }

    /**
     * `m5`–`d1`: bucket e candle ghablan tavasot e task por shode.
     *
     * Faghat `pivot` + `sort`/`limit` lazem ast — hich aggregate-i.
     */
    private function candlesFromBucket(CandleRequestDto $request): string
    {
        $bucket = self::tag($request->interval->bucket());
        $filters = $this->tagFilters($request);

        return <<<FLUX
        from(bucket: "{$bucket}")
          |> range(start: {$this->time($request->from)}, stop: {$this->time($request->to)})
          |> filter(fn: (r) => r._measurement == "{$this->measurementCandles}")
          {$filters}
          |> filter(fn: (r) => r._field == "open" or r._field == "high" or r._field == "low" or r._field == "close" or r._field == "count")
          |> pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value")
          |> keep(columns: ["_time", "open", "high", "low", "close", "count"])
          |> sort(columns: ["_time"], desc: true)
          |> limit(n: {$request->limit})
          |> sort(columns: ["_time"])
        FLUX;
    }

    /**
     * `m1`: mostaghim az `ticks` aggregate mishe.
     *
     * Amdan az bucket e candle nemikhune — injuri hamishe be-rooz ast va be
     * latency e task e Influx vabaste nist.
     */
    private function candlesFromTicks(CandleRequestDto $request): string
    {
        $window = $request->interval->fluxWindow();
        $filters = $this->tagFilters($request);

        // ⚠️ `drop` ghabl az `set` — `_field` tu group key ast.
        $aggregate = static fn (string $fn, string $field): string => <<<FLUX
        {$field} = src
          |> aggregateWindow(every: {$window}, fn: {$fn}, timeSrc: "_start", createEmpty: false)
          |> drop(columns: ["_start", "_stop", "_field"])
          |> set(key: "_field", value: "{$field}")
        FLUX;

        $open = $aggregate('first', 'open');
        $high = $aggregate('max', 'high');
        $low = $aggregate('min', 'low');
        $close = $aggregate('last', 'close');

        return <<<FLUX
        import "timezone"
        option location = timezone.fixed(offset: {$this->timezoneOffset})

        src = from(bucket: "ticks")
          |> range(start: {$this->time($request->from)}, stop: {$this->time($request->to)})
          |> filter(fn: (r) => r._measurement == "{$this->measurementTicks}" and r._field == "value")
          {$filters}

        {$open}

        {$high}

        {$low}

        {$close}

        count = src
          |> aggregateWindow(every: {$window}, fn: count, timeSrc: "_start", createEmpty: false)
          |> drop(columns: ["_start", "_stop", "_field"])
          |> set(key: "_field", value: "count")
          |> toFloat()

        union(tables: [open, high, low, close, count])
          |> pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value")
          |> keep(columns: ["_time", "open", "high", "low", "close", "count"])
          |> sort(columns: ["_time"], desc: true)
          |> limit(n: {$request->limit})
          |> sort(columns: ["_time"])
        FLUX;
    }

    /** Query e salamati: akharin bar e yek bucket chand vaght pish bude. */
    public function latestCandleTime(string $bucket): string
    {
        $bucket = self::tag($bucket);

        return <<<FLUX
        from(bucket: "{$bucket}")
          |> range(start: -7d)
          |> filter(fn: (r) => r._measurement == "{$this->measurementCandles}" and r._field == "close")
          |> keep(columns: ["_time"])
          |> sort(columns: ["_time"], desc: true)
          |> limit(n: 1)
        FLUX;
    }

    private function tagFilters(CandleRequestDto $request): string
    {
        $provider = self::tag($request->providerKey);
        $symbol = self::tag($request->instrument->value);
        $side = self::tag($request->side->value);

        return implode("\n  ", [
            '|> filter(fn: (r) => r.provider == "'.$provider.'")',
            '|> filter(fn: (r) => r.symbol == "'.$symbol.'")',
            '|> filter(fn: (r) => r.side == "'.$side.'")',
        ]);
    }

    /** Timestamp e Unix be RFC3339 — tanha shekl-i ke Flux ghabul mikone. */
    private function time(int $timestamp): string
    {
        return CarbonImmutable::createFromTimestampUTC($timestamp)->format('Y-m-d\TH:i:s\Z');
    }
}
