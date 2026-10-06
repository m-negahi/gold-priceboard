<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Providers;

use App\Modules\Pricing\Application\Actions\IngestTickAction;
use App\Modules\Pricing\Application\Actions\ProviderSpreadAction;
use App\Modules\Pricing\Application\Actions\RecordProviderHealthAction;
use App\Modules\Pricing\Application\Pipeline\Stages\EnrichStage;
use App\Modules\Pricing\Application\Pipeline\Stages\FetchStage;
use App\Modules\Pricing\Application\Pipeline\Stages\ManualBackupStage;
use App\Modules\Pricing\Application\Pipeline\Stages\PersistStage;
use App\Modules\Pricing\Application\Pipeline\Stages\PublishStage;
use App\Modules\Pricing\Application\Pipeline\Stages\ValidateStage;
use App\Modules\Pricing\Application\Pipeline\TickPipeline;
use App\Modules\Pricing\Application\Queries\BoardQuery;
use App\Modules\Pricing\Application\Queries\CandleQuery;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Application\Queries\SpreadQuery;
use App\Modules\Pricing\Console\Commands\PricingInfluxDrainCommand;
use App\Modules\Pricing\Console\Commands\PricingInfluxHealthCommand;
use App\Modules\Pricing\Console\Commands\PricingInfluxProvisionCommand;
use App\Modules\Pricing\Console\Commands\PricingTickCommand;
use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Domain\Ports\ManualBackupPort;
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use App\Modules\Pricing\Domain\Ports\TimeSeriesPort;
use App\Modules\Pricing\Domain\Ports\UpstreamLogPort;
use App\Modules\Pricing\Infrastructure\Cache\RedisHotCache;
use App\Modules\Pricing\Infrastructure\Monitoring\RedisUpstreamCallLog;
use App\Modules\Pricing\Infrastructure\Persistence\EloquentManualBackup;
use App\Modules\Pricing\Infrastructure\Persistence\EloquentSnapshotStore;
use App\Modules\Pricing\Infrastructure\Sources\SourceManager;
use App\Modules\Pricing\Infrastructure\Time\SystemClock;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxSpool;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxTimeSeries;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * ⚠️ Ghanun e bind: faghat chizi `singleton` mishe ke `final readonly` ast va
 * FAGHAT config e immutable negah midare — va tu har method client e taze
 * misaze.
 *
 * `InfluxDB2\Client` / `WriteApi` / `QueryApi` **hargez** bind nemishan:
 * mutable-and va handle e curl negah midaran، ke zire Swoole beyn e
 * coroutine ha nasht mikone. (Ma aslan SDK estefade nemikonim — `Http`.)
 *
 * Har bind inja yek test e motanazer tu `PricingBindingTest` dare: tu projeh
 * ye marja' yek `scoped()` ru class e NAMOJUD bind shode bud va memoization
 * hichvaght kar nakard — kasi ta mah ha nafahmid.
 */
