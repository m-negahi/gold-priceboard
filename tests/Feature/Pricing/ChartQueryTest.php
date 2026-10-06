<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Pricing\Application\Actions\SymbolPriorityAction;
use App\Modules\Pricing\Application\Dto\CandleDto;
use App\Modules\Pricing\Application\Queries\CandleQuery;
use App\Modules\Pricing\Application\Queries\ChartQuery;
use App\Modules\Pricing\Domain\Enums\ChartPeriod;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Ports\TimeSeriesPort;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\Support\Fakes\FakeTimeSeries;
use Tests\TestCase;

/**
 * Chart e panel e admin (P5-08): Influx -> (spread) -> tajmi' e Jalali.
 */
final class ChartQueryTest extends TestCase
{
    use RefreshDatabase;

    private FakeTimeSeries $series;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection('prices')->flushdb();

        $this->series = new FakeTimeSeries;
        $this->app->instance(TimeSeriesPort::class, $this->series);
        $this->app->forgetInstance(CandleQuery::class);
    }

    private function provider(string $key, int $sort): void
    {
        Provider::query()->create([
            'key' => $key, 'driver' => 'tgju', 'name' => $key, 'name_fa' => $key, 'is_active' => true, 'sort' => $sort,
        ]);
    }

    private function day(string $tehranDate, float $close): CandleDto
    {
        $ms = CarbonImmutable::parse($tehranDate.' 00:00:00', 'Asia/Tehran')->getTimestampMs();

        return new CandleDto(timestamp: $ms, open: $close - 1, high: $close + 1, low: $close - 2, close: $close, volume: 1);
    }

    private function chart(): ChartQuery
    {
        return $this->app->make(ChartQuery::class);
    }

    public function test_daily_bars_pass_through_and_weekly_bars_are_aggregated(): void
    {
        $this->provider('tgju', 10);
        // Shanbe ta doshanbe = yek hafte.
        $this->series->willReturn([
            $this->day('2026-10-03', 100),
            $this->day('2026-10-04', 110),
            $this->day('2026-10-05', 120),
        ]);

        $daily = $this->chart()->series(Instrument::Gold18Gram, 'tgju', ChartPeriod::D1);
        $weekly = $this->chart()->series(Instrument::Gold18Gram, 'tgju', ChartPeriod::W1);

        self::assertCount(3, $daily['candles']);
        self::assertCount(1, $weekly['candles']);
        self::assertSame(120.0, $weekly['candles'][0]->close);
        self::assertSame('tgju', $weekly['provider']);
    }

    public function test_an_unknown_provider_falls_back_to_the_first_in_the_symbols_chain(): void
    {
        $this->provider('tgju', 10);
        $this->provider('estjt', 20);
        // Olaviat e per-symbol (P5-07) — chart ham az hamun peyravi kone.
        $this->app->make(SymbolPriorityAction::class)->set(Instrument::Gold18Gram, ['estjt']);

        $result = $this->chart()->series(Instrument::Gold18Gram, 'nist', ChartPeriod::D1);

        self::assertSame('estjt', $result['provider']);
    }

    public function test_no_active_provider_is_an_empty_degraded_series(): void
    {
        $result = $this->chart()->series(Instrument::Gold18Gram, '', ChartPeriod::D1);

        self::assertSame([], $result['candles']);
        self::assertTrue($result['degraded']);
    }

    // ── HTTP ────────────────────────────────────────────────────────

    public function test_the_json_endpoint_is_admin_only_and_validates_its_input(): void
    {
        $this->provider('tgju', 10);
        $this->series->willReturn([$this->day('2026-10-03', 100)]);

        $this->getJson(route('panel.charts.data', ['symbol' => 'xau-18k-g-irr', 'period' => 'w1']))->assertUnauthorized();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson(route('panel.charts.data', ['symbol' => 'nist']))->assertStatus(422);

        $this->actingAs($admin)
            ->getJson(route('panel.charts.data', ['symbol' => 'xau-18k-g-irr', 'period' => 'w1']))
            ->assertOk()
            ->assertJsonPath('period', 'w1')
            ->assertJsonPath('provider', 'tgju')
            ->assertJsonCount(1, 'data');
    }

    public function test_the_page_renders_with_symbol_preselected(): void
    {
        $this->provider('tgju', 10);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('panel.charts', ['symbol' => 'xag-925-g-irr']))
            ->assertOk()
            ->assertSee('نمودار قیمت')
            ->assertViewHas('symbol', 'xag-925-g-irr');
    }

    /** Legend e «منابع روی نمودار» faghat provider haye fa'al + `in_chart` (2026-10-06). */
    public function test_only_active_in_chart_providers_get_an_overlay_line(): void
    {
        $this->provider('tgju', 10);
        $this->provider('estjt', 20);
        Provider::query()->where('key', 'estjt')->update(['in_chart' => false]);
        Provider::query()->create(['key' => 'off', 'driver' => 'mock', 'name' => 'Off', 'name_fa' => 'خاموش', 'is_active' => false, 'sort' => 30]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('panel.charts'))
            ->assertOk()
            ->assertSee('data-chart-legend', false)
            ->assertViewHas('chartProviders', static fn (array $providers): bool => array_keys($providers) === ['tgju']);
    }
}
