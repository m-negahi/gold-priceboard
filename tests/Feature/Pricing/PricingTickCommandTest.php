<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class PricingTickCommandTest extends TestCase
{
    use RefreshDatabase;

    private function provider(): Provider
    {
        return Provider::create([
            'key' => 'mock',
            'driver' => 'mock',
            'name' => 'Mock',
            'name_fa' => 'آزمایشی',
            'is_active' => true,
        ]);
    }

    public function test_runs_a_tick(): void
    {
        $this->provider();

        $this->artisan('pricing:tick')->assertSuccessful();
    }

    public function test_reports_when_there_is_nothing_to_poll(): void
    {
        $this->artisan('pricing:tick')
            ->expectsOutputToContain('rad shod')
            ->assertFailed();
    }

    public function test_json_output_is_machine_readable(): void
    {
        $this->provider();

        $this->artisan('pricing:tick --json')->assertSuccessful();
    }

    // ── 🔴 `--force` ────────────────────────────────────────────────

    /**
     * 🔴 Bedun e `--force`، yek tick e DASTI ke bala-fasele ba'd az tick e
     * worker ejra she «hich provider e fa'ali baraye poll nist» mide — chun
     * worker `next_poll_at` ro 15 saniye jolo gozashte.
     *
     * Un payam mesl e yek khata ye CONFIG be nazar mirese («provider fa'al
     * nadarim»)، na mesl e «15 saniye sabr kon». Va daghighan hamun kari-e
     * ke skill e `add-price-provider` mige: «avval `pricing:tick` ro dasti
     * bezan، ba'd `TickMonitor` ro check kon». Bedun e `--force` un ravesh e
     * kar kar NEMIKONE.
     */
    public function test_force_polls_a_provider_that_is_not_due_yet(): void
    {
        $provider = $this->provider();
        $provider->forceFill(['next_poll_at' => now()->addHour()])->save();

        // Bedun e force: rad mishe.
        $this->artisan('pricing:tick')
            ->expectsOutputToContain('rad shod')
            ->assertFailed();

        // Ba force: ejra mishe.
        $this->artisan('pricing:tick --force')->assertSuccessful();
    }

    public function test_force_does_not_move_the_tick_off_the_grid(): void
    {
        $this->provider();

        // Do ta force e poshte-sar-e-ham tu yek panjare ye 15 saniye-i bayad
        // HAMUN timestamp ro bedan — `TickTime` hanuz floor mishe، pas
        // idempotency e Influx dast nemikhore.
        $first = $this->tickJson('pricing:tick --json --force');
        $second = $this->tickJson('pricing:tick --json --force');

        $this->assertSame($first['at'], $second['at']);
    }

    // ── 🔴 Shekl e JSON ─────────────────────────────────────────────

    /**
     * 🔴 `sinks` bayad HAMISHE object bashe، ham por ham khali.
     *
     * `json_encode` yek array e PHP e khali ro `[]` mikone va yek array ba
     * kelid e string ro `{}`. Pas `sinks` vaghti por bud object mishod va
     * vaghti khali bud ARRAY — do shekl e motefavet baraye yek field.
     * `scripts/resilience-check.sh` hamin ja gir kard: `sinks.influx` tu
     * yek tick e rad-shode aslan ghabl e address dadan nabud.
     *
     * Phase 4 in payload ro MIFROUSHE، pas har client e type-dar ba in
     * misheknae.
     */
    public function test_keyed_maps_stay_objects_even_when_empty(): void
    {
        // Hich provider-i nist -> tick rad mishe -> hame ye map ha khali-an.
        $json = $this->rawJson('pricing:tick --json');

        $this->assertTrue($json['skipped']);

        foreach (['sinks', 'providers', 'unmapped'] as $key) {
            $this->assertMatchesRegularExpression(
                '/"'.$key.'":\{/',
                $json['raw'],
                "«{$key}» bayad object bashe، na array.",
            );
        }

        // `rejections` amdan LIST mimune — un vaghe'an yek lista-st.
        $this->assertMatchesRegularExpression('/"rejections":\[/', $json['raw']);
    }

    public function test_keyed_maps_are_objects_when_populated_too(): void
    {
        $this->provider();

        $json = $this->rawJson('pricing:tick --json --force');

        $this->assertMatchesRegularExpression('/"sinks":\{/', $json['raw']);
        $this->assertArrayHasKey('redis', $json['sinks']);
        $this->assertArrayHasKey('mock', $json['providers']);
    }

    // ── Helper ──────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function tickJson(string $command): array
    {
        return $this->rawJson($command);
    }

    /** @return array<string, mixed> */
    private function rawJson(string $command): array
    {
        // ⚠️ `$this->artisan(...)->run()` khoruji ro tu buffer e
        // `Artisan::output()` nemizare — `Artisan::call()` mizare.
        Artisan::call($command);

        $output = Artisan::output();

        $line = '';

        foreach (explode("\n", trim($output)) as $candidate) {
            if (str_starts_with(trim($candidate), '{')) {
                $line = trim($candidate);
            }
        }

        $this->assertNotSame('', $line, 'hich khat e JSON-i chap nashod.');

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        $decoded['raw'] = $line;

        return $decoded;
    }

    /**
     * ⚠️ Shekast e yek provider `FAILURE` NIST: halghe ye worker nabayad be
     * khatere yek ghat'i ye gozara motevaghef beshe.
     */
    public function test_a_failing_provider_still_exits_successfully(): void
    {
        $this->provider();

        Provider::create([
            'key' => 'broken',
            'driver' => 'driver-e-khiali',
            'name' => 'Broken',
            'name_fa' => 'خراب',
            'is_active' => true,
        ]);

        $this->artisan('pricing:tick')->assertSuccessful();
    }

    public function test_quiet_ok_suppresses_healthy_output(): void
    {
        $this->provider();

        $this->artisan('pricing:tick --quiet-ok')->assertSuccessful();
    }
}
