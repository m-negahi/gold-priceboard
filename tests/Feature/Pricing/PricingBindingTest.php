<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Actions\IngestTickAction;
use App\Modules\Pricing\Application\Actions\ProviderAction;
use App\Modules\Pricing\Application\Actions\ProviderSpreadAction;
use App\Modules\Pricing\Application\Actions\RecordProviderHealthAction;
use App\Modules\Pricing\Application\Pipeline\Stages\EnrichStage;
use App\Modules\Pricing\Application\Pipeline\Stages\FetchStage;
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
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use App\Modules\Pricing\Domain\Ports\TimeSeriesPort;
use App\Modules\Pricing\Infrastructure\Cache\RedisHotCache;
use App\Modules\Pricing\Infrastructure\Persistence\EloquentSnapshotStore;
use App\Modules\Pricing\Infrastructure\Time\SystemClock;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxSpool;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxTimeSeries;
use App\Modules\Pricing\Services\SourceManager;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\NullStore;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use ReflectionClass;
use Tests\TestCase;

/**
 * 🔴 Har bind e `PricingServiceProvider` yek test e assert dare.
 *
 * ── Chera in file vojud dare ─────────────────────────────────────────
 * Tu projeh ye marja' (`hg_website`) `PricingServiceProvider` yek
 * `scoped()` ru class-i bind karde bud ke `use` esh nashode bud — pas esm e
 * class be namespace e KHOD e provider resolve mishod va un class vojud
 * nadasht. Container hichvaght un bind ro seda nazad، memoization
 * HICHVAGHT kar nakard، va **hich khata-i ham nadad**. Mah ha kasi
 * nafahmid.
 *
 * Dars: yek bind-i ke resolve nashe shekast e SAKET ast. Pas har bind inja
 * resolve mishe va natije esh assert mishe.
 */
final class PricingBindingTest extends TestCase
{
    /**
     * Har port -> class e concrete esh.
     *
     * @return array<string, array{0: class-string, 1: class-string}>
     */
    public static function portBindings(): array
    {
        return [
            'snapshot store' => [SnapshotStorePort::class, EloquentSnapshotStore::class],
            'hot cache' => [HotCachePort::class, RedisHotCache::class],
            'time series' => [TimeSeriesPort::class, InfluxTimeSeries::class],
            'clock' => [ClockPort::class, SystemClock::class],
        ];
    }

    /**
     * @param  class-string  $port
     * @param  class-string  $concrete
     */
    #[DataProvider('portBindings')]
    public function test_every_port_resolves_to_its_adapter(string $port, string $concrete): void
    {
        $this->assertInstanceOf($concrete, $this->app->make($port));
    }

    /**
     * @param  class-string  $port
     * @param  class-string  $concrete
     */
    #[DataProvider('portBindings')]
    public function test_every_port_is_memoized(string $port, string $concrete): void
    {
        // 🔴 Daghighan hamun bug e projeh ye marja': bind-i ke resolve
        // nemishe، har bar yek instance e taze (ya hichi) mide va kasi
        // motavajeh nemishe.
        $this->assertSame($this->app->make($port), $this->app->make($port));
    }

    /**
     * Har chizi ke provider misaze — bedun e estesna.
     *
     * @return array<string, array{0: class-string}>
     */
    public static function resolvables(): array
    {
        return [
            'tick pipeline' => [TickPipeline::class],
            'influx spool' => [InfluxSpool::class],
            'ingest tick' => [IngestTickAction::class],
            'provider spread action' => [ProviderSpreadAction::class],
            'provider action' => [ProviderAction::class],
            'record health' => [RecordProviderHealthAction::class],
            'board query' => [BoardQuery::class],
            'candle query' => [CandleQuery::class],
            'provider query' => [ProviderQuery::class],
            'spread query' => [SpreadQuery::class],
            'source manager' => [SourceManager::class],
            'tick command' => [PricingTickCommand::class],
            'influx provision command' => [PricingInfluxProvisionCommand::class],
            'influx drain command' => [PricingInfluxDrainCommand::class],
            'influx health command' => [PricingInfluxHealthCommand::class],
        ];
    }

    /** @param class-string $class */
    #[DataProvider('resolvables')]
    public function test_everything_the_module_offers_can_be_built(string $class): void
    {
        $this->assertInstanceOf($class, $this->app->make($class));
    }

    // ── 🔴 SDK e Influx hargez bind nemishe ─────────────────────────

