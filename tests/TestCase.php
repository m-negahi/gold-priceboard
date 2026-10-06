<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Facade;

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

        /*
         * 🔴 Hamun dastan e ma'ruf e in proje: container `APP_ENV=local` ro
         * be onvan e env e VAGHEI ye OS export mikone va bar phpunit.xml
         * barande mishe. `runningUnitTests()` e Laravel az `$app['env']`
         * mikhune — pas bedun e in khat، test «testing» shenasayi NEMISHE va
         * `PreventRequestForgery` (CSRF) tu test ha ham fa'al mimune: har
         * POST e feature-test 419 migire.
         */
        $app['env'] = 'testing';

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

        // ── Redis e default tu test: database e 13 ─────────────
        // `RedisOtpStore` ru connection e `default` mineviseh؛ bedun e in،
        // test haye OTP kelid haye DEV (db 0 — Horizon، cache) ro dast
        // mizanan.
        $config->set('database.redis.default.database', '13');
        foreach (['default', 'cache', 'prices'] as $connection) {
            $config->set('database.redis.'.$connection.'.url', null);
        }

        // ── Redis e gheymat: database e 15، na 0 ────────────────
        // `RedisHotCacheTest` `flushdb()` mizane — ru db e 0 un tablo ye
        // dev ro pak mikone.
        $config->set('database.redis.prices.database', '15');

        // 🔴 `RedisManager` TU BOOTSTRAP sakhte shode va yek COPY az config e
        // UN lahze (db 0) dare؛ do khat e bala ru un hich asari nadashtan.
        // Natije: `flushdb()` e test ha Redis e DEV ro pak mikard va session
        // e admin ba har ejra ye suite mipparid (logout e mokarrar — ba canary
        // ta'eed shod، 2026-10-06). Manager az no ba config e jadid sakhte
        // mishe. Negahban: `GuardsTest` DB e ZENDE ye connection ro check
        // mikone، na config.
        $app->forgetInstance('redis');
        $app->forgetInstance('redis.connection');
        Facade::clearResolvedInstance('redis');

        // ── Abzar haye dev ─────────────────────────────────────
        $config->set('telescope.enabled', false);
        $config->set('telescope.storage.database.connection', 'sqlite');
        $config->set('pulse.enabled', false);

        // ── SMS: driver e fake — hich IO ───────────────────────
        $config->set('sms.driver', 'fake');
        $config->set('modules.kyc.token', '');
        $config->set('billing.zibal.merchant', '');
        $config->set('database.redis.cache.database', '14');

        // ── OTP: meghdar haye SABET e test ─────────────────────
        // `.env` e dev `BYPASS_OTP=true` va `OTP_LENGTH=5` dare va container
        // un ha ro be onvan e env e VAGHEI ye OS export mikone — hamun
        // masiri ke yek bar DB e dev ro pak kard. Test ha nabayad be tanzim
        // e dev vabaste bashan، makhsusan bypass e OTP.
        $config->set('otp.bypass', false);
        // 🔴 Rate limit tu test HAMISHE roshan — `OtpHttpHardeningTest` un
        // ro misanje، va driver e `fake` e test nabayad khamoosh esh kone.
        $config->set('otp.throttle', true);
        $config->set('otp.ttl_seconds', 120);
        $config->set('otp.resend_cooldown_seconds', 60);
        $config->set('otp.max_attempts', 3);
        $config->set('otp.digits', 6);

        // ── Influx: test ha az fake estefade mikonan؛ test haye
        // integration khodeshun config e sarih pass midan.
        $config->set('pricing.influx.url', env('PRICING_INFLUX_URL', 'http://influxdb:8086'));
    }
}
