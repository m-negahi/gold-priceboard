<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\TimeSeries;

use App\Modules\Pricing\Application\Dto\CandleRequestDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\Ports\TimeSeriesPort;
use App\Modules\Pricing\Domain\ValueObjects\TickTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Adapter e InfluxDB 2.7.
 *
 * ── 🔴 Octane safety ─────────────────────────────────────────────────
 * In class `final readonly` ast va FAGHAT config negah midare. Hich
 * `InfluxDB2\Client`، `WriteApi` ya `QueryApi` be onvan e property zakhire
 * **nemishe** — un ha mutable-and va handle e curl negah midaran:
 *   - `WriteApi` e BATCHING yek buffer e dakheli dare ke beyn e request ha
 *     mimune، mitune tavasot e yek request e bi-rabt flush beshe، memory ro
 *     leak mikone، va ba recycle e worker SAKET khali mishe.
 *   - `CurlMultiHandler` e Guzzle handle e persistent dare ke share kardanesh
 *     beyn e coroutine haye Swoole response ha ro ghati mikone.
 *
 * Pas:
 *   WRITE -> faghat tu worker e CLI، `SYNCHRONOUS`، client dakhel e method
 *            sakhte va tu `finally` baste mishe.
 *   READ  -> **aslan SDK estefade nemishe**. `Http::withBody()` + `FluxCsv`.
 *            Yek kelas e kamel e khatar az request path hazf mishe va yek
 *            HTTP stack baraye kol e app mimune.
 *
 * ── Do token ────────────────────────────────────────────────────────
 * `write` faghat ru `ticks` ejaze dare va `read` faghat khundan. Agar token e
 * web lo bere، kasi natune tarikhche ye gheymat ro ja'l kone.
 */