final class PricingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/pricing.php', 'pricing');

        $this->app->singleton(SnapshotStorePort::class, static fn (): SnapshotStorePort => new EloquentSnapshotStore);

        $this->app->singleton(HotCachePort::class, static fn (): HotCachePort => new RedisHotCache);

        $this->app->singleton(
            TimeSeriesPort::class,
            static fn (): TimeSeriesPort => new InfluxTimeSeries((array) config('pricing.influx', [])),
        );

        $this->app->singleton(ManualBackupPort::class, EloquentManualBackup::class);

        $this->app->singleton(ClockPort::class, static fn (): ClockPort => new SystemClock);

        $this->app->singleton(UpstreamLogPort::class, static fn (): UpstreamLogPort => new RedisUpstreamCallLog);

        $this->app->singleton(InfluxSpool::class, static fn (): InfluxSpool => new InfluxSpool(
            (int) config('pricing.influx.spool_max_lines', 40_000),
        ));

        // ── Chera in se ta SARIH bind mishan ────────────────────
        // Har se yek param e `?LoggerInterface` daran. Autowire un ro be
        // channel e DEFAULT resolve mikone (container `LoggerInterface` ro
        // be `LogManager` bind karde)، na be channel e `pricing`.
        //
        // Natije ye un، yek shekast e saket e monitoring bud: hoshdar hayi
        // mesl e `providers_unavailable`، `lock_unavailable`،
        // `health_write_failed` va `spread_version_bump_failed` tu log e
        // OMUMI mioftadan، dar hali ke kol e dalil e vojud e channel e
        // `pricing` in ast ke hamun hoshdar ha yek ja ye JSON e ghabl e
        // query bashan.
        $this->app->singleton(ProviderSpreadAction::class, static fn ($app): ProviderSpreadAction => new ProviderSpreadAction(
            cache: $app->make(HotCachePort::class),
            clock: $app->make(ClockPort::class),
            logger: self::logger(),
        ));

        $this->app->singleton(IngestTickAction::class, static fn ($app): IngestTickAction => new IngestTickAction(
            pipeline: $app->make(TickPipeline::class),
            providers: $app->make(ProviderQuery::class),
            sources: $app->make(SourceManager::class),
            cache: $app->make(HotCachePort::class),
            clock: $app->make(ClockPort::class),
            health: $app->make(RecordProviderHealthAction::class),
            logger: self::logger(),
        ));

        // `BoardQuery` sarih bind mishe ta `freeze_after_seconds` az config
        // biad — un adad mohafez e yakh-zadegi ye SAKET e tablo-st.
        $this->app->singleton(BoardQuery::class, static fn ($app): BoardQuery => new BoardQuery(
            cache: $app->make(HotCachePort::class),
            store: $app->make(SnapshotStorePort::class),
            spreads: $app->make(SpreadQuery::class),
            clock: $app->make(ClockPort::class),
            freezeAfterSeconds: (int) config('pricing.board.freeze_after_seconds', 120),
        ));

        // `CandleQuery` store esh SARIH ast — na store e default e app.
        //
        // 🔴 `CACHE_STORE=database` chart ro mindaze ru POSTGRES: hamun DB-i
        // ke kol e cache baraye mohafezat azash hast. Yek series e `h1` e
        // yek-sale 8,760 candle ast؛ zakhire kardan esh unja aks e hadaf ast.
        $this->app->singleton(CandleQuery::class, static fn ($app): CandleQuery => new CandleQuery(
            series: $app->make(TimeSeriesPort::class),
            hot: $app->make(HotCachePort::class),
            spreads: $app->make(SpreadQuery::class),
            cache: $app->make(CacheFactory::class)->store(config('pricing.cache_store')),
        ));

        // Tartib e Stage ha MOHEM ast:
        //   Fetch -> Validate -> Enrich -> Persist -> Publish
        // `Validate` ghabl az `Enrich` miad: enrich kardan e chizi ke gharar
        // ast rad beshe faghat kar e ezafi nist — `dayHigh` ro ba yek meghdar
        // e kharab ALUDEH mikone.
        $this->app->singleton(TickPipeline::class, static fn ($app): TickPipeline => new TickPipeline(
            store: $app->make(SnapshotStorePort::class),
            stages: [
                // Retry faghat baraye shekast haye GOZARA ye ettesal، va
                // timeout esh be budget e baghi-munde clamp mishe (dalil e
                // kamel tu `FetchStage` va `TransientFailure`).
                new FetchStage(
                    maxAttempts: (int) config('pricing.fetch.max_attempts', 2),
                    retryDelayMs: (int) config('pricing.fetch.retry_delay_ms', 300),
                    budgetMs: (int) config('pricing.tick_seconds', 15) * 1_000,
                    // P4-14 — ja mundan e in param hich khata-i NEMIDE
                    // (nullable ast)؛ `PricingBindingTest` negahban esh e.
                    upstreamLog: $app->make(UpstreamLogPort::class),
                ),
                new ValidateStage(
                    maxDeviation: (array) config('pricing.max_deviation', []),
                    maxCrossProviderDeviation: (float) config('pricing.max_cross_provider_deviation', 20),
                ),
                new EnrichStage,
                new PersistStage(
                    cache: $app->make(HotCachePort::class),
                    store: $app->make(SnapshotStorePort::class),
                    series: $app->make(TimeSeriesPort::class),
                    spool: $app->make(InfluxSpool::class),
                ),
                new ManualBackupStage($app->make(ManualBackupPort::class)),
                new PublishStage($app->make(HotCachePort::class)),
            ],
            logger: self::logger(),
        ));
    }

    /**
     * Logger e module — ba ehteram be `pricing.log.enabled`.
     *
     * ⚠️ Un kelid tu config bud vali HICHVAGHT khunde nemishod:
     * `PRICING_LOG=false` hich kari nemikard. Yek kelid e config ke kar
     * nemikone yek dorugh ast — ya bayad e'mal she ya pak she.
     */
    private static function logger(): LoggerInterface
    {
        if (! (bool) config('pricing.log.enabled', true)) {
            return new NullLogger;
        }

        return Log::channel((string) config('pricing.log.channel', 'stack'));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PricingTickCommand::class,
                PricingInfluxProvisionCommand::class,
                PricingInfluxDrainCommand::class,
                PricingInfluxHealthCommand::class,
            ]);
        }
    }
}
