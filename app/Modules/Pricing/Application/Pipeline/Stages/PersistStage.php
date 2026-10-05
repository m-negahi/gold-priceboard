<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Pipeline\Stages;

use App\Modules\Pricing\Application\Dto\SinkResultDto;
use App\Modules\Pricing\Application\Pipeline\Stage;
use App\Modules\Pricing\Application\Pipeline\TickContext;
use App\Modules\Pricing\Domain\Enums\StagePolicy;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use App\Modules\Pricing\Domain\Ports\TimeSeriesPort;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxSpool;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxTimeSeries;
use Throwable;

/**
 * Neveshtan tu har se store — har kodum JODAGANE izole.
 *
 * ── Tartib: Redis -> Postgres -> Influx ─────────────────────────────
 * Redis avval chun chizi-e ke HAR bazdid-konande mikhune.
 * Postgres dovom chun backfill e payedar e Redis ast.
 * Influx akhar chun gom shodan e yek tick unja = yek gap e 15 saniye-i tu
 * yek candle e 90-rooze — kam-zarar tarin.
 *
 * Dalil e vaghei ye in tartib «durability» nist، **asymmetry e shekast**
 * ast: agar Postgres down bashe va tartib PG-aval bashe، Redis be-rooz
 * nemishe va tablo YAKH mizane. Ba Redis-aval، tablo kamelan zende mimune.
 *
 * ── 🔴 Hich kodum nabayad tick ro bokoshe ───────────────────────────
 * Har sink try/catch e khodesh ro dare va natije esh tu `TickReportDto`
 * sabt mishe. Influx ghat' bashe va tablo salem — in yek halat e SALEM e
 * system ast، na yek shekast.
 */
final readonly class PersistStage implements Stage
{
    public function __construct(
        private HotCachePort $cache,
        private SnapshotStorePort $store,
        private TimeSeriesPort $series,
        private ?InfluxSpool $spool = null,
    ) {}

    public function name(): string
    {
        return 'persist';
    }

    public function policy(): StagePolicy
    {
        return StagePolicy::Isolated;
    }

    public function handle(TickContext $context): void
    {
        if ($context->snapshots === [] && $context->holds === []) {
            return;
        }

        $this->toCache($context);
        $this->toStore($context);
        $this->toSeries($context);
    }

    private function toCache(TickContext $context): void
    {
        if ($context->snapshots === []) {
            // Faghat hold darim — gheymat e namayesh dade shode avaz nashode،
            // pas tablo dast nemikhore.
            return;
        }

        $startedAt = microtime(true);

        try {
            $this->cache->putBoard($context->snapshots);

            foreach ($context->health as $health) {
                $this->cache->putHealth($health);
            }

            $context->recordSink(SinkResultDto::ok('redis', count($context->snapshots), $this->ms($startedAt)));
        } catch (Throwable $e) {
            // Tablo be Postgres fallback mikone — site zende mimune.
            $context->recordSink(SinkResultDto::failed('redis', mb_substr($e->getMessage(), 0, 480)));
        }
    }

    private function toStore(TickContext $context): void
    {
        $startedAt = microtime(true);

        try {
            // Hold ha ham inja mirizan: tanha chizi ke avaz mishe
            // `pendingOutlier` ast، gheymat dast nemikhore.
            $this->store->save(array_merge($context->snapshots, $context->holds));

            if ($context->changed !== []) {
                $this->store->appendChanges($context->changed);
            }

            $context->recordSink(SinkResultDto::ok(
                'postgres',
                count($context->snapshots) + count($context->holds),
                $this->ms($startedAt),
            ));
        } catch (Throwable $e) {
            // Redis ghablan neveshte shode — tablo zende ast، faghat backfill
            // yek tick aghab ast.
            $context->recordSink(SinkResultDto::failed('postgres', mb_substr($e->getMessage(), 0, 480)));
        }
    }

    private function toSeries(TickContext $context): void
    {
        if ($context->snapshots === []) {
            // Hold tick e jadid NIST — neveshtanesh tu Influx ya'ni ja'l
            // kardan e data-i ke upstream nadade.
            return;
        }

        $startedAt = microtime(true);

        try {
            $this->series->writeTicks($context->snapshots, $context->at);

            $context->recordSink(SinkResultDto::ok('influx', count($context->snapshots), $this->ms($startedAt)));
        } catch (Throwable $e) {
            // Tu spool mirize ta `pricing:influx:drain` ba'dan replay kone.
            // Emn ast chun timestamp floor shode -> replay OVERWRITE mikone.
            $spooled = $this->spill($context);

            $context->recordSink(SinkResultDto::failed(
                'influx',
                mb_substr($e->getMessage(), 0, 480),
                $spooled,
            ));
        }
    }

    private function spill(TickContext $context): int
    {
        if ($this->spool === null || ! $this->series instanceof InfluxTimeSeries) {
            return 0;
        }

        try {
            $lines = $this->series->toLineProtocol($context->snapshots, $context->at);

            return $this->spool->push($lines) ? count($lines) : 0;
        } catch (Throwable) {
            // Spool khodesh mekanism e jobran ast — agar un ham kharab bashe
            // nabayad tick ro bokoshe.
            return 0;
        }
    }

    private function ms(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
