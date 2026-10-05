<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Dto\CandleDto;
use App\Modules\Pricing\Application\Dto\CandleRequestDto;
use App\Modules\Pricing\Application\Queries\CandleQuery;
use App\Modules\Pricing\Application\Queries\SpreadQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Interval;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpread;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\ArrayHotCache;
use Tests\Support\Fakes\FakeTimeSeries;
use Tests\TestCase;

final class CandleQueryTest extends TestCase
{
    use RefreshDatabase;

    private FakeTimeSeries $series;

    private ArrayHotCache $hot;

    private Repository $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->series = new FakeTimeSeries;
        $this->hot = new ArrayHotCache;
        $this->cache = new Repository(new ArrayStore);
    }

    private function candleQuery(): CandleQuery
    {
        return new CandleQuery(
            $this->series,
            $this->hot,
            $this->app->make(SpreadQuery::class),
            $this->cache,
        );
    }

    private function request(
        Interval $interval = Interval::M5,
        Side $side = Side::Last,
        Instrument $instrument = Instrument::Gold18Gram,
    ): CandleRequestDto {
        return CandleRequestDto::make('tgju', $instrument, $interval, 1_791_100_000, 1_791_104_500, $side);
    }

    /** @return array<int, CandleDto> */
    private function candles(): array
    {
        return [
            new CandleDto(1_791_100_000_000, 266_400_000.0, 266_500_000.0, 266_300_000.0, 266_480_000.0, 4.0),
            new CandleDto(1_791_100_300_000, 266_480_000.0, 266_600_000.0, 266_450_000.0, 266_550_000.0, 5.0),
        ];
    }

    private function provider(string $key = 'tgju'): Provider
    {
        return Provider::create([
            'key' => $key, 'driver' => $key, 'name' => ucfirst($key),
            'name_fa' => 'آزمایشی', 'is_active' => true,
        ]);
    }

    // ── masir e asli ─────────────────────────────────────────────

    public function test_returns_candles(): void
    {
        $this->series->willReturn($this->candles());

        $result = $this->candleQuery()->candles($this->request());

        $this->assertSame(2, $result->count());
        $this->assertFalse($result->degraded);
    }

    public function test_empty_series_is_not_an_error(): void
    {
        $result = $this->candleQuery()->candles($this->request());

        $this->assertTrue($result->isEmpty());
        $this->assertFalse($result->degraded);
    }

    // ── cache e do tabaghe ───────────────────────────────────────

    public function test_second_call_is_served_from_cache(): void
    {
        $this->series->willReturn($this->candles());
        $query = $this->candleQuery();

        $query->candles($this->request());
        $this->series->willReturn([]);
        $result = $query->candles($this->request());

        $this->assertSame(2, $result->count(), 'bayad az cache biad، na az Influx.');
    }

    /**
     * 🔴 Influx ghat' -> akharin javab e salem.
     * Chart e «bayat vali DUROST» kheyli behtar az chart e KHALI-e: chart e
     * khali be nazar e karbar ya'ni site kharab ast.
     */
    public function test_falls_back_to_the_last_good_series_when_influx_dies(): void
    {
        $this->series->willReturn($this->candles());
        $query = $this->candleQuery();
        $query->candles($this->request());

        // Cache e taze mimire، vali `:last` 6 saat mimune.
        $this->cache->forget($this->request()->cacheKey(0));
        $this->series->breakIt();

        $result = $query->candles($this->request());

        $this->assertSame(2, $result->count());
        $this->assertTrue($result->degraded, 'bayad e\'lam kone ke data bayat ast.');
    }

    public function test_reports_degraded_with_no_fallback_available(): void
    {
        $this->series->breakIt();

        $result = $this->candleQuery()->candles($this->request());

        $this->assertTrue($result->isEmpty());
        $this->assertTrue($result->degraded);
    }

    /**
     * Javab e KHALI nabayad be onvan e «akharin salem» zakhire beshe —
     * vagarna yek bazeh ye khali ye movaghat 6 saat fallback mimune.
     */
    public function test_an_empty_result_is_not_remembered_as_last_good(): void
    {
        $query = $this->candleQuery();
        $query->candles($this->request());

        $this->assertNull($this->cache->get($this->request()->lastGoodCacheKey(0)));
    }

    /** TTL e tabaghe ye taze bayad ba andaze ye bar motanaseb bashe. */
    public function test_cache_ttl_matches_the_interval(): void
    {
        $this->series->willReturn($this->candles());

        foreach ([Interval::M1, Interval::D1] as $interval) {
            $this->candleQuery()->candles($this->request($interval));

            $this->assertNotNull(
                $this->cache->get($this->request($interval)->cacheKey(0)),
                "{$interval->value} bayad cache shode bashe.",
            );
        }
    }

    // ── spread ───────────────────────────────────────────────────

    public function test_applies_the_sell_spread_to_the_series(): void
    {
        $provider = $this->provider();
        ProviderSpread::create([
            'provider_id' => $provider->id,
            'symbol' => Instrument::Gold18Gram->value,
            'buy_mode' => 'percent', 'buy_value' => -1.0,
            'sell_mode' => 'percent', 'sell_value' => 2.0,
        ]);

        $this->series->willReturn($this->candles());

        $result = $this->candleQuery()->candles($this->request());

        // 266,400,000 +2% = 271,728,000
        $this->assertSame(271_728_000.0, $result->candles[0]->open);
    }

    /** Samt e `bid` bayad spread e KHARID begire، na foroosh. */
    public function test_bid_side_uses_the_buy_spread(): void
    {
        $provider = $this->provider();
        ProviderSpread::create([
            'provider_id' => $provider->id,
            'symbol' => Instrument::Gold18Gram->value,
            'buy_mode' => 'percent', 'buy_value' => -1.0,
            'sell_mode' => 'percent', 'sell_value' => 2.0,
        ]);

        $this->series->willReturn($this->candles());

        $result = $this->candleQuery()->candles($this->request(side: Side::Bid));

        // 266,400,000 -1% = 263,736,000
        $this->assertSame(263_736_000.0, $result->candles[0]->open);
    }

    public function test_no_spread_leaves_the_series_untouched(): void
    {
        $this->series->willReturn($this->candles());

        $result = $this->candleQuery()->candles($this->request());

        $this->assertSame(266_400_000.0, $result->candles[0]->open);
    }

    /**
     * 🔴 Kelid e cache bayad `spreadVersion` ro dashte bashe — vagarna ba'd
     * az taghir e spread، chart gheymat e ghadim mide dar hali ke tablo
     * jadid mide.
     */
    public function test_bumping_the_spread_version_invalidates_the_cache(): void
    {
        $this->series->willReturn($this->candles());
        $query = $this->candleQuery();
        $query->candles($this->request());

        $this->hot->bumpSpreadVersion();
        $this->series->willReturn([]);

        $this->assertTrue($query->candles($this->request())->isEmpty(), 'cache bayad batel shode bashe.');
    }

    // ── shekl e payload ──────────────────────────────────────────

    public function test_payload_tells_the_chart_how_to_format(): void
    {
        $this->series->willReturn($this->candles());

        $payload = $this->candleQuery()->candles($this->request())->toArray();

        $this->assertSame('IRR', $payload['quote']);
        $this->assertSame(0, $payload['precision']);
        $this->assertSame(10, $payload['displayDivisor']);
        $this->assertArrayHasKey('degraded', $payload);
    }

    public function test_usd_series_carries_its_own_precision(): void
    {
        $this->series->willReturn($this->candles());

        $payload = $this->candleQuery()
            ->candles($this->request(instrument: Instrument::GoldOunce))
            ->toArray();

        $this->assertSame('USD', $payload['quote']);
        $this->assertSame(2, $payload['precision']);
        $this->assertSame(1, $payload['displayDivisor']);
    }

    public function test_etag_changes_when_the_series_moves(): void
    {
        $this->series->willReturn($this->candles());
        $first = $this->candleQuery()->candles($this->request())->etag();

        $this->series->willReturn([
            new CandleDto(1_791_200_000_000, 1.0, 1.0, 1.0, 1.0, 1.0),
        ]);
        $this->hot->bumpSpreadVersion();

        $this->assertNotSame($first, $this->candleQuery()->candles($this->request())->etag());
    }
}
