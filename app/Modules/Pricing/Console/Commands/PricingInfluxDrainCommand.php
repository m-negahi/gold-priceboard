<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Console\Commands;

use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxSpool;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxTimeSeries;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Replay e tick hayi ke vaghte ghat'i ye Influx tu spool rikhtan.
 *
 * Har daghighe tavasot e scheduler ejra mishe.
 *
 * ── Chera emn ast ───────────────────────────────────────────────────
 * Hoviyat e point tu Influx `(measurement, tagset, field, timestamp)` ast
 * va timestamp hamishe ru grid e 15 saniye-i floor shode. Pas replay
 * OVERWRITE mikone، na duplicate. In dar
 * `InfluxTimeSeriesTest::test_rewriting_the_same_tick_overwrites…` ta'eed
 * shode — bedun e un ta'eed، in command ghabl e e'temad nabud.
 *
 * ── Agar neveshtan shekast bokhore ──────────────────────────────────
 * Khat ha BAR MIGARDAN tu spool. `pull()` un ha ro bardashte، pas bedun e
 * bargardandan gom mishan.
 */
final class PricingInfluxDrainCommand extends Command
{
    protected $signature = 'pricing:influx:drain
        {--batch=2000 : chand khat tu har batch}
        {--max-batches=10 : saghf e batch dar har ejra}';

    protected $description = 'Replay e tick haye spool-shode be Influx.';

    public function handle(): int
    {
        $config = (array) config('pricing.influx', []);

        $spool = new InfluxSpool((int) ($config['spool_max_lines'] ?? 40_000));
        $series = new InfluxTimeSeries($config);

        $pending = $spool->size();

        if ($pending === 0) {
            $this->line('Spool khali-e.');

            return self::SUCCESS;
        }

        if (! $series->isAvailable()) {
            $this->warn("Influx hanuz dar dastres nist — {$pending} khat tu spool munde.");

            return self::SUCCESS;
        }

        $batchSize = max(1, (int) $this->option('batch'));
        $maxBatches = max(1, (int) $this->option('max-batches'));

        $written = 0;
        $failed = 0;
        $oldest = null;
        $newest = null;

        for ($i = 0; $i < $maxBatches; $i++) {
            $lines = $spool->pull($batchSize);

            if ($lines === []) {
                break;
            }

            try {
                $series->writeLines($lines);
                $written += count($lines);

                [$batchOldest, $batchNewest] = $this->timestampRange($lines);
                $oldest = $oldest === null ? $batchOldest : min($oldest, $batchOldest);
                $newest = $newest === null ? $batchNewest : max($newest, $batchNewest);
            } catch (Throwable $e) {
                // 🔴 Bargardundan — vagarna in tick ha baraye hamishe gom mishan.
                $spool->push($lines);
                $failed = count($lines);

                Log::channel($this->channel())->warning('pricing.drain_failed', [
                    'lines' => $failed,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Batch shekast khord ({$failed} khat bargasht tu spool): {$e->getMessage()}");

                break;
            }
        }

        // 🔴 Tick e kham bargasht — vali candle NA.
        // Task e `candles_1m` lookback e `-3m` dare; in tick ha ghadimi tar
        // az un-an، pas task hargez nemibinateshun va `m5`–`d1` yek surakh e
        // daemi peyda mikonan. Hamun bazeh dasti dobare aggregate mishe.
        if ($written > 0 && $oldest !== null && $newest !== null) {
            try {
                $series->rebuildMinuteCandles($oldest - 60, $newest + 120, (string) ($config['org'] ?? 'priceboard'));
                $this->line('Candle haye 1m baraye bazeh ye drain-shode dobare sakhte shodan.');
            } catch (Throwable $e) {
                // Tick ha salem neshastan — faghat candle aghab ast.
                Log::channel($this->channel())->warning('pricing.rebuild_failed', [
                    'from' => $oldest,
                    'to' => $newest,
                    'error' => $e->getMessage(),
                ]);

                $this->warn("Rebuild e candle shekast khord: {$e->getMessage()}");
            }
        }

        $remaining = $spool->size();

        if ($written > 0) {
            Log::channel($this->channel())->info('pricing.drained', [
                'written' => $written,
                'remaining' => $remaining,
            ]);
        }

        $this->info("Drain: {$written} khat neveshte shod، {$remaining} khat munde.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Ghadimi tarin va jadid tarin timestamp e yek batch e line protocol.
     *
     * Shekl: `price,<tags> <fields> <timestamp>` — timestamp akharin token ast.
     *
     * @param  array<int, string>  $lines
     * @return array{0: int, 1: int}
     */
    private function timestampRange(array $lines): array
    {
        $timestamps = [];

        foreach ($lines as $line) {
            $position = strrpos($line, ' ');

            if ($position === false) {
                continue;
            }

            $timestamp = (int) substr($line, $position + 1);

            if ($timestamp > 0) {
                $timestamps[] = $timestamp;
            }
        }

        return $timestamps === []
            ? [time(), time()]
            : [min($timestamps), max($timestamps)];
    }

    private function channel(): string
    {
        return (string) config('pricing.log.channel', 'stack');
    }
}
