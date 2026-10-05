<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Providers;

use App\Modules\Pricing\Application\Actions\ProviderSpreadAction;
use App\Modules\Pricing\Application\Pipeline\Stages\EnrichStage;
use App\Modules\Pricing\Application\Pipeline\Stages\FetchStage;
use App\Modules\Pricing\Application\Pipeline\Stages\PersistStage;
use App\Modules\Pricing\Application\Pipeline\Stages\PublishStage;
use App\Modules\Pricing\Application\Pipeline\Stages\ValidateStage;
use App\Modules\Pricing\Application\Pipeline\TickPipeline;
use App\Modules\Pricing\Console\Commands\PricingInfluxDrainCommand;
use App\Modules\Pricing\Console\Commands\PricingInfluxHealthCommand;
use App\Modules\Pricing\Console\Commands\PricingInfluxProvisionCommand;
use App\Modules\Pricing\Console\Commands\PricingTickCommand;
use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use App\Modules\Pricing\Domain\Ports\TimeSeriesPort;
use App\Modules\Pricing\Infrastructure\Cache\RedisHotCache;
use App\Modules\Pricing\Infrastructure\Persistence\EloquentSnapshotStore;
use App\Modules\Pricing\Infrastructure\Time\SystemClock;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxSpool;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxTimeSeries;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

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

        $this->app->singleton(ClockPort::class, static fn (): ClockPort => new SystemClock);

        $this->app->singleton(InfluxSpool::class, static fn (): InfluxSpool => new InfluxSpool(
            (int) config('pricing.influx.spool_max_lines', 40_000),
        ));

        // `ProviderSpreadAction` sarih bind mishe faghat baraye yek chiz:
        // logger esh bayad channel e `pricing` bashe. Autowire `LoggerInterface`
        // ro be channel e default resolve mikone، va un vaght yek
        // `spread_version_bump_failed` — ke ya'ni cache e nemudar bayat mune —
        // tu log e omumi gom mishe jaye inke tu log e pricing bashe.
        $this->app->singleton(ProviderSpreadAction::class, static fn ($app): ProviderSpreadAction => new ProviderSpreadAction(
            cache: $app->make(HotCachePort::class),
            clock: $app->make(ClockPort::class),
            logger: Log::channel((string) config('pricing.log.channel', 'stack')),
        ));

        // Tartib e Stage ha MOHEM ast:
        //   Fetch -> Validate -> Enrich -> Persist -> Publish
        // `Validate` ghabl az `Enrich` miad: enrich kardan e chizi ke gharar
        // ast rad beshe faghat kar e ezafi nist — `dayHigh` ro ba yek meghdar
        // e kharab ALUDEH mikone.
        $this->app->singleton(TickPipeline::class, static fn ($app): TickPipeline => new TickPipeline(
            store: $app->make(SnapshotStorePort::class),
            stages: [
                new FetchStage,
                new ValidateStage((array) config('pricing.max_deviation', [])),
                new EnrichStage,
                new PersistStage(
                    cache: $app->make(HotCachePort::class),
                    store: $app->make(SnapshotStorePort::class),
                    series: $app->make(TimeSeriesPort::class),
                    spool: $app->make(InfluxSpool::class),
                ),
                new PublishStage($app->make(HotCachePort::class)),
            ],
            logger: Log::channel((string) config('pricing.log.channel', 'stack')),
        ));
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
