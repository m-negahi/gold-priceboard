<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * 🔴 Mohafez e database e dev — kamarband E DOVOM.
 *
 * ── Chi ettefagh oftad ──────────────────────────────────────────────
 * `phpunit.xml` `<env name="DB_CONNECTION" value="sqlite" force="true"/>`
 * dare. Un **KAFI NABUD**: container e `octane` `DB_CONNECTION=pgsql` va
 * `DB_DATABASE=priceboard` ro be onvan e env e VAGHEI ye OS export mikone،
 * va un bar `phpunit.xml` barande shod. Natije: `RefreshDatabase` ru
 * database e DEV ejra shod va jadval e `providers` ro khali kard.
 * Hamin tor `RedisHotCacheTest::flushdb()` ru db e 0 tablo ye dev ro pak kard.
 *
 * ── Hall ────────────────────────────────────────────────────────────
 * Config BA'D az bootstrap be zoor set mishe. Inja dige hich env e OS-i
 * nemitune barande beshe — in akharin harf ast.
 *
 * `GuardsTest` in ro negahbani mikone: agar ruzi kasi in ro bardare،
 * un test fail mishe، na inke data ye dev bere.
 */
abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->forceTestConfig($app);

        return $app;
    }

    /**
     * Majbur kardan e config e test — ba'd az bootstrap، pas env e OS
     * nemitune bar un barande beshe.
     */
    private function forceTestConfig(Application $app): void
    {
        $config = $app->make('config');

        // ── Database: hamishe sqlite e in-memory ────────────────
        $config->set('database.default', 'sqlite');
        $config->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'url' => null,
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // ── Chiz haye moshtarak: hichvaght service e vaghei ─────
        $config->set('cache.default', 'array');
        $config->set('queue.default', 'sync');
        $config->set('session.driver', 'array');
        $config->set('mail.default', 'array');
        $config->set('broadcasting.default', 'null');

        // ── Redis e gheymat: database e 15، na 0 ────────────────
        // `RedisHotCacheTest` `flushdb()` mizane — ru db e 0 un tablo ye
        // dev ro pak mikone.
        $config->set('database.redis.prices.database', '15');

        // ── Abzar haye dev ─────────────────────────────────────
        $config->set('telescope.enabled', false);
        $config->set('pulse.enabled', false);

        // ── SMS: driver e fake — hich IO ───────────────────────
        $config->set('sms.driver', 'fake');

        // ── Influx: test ha az fake estefade mikonan؛ test haye
        // integration khodeshun config e sarih pass midan.
        $config->set('pricing.influx.url', env('PRICING_INFLUX_URL', 'http://influxdb:8086'));
    }
}