    /**
     * `InfluxDB2\Client` / `WriteApi` / `QueryApi` mutable-and va handle e
     * curl negah midaran. Zire Swoole، share kardan eshun beyn e coroutine
     * ha response ha ro GHATI mikone va buffer e `WriteApi` e BATCHING beyn e
     * request ha nasht mikone (va ba recycle e worker saket khali mishe).
     *
     * Ma aslan SDK estefade nemikonim — `Http` + `FluxCsv`. In test mohafez e
     * un tasmim ast.
     */
    public function test_the_influx_sdk_is_never_bound(): void
    {
        foreach (['InfluxDB2\Client', 'InfluxDB2\WriteApi', 'InfluxDB2\QueryApi'] as $class) {
            $this->assertFalse(
                $this->app->bound($class),
                "«{$class}» nabayad bind beshe — mutable ast va zire Swoole nasht mikone.",
            );
        }
    }

    // ── Tartib e Stage ha ───────────────────────────────────────────

    /**
     * 🔴 `Validate` bayad GHABL az `Enrich` bashe.
     *
     * Enrich kardan e quote-i ke gharar ast rad beshe faghat kar e ezafi
     * nist — `dayHigh`/`dayLow` ro ba yek meghdar e KHARAB aludeh mikone، va
     * un band ba'd tu Redis va Postgres mineshine. Yek radif e kharab e
     * rad-shode bayad hich asari nazare.
     */
    public function test_the_pipeline_stages_are_in_the_documented_order(): void
    {
        $stages = $this->stages();

        $this->assertSame([
            FetchStage::class,
            ValidateStage::class,
            EnrichStage::class,
            PersistStage::class,
            PublishStage::class,
        ], array_map(static fn (object $stage): string => $stage::class, $stages));
    }

    public function test_validate_comes_before_enrich(): void
    {
        $names = array_map(static fn (object $stage): string => $stage->name(), $this->stages());

        $this->assertLessThan(
            array_search('enrich', $names, true),
            array_search('validate', $names, true),
        );
    }

    // ── Logger ──────────────────────────────────────────────────────

    /**
     * 🔴 Hoshdar haye module bayad tu channel e `pricing` bioftan.
     *
     * Autowire `?LoggerInterface` ro be channel e DEFAULT resolve mikone.
     * Un vaght `providers_unavailable`، `lock_unavailable`،
     * `health_write_failed` va `spread_version_bump_failed` tu log e omumi
     * gom mishan — dar hali ke kol e dalil e vojud e channel e `pricing` in
     * ast ke hamun hoshdar ha yek ja ye JSON e ghabl e query bashan.
     */
    public function test_the_module_logs_into_the_pricing_channel(): void
    {
        foreach ([TickPipeline::class, IngestTickAction::class, ProviderSpreadAction::class] as $class) {
            $logger = $this->property($this->app->make($class), 'logger');

            $this->assertNotNull($logger, "«{$class}» bedun e logger sakhte shode.");

            // ⚠️ Esm e Monolog check NEMISHE: Laravel un ro az `app.env`
            // migire (ya'ni «local»)، na az esm e channel. `LogManager`
            // channel ha ro memoize mikone، pas moghayese ye HOVIAT
            // mostaghim tarin javab ast.
            $this->assertSame(
                Log::channel('pricing'),
                $logger,
                "«{$class}» tu channel e ghalat log mizane.",
            );
        }
    }

    /**
     * `PRICING_LOG=false` bayad VAGHE'AN khamoosh kone.
     *
     * ⚠️ `pricing.log.enabled` tu config bud vali hichvaght khunde nemishod
     * — yek kelid e config ke kar nemikone yek dorugh ast.
     */
    public function test_disabling_the_log_swaps_in_a_null_logger(): void
    {
        config()->set('pricing.log.enabled', false);
        $this->app->forgetInstance(ProviderSpreadAction::class);
        $this->app->forgetInstance(TickPipeline::class);

        $this->assertInstanceOf(
            NullLogger::class,
            $this->property($this->app->make(ProviderSpreadAction::class), 'logger'),
        );
        $this->assertInstanceOf(
            NullLogger::class,
            $this->property($this->app->make(TickPipeline::class), 'logger'),
        );
    }

    // ── Cache e candle ──────────────────────────────────────────────

