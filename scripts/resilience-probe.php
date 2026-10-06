<?php

declare(strict_types=1);

/*
 * Probe ye vaziat — baraye `scripts/resilience-check.sh`.
 *
 * Yek khat JSON chap mikone. HAR bakhsh try/catch e KHODESH ro dare: kol e
 * noghte ye in script in ast ke vaghti service ha GHAT'-and ejra beshe، pas
 * nabayad khodesh az yek ghat'i beterke.
 *
 * Masraf: php scripts/resilience-probe.php [fromUnix] [toUnix] [provider] [symbol]
 */

use App\Modules\Pricing\Application\Dto\CandleDto;
use App\Modules\Pricing\Application\Dto\CandleRequestDto;
use App\Modules\Pricing\Application\Queries\BoardQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Interval;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use App\Modules\Pricing\Domain\Ports\TimeSeriesPort;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxSpool;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$from = (int) ($argv[1] ?? (time() - 3_600));
$to = (int) ($argv[2] ?? time());
$providerKey = (string) ($argv[3] ?? 'tgju');
$symbol = (string) ($argv[4] ?? 'xau-oz-usd');

/** @param callable(): mixed $probe */
function attempt(callable $probe): mixed
{
    try {
        return $probe();
    } catch (Throwable $e) {
        return ['error' => mb_substr($e->getMessage(), 0, 160)];
    }
}

$out = [];

// ── Spool (ru connection e `prices`, database e 2 e Redis) ───────────────────────────────────────
$out['spool_size'] = attempt(static fn (): int => $app->make(InfluxSpool::class)->size());

// ── Tablo az masir e VAGHEI (Redis -> fallback e Postgres) ──────────
$out['board'] = attempt(static function () use ($app): array {
    $board = $app->make(BoardQuery::class)->board();

    return [
        'rows' => count($board->rows),
        'version' => $board->version,
        'spread_version' => $board->spreadVersion,
        'degraded' => $board->degraded,
    ];
});

// ── Postgres ────────────────────────────────────────────────────────
$out['store_rows'] = attempt(static fn (): int => count($app->make(SnapshotStorePort::class)->all()));

// ── Redis e gheymat ─────────────────────────────────────────────────
$out['redis_available'] = attempt(static fn (): bool => $app->make(HotCachePort::class)->isAvailable());

// ── Influx ──────────────────────────────────────────────────────────
$out['influx_available'] = attempt(static fn (): bool => $app->make(TimeSeriesPort::class)->isAvailable());

/*
 * Candle haye `m1` — asas e check e «hich gap».
 *
 * `Interval::M1` ZENDE az bucket e `ticks` khunde mishe (`aggregateWindow`)،
 * na az yek bucket e candle. Pas tedad e candle e m1 tu yek bazeh = tedad e
 * DAGHIGHE hayi ke tick daran. Agar yek tick gom beshe va spool replay
 * nashe، un daghighe INJA kam miad.
 */
$out['m1'] = attempt(static function () use ($app, $from, $to, $providerKey, $symbol): array {
    $instrument = Instrument::tryFrom($symbol);

    if ($instrument === null) {
        return ['error' => "symbol e nashenas: {$symbol}"];
    }

    $candles = $app->make(TimeSeriesPort::class)->candles(CandleRequestDto::make(
        providerKey: $providerKey,
        instrument: $instrument,
        interval: Interval::M1,
        from: $from,
        to: $to,
    ));

    return [
        'count' => count($candles),
        'minutes' => array_map(static fn (CandleDto $c): int => $c->timestamp, $candles),
    ];
});

echo json_encode($out, JSON_UNESCAPED_UNICODE), PHP_EOL;