final readonly class InfluxTimeSeries implements TimeSeriesPort
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private array $config,
        private Flux $flux = new Flux,
    ) {}

    /**
     * Neveshtan e yek batch e tick.
     *
     * HAME ye point ha hamun yek timestamp e floor-shode ro migiran — hamin
     * chizi-e ke replay e spool ro be «overwrite» tabdil mikone، na duplicate.
     */
    public function writeTicks(array $snapshots, TickTime $at): void
    {
        if ($snapshots === []) {
            return;
        }

        $lines = $this->toLineProtocol($snapshots, $at);

        $this->writeLines($lines);
    }

    /**
     * Neveshtan e line-protocol e kham.
     *
     * Jodā-st ta `pricing:influx:drain` betune daghighan hamun khat hayi ke
     * tu spool-and ro replay kone، bedun e inke dobare az `SnapshotDto`
     * besazatesh.
     *
     * @param  array<int, string>  $lines
     *
     * @throws RuntimeException
     */
    public function writeLines(array $lines): void
    {
        if ($lines === []) {
            return;
        }

        $url = $this->url().'/api/v2/write?'.http_build_query([
            'org' => $this->org(),
            'bucket' => (string) ($this->config['buckets']['ticks'] ?? 'ticks'),
            // Grid e ma 15 saniye-i ast — daghighat e bishtar ma'ni nadare.
            'precision' => 's',
        ]);

        try {
            $response = Http::withToken($this->writeToken(), 'Token')
                ->withHeaders(['Content-Type' => 'text/plain; charset=utf-8'])
                ->timeout((int) ($this->config['write_timeout'] ?? 2))
                ->withBody(implode("\n", $lines), 'text/plain')
                ->post($url);
        } catch (Throwable $e) {
            throw new RuntimeException("influx: write shekast khord — {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                "influx: write e na-movafagh ({$response->status()}): ".$response->body(),
            );
        }
    }

    public function candles(CandleRequestDto $request): array
    {
        $csv = $this->query($this->flux->candles($request));

        return FluxCsv::toCandles($csv);
    }

    /** Akharin bar e yek bucket — baraye `pricing:influx:health`. */
    public function latestCandleAt(string $bucket): ?CarbonImmutable
    {
        return FluxCsv::firstTime($this->query($this->flux->latestCandleTime($bucket)));
    }

    public function isAvailable(): bool
    {
        try {
            return Http::timeout(2)->get($this->url().'/health')->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Ejra ye yek query e Flux va bargardandan e CSV e kham.
     *
     * @throws RuntimeException
     */
    private function query(string $flux): string
    {
        $url = $this->url().'/api/v2/query?org='.urlencode($this->org());

        try {
            $response = Http::withToken($this->readToken(), 'Token')
                ->withHeaders([
                    'Content-Type' => 'application/vnd.flux',
                    'Accept' => 'application/csv',
                ])
                ->timeout((int) ($this->config['query_timeout'] ?? 5))
                ->withBody($flux, 'application/vnd.flux')
                ->post($url);
        } catch (Throwable $e) {
            throw new RuntimeException("influx: query shekast khord — {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                "influx: query e na-movafagh ({$response->status()}): ".$response->body(),
            );
        }

        return $response->body();
    }

    /**
     * Tabdil e snapshot be line protocol.
     *
     * Tag: `provider`, `symbol`, `side`, `quote` — `quote` vabaste be
     * `symbol` ast pas cardinality ye EZAFE sefr mide، vali filter be tafkik
     * e arz ro bedun e lookup mumken mikone.
     *
     * Field: `value` (MAJOR unit e float) va `lag`.
     * ⚠️ `value` be MAJOR neveshte mishe (na minor) chun chart ba float kar
     * mikone va rial ta ~1e10 tu float64 daghigh ast (hadd e 2^53 ≈ 9e15).
     *
     * @param  array<int, SnapshotDto>  $snapshots
     * @return array<int, string>
     */
    public function toLineProtocol(array $snapshots, TickTime $at): array
    {
        $timestamp = $at->timestamp();
        $lines = [];

        foreach ($snapshots as $snapshot) {
            $tags = implode(',', [
                'provider='.Flux::tag($snapshot->providerKey),
                'symbol='.Flux::tag($snapshot->instrument->value),
                'side='.Flux::tag($snapshot->side->value),
                'quote='.Flux::tag(strtolower($snapshot->price->quote->value)),
            ]);

            $fields = implode(',', [
                'value='.$this->float($snapshot->price->toFloat()),
                'lag='.$this->float((float) $snapshot->upstreamLagSeconds),
            ]);

            $lines[] = "price,{$tags} {$fields} {$timestamp}";
        }

        return $lines;
    }

    /**
     * Float be shekl e line protocol.
     *
     * ⚠️ Dalil e `sprintf` **notation e elmi** ast، na noghte ye ashar:
     * `(string) 1.0e25` tu PHP mishe `"1.0E+25"` va `(string) 0.00001`
     * mishe `"1.0E-5"` — Influx hich kodum ro ghabul nemikone.
     *
     * Khoruji e bedun e noghte (masalan `266400000`) moshkeli nadare: tu
     * line protocol، adad e BEDUN e suffix float ast. Integer bayad `i`
     * dashte bashe (`266400000i`) — ke ma hargez nemizanim.
     */
    private function float(float $value): string
    {
        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.') ?: '0';
    }

    private function url(): string
    {
        return rtrim((string) ($this->config['url'] ?? 'http://influxdb:8086'), '/');
    }

    private function org(): string
    {
        return (string) ($this->config['org'] ?? 'priceboard');
    }

    private function writeToken(): string
    {
        $token = (string) ($this->config['write_token'] ?? '');

        if ($token === '') {
            throw new RuntimeException('influx: `PRICING_INFLUX_WRITE_TOKEN` tanzim nashode.');
        }

        return $token;
    }

    private function readToken(): string
    {
        $token = (string) ($this->config['read_token'] ?? '');

        if ($token === '') {
            throw new RuntimeException('influx: `PRICING_INFLUX_READ_TOKEN` tanzim nashode.');
        }

        return $token;
    }
}
