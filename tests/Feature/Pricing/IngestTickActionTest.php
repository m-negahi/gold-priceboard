<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Actions\IngestTickAction;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceSnapshot;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `IngestTickAction` ba DB e vaghei — vali ba `mock` be onvan e Source،
 * pas hich request e biruni.
 */
final class IngestTickActionTest extends TestCase
{
    use RefreshDatabase;

    private function provider(string $key = 'mock', string $driver = 'mock', bool $active = true): Provider
    {
        return Provider::create([
            'key' => $key,
            'driver' => $driver,
            'name' => ucfirst($key),
            'name_fa' => 'آزمایشی',
            'is_active' => $active,
        ]);
    }

    private function action(): IngestTickAction
    {
        return $this->app->make(IngestTickAction::class);
    }

    public function test_a_tick_with_no_providers_is_skipped(): void
    {
        $report = $this->action()->execute();

        $this->assertTrue($report->skipped);
        $this->assertStringContainsString('provider', (string) $report->skipReason);
    }

    public function test_inactive_providers_are_not_polled(): void
    {
        $this->provider(active: false);

        $this->assertTrue($this->action()->execute()->skipped);
    }

    public function test_a_tick_stores_every_instrument(): void
    {
        $this->provider();

        $report = $this->action()->execute();

        $this->assertFalse($report->skipped);
        // 15 instrument + yeki ezafe chun abshode bid va ask dare.
        $this->assertSame(count(Instrument::cases()) + 1, $report->accepted);
        $this->assertSame($report->accepted, PriceSnapshot::query()->count());
    }

    public function test_health_is_recorded_on_the_provider_row(): void
    {
        $provider = $this->provider();

        $this->action()->execute();

        $provider->refresh();

        $this->assertTrue($provider->last_success_at !== null);
        $this->assertSame(0, $provider->consecutive_failures);
        $this->assertSame('healthy', $provider->health_state->value);
        $this->assertNotNull($provider->next_poll_at);
    }

    /** `next_poll_at` ya'ni provider e arzun 15s va geroon 60s poll mishe. */
    public function test_next_poll_is_scheduled_from_the_provider_interval(): void
    {
        $provider = $this->provider();
        $provider->update(['poll_interval_seconds' => 60]);

        $this->action()->execute();
        $provider->refresh();

        $this->assertGreaterThan(now()->addSeconds(50), $provider->next_poll_at);
    }

    public function test_a_provider_not_yet_due_is_skipped(): void
    {
        $provider = $this->provider();
        $provider->update(['next_poll_at' => now()->addMinutes(5)]);

        $this->assertTrue($this->action()->execute()->skipped);
    }

    /**
     * Driver e nashenas = eshtebah e config، na ghat'i. Nabayad baghi ye
     * provider ha ro bokoshe.
     */
    public function test_an_unknown_driver_does_not_break_the_tick(): void
    {
        $this->provider('mock', 'mock');
        $this->provider('broken', 'driver-e-khiali');

        $report = $this->action()->execute();

        $this->assertFalse($report->skipped);
        $this->assertGreaterThan(0, $report->accepted);
        $this->assertArrayNotHasKey('broken', $report->health);
    }

    public function test_every_sink_is_reported(): void
    {
        $this->provider();

        $report = $this->action()->execute();

        $this->assertArrayHasKey('postgres', $report->sinks);
        $this->assertTrue($report->sinks['postgres']->succeeded);
    }

    /** Tick e dovom bayad UPDATE kone، na radif e jadid. */
    public function test_two_ticks_do_not_duplicate_rows(): void
    {
        $this->provider();

        $this->action()->execute();
        $first = PriceSnapshot::query()->count();

        Provider::query()->update(['next_poll_at' => null]);
        $this->action()->execute();

        $this->assertSame($first, PriceSnapshot::query()->count());
    }

    /**
     * 🔴 Override e `freshness_seconds` ru (provider, symbol).
     *
     * Bedun e in، `ons` e tgju — ke faghat RUZANE be-rooz mishe — har tick
     * rad mishe chun default e jahani 180s ast.
     */
    public function test_provider_query_loads_symbol_freshness_overrides(): void
    {
        $provider = $this->provider('tgju', 'tgju');

        ProviderSpread::create([
            'provider_id' => $provider->id,
            'symbol' => Instrument::GoldOunce->value,
            'buy_mode' => 'percent', 'buy_value' => 0,
            'sell_mode' => 'percent', 'sell_value' => 0,
            'freshness_seconds' => 90_000,
        ]);

        $configs = $this->app->make(ProviderQuery::class)->dueForPoll(now());

        $this->assertArrayHasKey('tgju', $configs);
        $this->assertSame(90_000, $configs['tgju']->freshnessFor(Instrument::GoldOunce));
        // Symbol e bedun e override default e khodesh ro negah midare.
        $this->assertSame(900, $configs['tgju']->freshnessFor(Instrument::Gold18Gram));
    }

    /** Spread e wildcard nabayad be onvan e override e symbol khunde beshe. */
    public function test_wildcard_spread_is_not_treated_as_a_symbol_override(): void
    {
        $provider = $this->provider('tgju', 'tgju');

        ProviderSpread::create([
            'provider_id' => $provider->id,
            'symbol' => ProviderSpread::WILDCARD,
            'buy_mode' => 'percent', 'buy_value' => 0,
            'sell_mode' => 'percent', 'sell_value' => 0,
            'freshness_seconds' => 12_345,
        ]);

        $configs = $this->app->make(ProviderQuery::class)->dueForPoll(now());

        $this->assertSame(900, $configs['tgju']->freshnessFor(Instrument::Gold18Gram));
    }
}
