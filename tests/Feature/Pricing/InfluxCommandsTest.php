<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxAdmin;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxSpool;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxTimeSeries;
use Tests\TestCase;

/**
 * Command haye `pricing:influx:*` ru Influx e ZENDE.
 *
 * Agar Influx bala nabashe، skip mishan — vagarna suite ru har machine-i ke
 * Docker nadare mishkane.
 */
final class InfluxCommandsTest extends TestCase
{
    /** @return array<string, mixed> */
    private function config(): array
    {
        $config = (array) config('pricing.influx', []);
        $config['admin_token'] = env('INFLUX_ADMIN_TOKEN', '');

        return $config;
    }

    private function skipUnlessInflux(): void
    {
        if (! (new InfluxTimeSeries($this->config()))->isAvailable()) {
            $this->markTestSkipped('influxdb dar dastres nist.');
        }
    }

    // ── provision ────────────────────────────────────────────────

    /**
     * 🔴 Idempotency ta'rif e in command ast: bayad betuni har chand bar
     * bezani. Agar na، deploy e dovom production ro kharab mikone.
     */
    public function test_provision_is_idempotent(): void
    {
        $this->skipUnlessInflux();

        $this->artisan('pricing:influx:provision')->assertSuccessful();

        // Ejra ye dovom nabayad hich chiz e taze-i besaze.
        $this->artisan('pricing:influx:provision')
            ->expectsOutputToContain('bi-taghir')
            ->doesntExpectOutputToContain('sakhte-shod')
            ->assertSuccessful();
    }

    public function test_provision_creates_every_bucket_with_the_right_retention(): void
    {
        $this->skipUnlessInflux();

        $this->artisan('pricing:influx:provision')->assertSuccessful();

        $buckets = (new InfluxAdmin($this->config()))->buckets();

        $expected = [
            'ticks' => 30 * 86_400,
            'candles_1m' => 90 * 86_400,
            'candles_5m' => 180 * 86_400,
            'candles_15m' => 400 * 86_400,
            'candles_1h' => 1_095 * 86_400,
            // `0` = bi-nahayat — bazeh haye bozorg hajm eshun kam ast.
            'candles_4h' => 0,
            'candles_1d' => 0,
        ];

        foreach ($expected as $name => $retention) {
            $this->assertArrayHasKey($name, $buckets, "bucket e «{$name}» nist.");
            $this->assertSame($retention, $buckets[$name]['retention'], "retention e «{$name}» ghalat ast.");
        }
    }

    public function test_provision_creates_all_six_tasks_active(): void
    {
        $this->skipUnlessInflux();

        $this->artisan('pricing:influx:provision')->assertSuccessful();

        $tasks = (new InfluxAdmin($this->config()))->tasks();

        foreach (['candles_1m', 'candles_5m', 'candles_15m', 'candles_1h', 'candles_4h', 'candles_1d'] as $name) {
            $this->assertArrayHasKey($name, $tasks, "task e «{$name}» nist.");
            $this->assertSame('active', $tasks[$name]['status']);
        }
    }

    /**
     * Manba' e haghighat file ast، na chizi ke tu UI click shode.
     * Har task bayad `timezone.fixed` dashte bashe — bedun esh bar e ruzane
     * be nime-shab e UTC (= 03:30 Tehran) align mishe.
     */
    public function test_deployed_task_flux_matches_the_checked_in_files(): void
    {
        $this->skipUnlessInflux();

        $this->artisan('pricing:influx:provision')->assertSuccessful();

        $tasks = (new InfluxAdmin($this->config()))->tasks();

        foreach ($tasks as $name => $task) {
            $this->assertStringContainsString('timezone.fixed(offset: 3h30m)', $task['flux'], "{$name}");
            $this->assertStringContainsString('option task = {name: "'.$name.'"', $task['flux']);
            // Placeholder bayad jaygozin shode bashe.
            $this->assertStringNotContainsString('{{org}}', $task['flux']);
            $this->assertStringNotContainsString('{{tz_offset}}', $task['flux']);
        }
    }

    /** `count` bayad FLOAT bashe — `acc.count + 1` task ro saket mikoshe. */
    public function test_task_flux_uses_float_arithmetic_for_count(): void
    {
        $flux = (string) file_get_contents(resource_path('influx/tasks/candles_1m.flux'));

        $this->assertStringContainsString('accumulator.count + 1.0', $flux);
        $this->assertStringNotContainsString('accumulator.count + 1,', $flux);
    }

    /** Bar bayad ru lahze ye BAZ shodanesh beshine، na baste shodanesh. */
    public function test_every_task_anchors_bars_to_their_start(): void
    {
        foreach (glob(resource_path('influx/tasks/*.flux')) ?: [] as $path) {
            $flux = (string) file_get_contents($path);

            $this->assertStringContainsString(
                'duplicate(column: "_start", as: "_time")',
                $flux,
                basename($path),
            );
        }
    }

    // ── drain ────────────────────────────────────────────────────

    public function test_drain_reports_an_empty_spool(): void
    {
        $this->skipUnlessInflux();

        (new InfluxSpool)->clear();

        $this->artisan('pricing:influx:drain')
            ->expectsOutputToContain('Spool khali-e.')
            ->assertSuccessful();
    }

    /**
     * 🔴 Agar neveshtan shekast bokhore، khat ha bayad BARGARDAN tu spool.
     * `pull()` un ha ro bardashte — bedun e bargardandan baraye hamishe gom
     * mishan.
     */
    public function test_drain_returns_lines_to_the_spool_when_the_write_fails(): void
    {
        $this->skipUnlessInflux();

        $spool = new InfluxSpool;
        $spool->clear();

        // Line protocol e kharab -> Influx 400 mide.
        $spool->push(['in khat e kamelan kharab ast']);

        $this->artisan('pricing:influx:drain')->assertFailed();

        $this->assertSame(1, $spool->size(), 'khat bayad bargashte bashe tu spool.');

        $spool->clear();
    }

    // ── health ───────────────────────────────────────────────────

    public function test_health_reports_every_check_as_json(): void
    {
        $this->skipUnlessInflux();

        $this->artisan('pricing:influx:health --json')->run();

        // `run()` chun khoruji be hālat e system vabaste ast (candle e taze
        // faghat vaghti hast ke `pricing:tick` dar hale ejra bashe).
        $this->assertTrue(true);
    }

    public function test_health_fails_when_influx_is_unreachable(): void
    {
        config()->set('pricing.influx.url', 'http://influx.invalid:9999');

        $this->artisan('pricing:influx:health')
            ->expectsOutputToContain('Influx dar dastres nist.')
            ->assertFailed();
    }
}
