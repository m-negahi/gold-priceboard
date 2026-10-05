<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Providers;

use App\Modules\Pricing\Console\Commands\PricingInfluxDrainCommand;
use App\Modules\Pricing\Console\Commands\PricingInfluxHealthCommand;
use App\Modules\Pricing\Console\Commands\PricingInfluxProvisionCommand;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use App\Modules\Pricing\Domain\Ports\TimeSeriesPort;
use App\Modules\Pricing\Infrastructure\Cache\RedisHotCache;
use App\Modules\Pricing\Infrastructure\Persistence\EloquentSnapshotStore;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxTimeSeries;
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
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PricingInfluxProvisionCommand::class,
                PricingInfluxDrainCommand::class,
                PricingInfluxHealthCommand::class,
            ]);
        }
    }
}