    /**
     * 🔴 `CandleQuery` store esh SARIH ast، na default e app.
     *
     * `CACHE_STORE=database` chart ro mindaze ru POSTGRES — hamun DB-i ke
     * kol e cache baraye mohafezat azash hast. Yek series e `h1` e yek-sale
     * 8,760 candle ast.
     */
    public function test_the_candle_cache_uses_the_configured_store(): void
    {
        // ⚠️ Default e app bayad BA store e config FARGH kone، vagarna test
        // bi-ma'ni-e. (Avval `pricing.cache_store` ro `array` gozashtam —
        // hamun default e test bud — va mutation e `->store()` e khali ro
        // test NAGEREFT.)
        config()->set('cache.stores.nullish', ['driver' => 'null']);
        config()->set('cache.default', 'nullish');
        config()->set('pricing.cache_store', 'array');
        $this->app->forgetInstance(CandleQuery::class);

        $cache = $this->property($this->app->make(CandleQuery::class), 'cache');

        $this->assertInstanceOf(ArrayStore::class, $cache->getStore());
        $this->assertNotInstanceOf(NullStore::class, $cache->getStore());
    }

    public function test_the_candle_cache_falls_back_to_the_app_default_store(): void
    {
        config()->set('cache.stores.nullish', ['driver' => 'null']);
        config()->set('cache.default', 'nullish');
        config()->set('pricing.cache_store', null);
        $this->app->forgetInstance(CandleQuery::class);

        $cache = $this->property($this->app->make(CandleQuery::class), 'cache');

        $this->assertInstanceOf(NullStore::class, $cache->getStore());
    }

    // ── Config ──────────────────────────────────────────────────────

    public function test_the_module_config_is_merged(): void
    {
        // `mergeConfigFrom` — config tu `app/Modules/Pricing/Config/` mimune
        // va publish nemishe tu `config/`.
        $this->assertSame(15, config('pricing.tick_seconds'));
        $this->assertSame('3h30m', config('pricing.influx.tz_offset'));
        $this->assertSame('candles_1d', config('pricing.influx.buckets.d1'));
    }

    // ── 🔴 Zamanbandi ───────────────────────────────────────────────

    /**
     * `pricing:tick` AMDAN tu scheduler NIST.
     *
     * Kamtarin daghighat e scheduler e Laravel yek DAGHIGHE ast — aslan
     * nemitune tick e 15 saniye-i bede. Un tu container e `price-worker` ba
     * yek halghe ye grid-aligned ejra mishe. Agar ruzi kasi un ro be
     * scheduler ezafe kone، tick haye OFF-GRID va hamzaman ba worker peyda
     * mishan — va timestamp e floor-shode (paye ye idempotency e Influx)
     * ma'ni esh ro az dast mide.
     */
    public function test_the_tick_is_not_scheduled(): void
    {
        $scheduled = $this->scheduledCommands();

        // Avval in assert khali bud chun regex e ghalat hamishe array e
        // khali mid va test BI-MA'NI pass mishod.
        $this->assertNotEmpty($scheduled, 'zamanbandi khali-e — test chizi ro check nemikone.');
        $this->assertNotContains('pricing:tick', $scheduled);
    }

    public function test_the_influx_maintenance_commands_are_scheduled(): void
    {
        $scheduled = $this->scheduledCommands();

        // Bedun e `drain`، tick haye spool-shode ba `LTRIM` saket dur rikhte
        // mishan — gap e daemi tu candle ha.
        $this->assertContains('pricing:influx:drain', $scheduled);

        // Bedun e `health`، yek task e Influx ke SAKET morde bashe ro avvalin
        // bar yek KARBAR mifahme — hafte ha ba'd.
        $this->assertContains('pricing:influx:health', $scheduled);
    }

    // ── Helper ha ───────────────────────────────────────────────────

    /** @return array<int, object> */
    private function stages(): array
    {
        return $this->property($this->app->make(TickPipeline::class), 'stages');
    }

    /**
     * @return array<int, string>
     */
    private function scheduledCommands(): array
    {
        // `routes/console.php` faghat vaghti load mishe ke console kernel
        // `commands()` esh ro ejra kone — tu test khodkar nemishe.
        $this->artisan('schedule:list')->run();

        $commands = [];

        foreach ($this->app->make(Schedule::class)->events() as $event) {
            // ⚠️ `$event->command` in shekl ast:
            //   '/usr/local/bin/php' 'artisan' pricing:influx:drain
            // Esm e command NAGHL-GHOL NASHODE ast — yek regex ke quote
            // mikhast، array e KHALI bar migardund va un vaght
            // `assertNotContains` bi-ma'ni pass mishod.
            if (preg_match('/(pricing:[a-z:]+)/', (string) $event->command, $matches) === 1) {
                $commands[] = $matches[1];
            }
        }

        return $commands;
    }

    private function property(object $object, string $name): mixed
    {
        return (new ReflectionClass($object))->getProperty($name)->getValue($object);
    }
}
