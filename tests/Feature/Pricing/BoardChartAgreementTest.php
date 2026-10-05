<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Dto\CandleDto;
use App\Modules\Pricing\Application\Dto\CandleRequestDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Application\Queries\BoardQuery;
use App\Modules\Pricing\Application\Queries\CandleQuery;
use App\Modules\Pricing\Application\Queries\SpreadQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Interval;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpread;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\ArrayHotCache;
use Tests\Support\Fakes\FakeTimeSeries;
use Tests\Support\Fakes\FrozenClock;
use Tests\TestCase;

/**
 * 🔴 Ta'ahod e asli ye tasmim e «spread ru READ».
 *
 * Tablo va chart bayad baraye hamun spread DAGHIGHAN hamun adad ro bedan.
 * Agar `close` e akharin candle ba gheymat e tablo fargh kone، har karbar un
 * ro be onvan e bug gozaresh mikone — va haghsh ham hast.
 *
 * In test un ta'ahod ro negahbani mikone: do masir e KAMELAN jodagane
 * (`BoardQuery` va `CandleQuery`) ro ba yek spread ejra mikone va natije ro
 * moghayese mikone.
 */
final class BoardChartAgreementTest extends TestCase
{
    use RefreshDatabase;

    private const RAW_MINOR = 266_400_000;

    private ArrayHotCache $hot;

    private FakeTimeSeries $series;

    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hot = new ArrayHotCache;
        $this->series = new FakeTimeSeries;
        $this->clock = new FrozenClock('2026-10-05T10:00:00Z');

        Provider::create([
            'key' => 'tgju', 'driver' => 'tgju', 'kind' => 'reference',
            'name' => 'TGJU', 'name_fa' => 'تی‌جی‌جی‌یو', 'is_active' => true,
        ]);
    }

    private function withSpread(float $buy, float $sell, string $mode = 'percent'): void
    {
        ProviderSpread::create([
            'provider_id' => Provider::query()->where('key', 'tgju')->value('id'),
            'symbol' => Instrument::Gold18Gram->value,
            'buy_mode' => $mode, 'buy_value' => $buy,
            'sell_mode' => $mode, 'sell_value' => $sell,
        ]);
    }

    /**
     * Hamun gheymat e KHAM ham tu tablo mishine ham tu akharin candle.
     *
     * ⚠️ Esm esh `seedPrices` ast na `seed`: `TestCase` e Laravel khodesh
     * yek method e PUBLIC e `seed()` dare (va hamin tor `run()`, `query()`,
     * `artisan()`, `get()`, …). Yek helper e `private` ba un esm ha fatal
     * mide: «Access level must be public».
     */
    private function seedPrices(): void
    {
        $snapshot = new SnapshotDto(
            providerKey: 'tgju',
            instrument: Instrument::Gold18Gram,
            side: Side::Last,
            price: Price::irr(self::RAW_MINOR),
            quotedAt: $this->clock->now(),
        );

        $this->hot->putBoard([$snapshot]);
        $this->app->make(SnapshotStorePort::class)->save([$snapshot]);

        $this->series->willReturn([
            new CandleDto(
                1_791_100_000_000,
                (float) self::RAW_MINOR,
                (float) self::RAW_MINOR,
                (float) self::RAW_MINOR,
                (float) self::RAW_MINOR,
                4.0,
            ),
        ]);
    }

    private function boardSell(): int
    {
        $board = new BoardQuery(
            $this->hot,
            $this->app->make(SnapshotStorePort::class),
            $this->app->make(SpreadQuery::class),
            $this->clock,
        );

        return $board->board()->rows[0]->sell->minor;
    }

    private function chartClose(Side $side = Side::Last): float
    {
        $chart = new CandleQuery(
            $this->series,
            $this->hot,
            $this->app->make(SpreadQuery::class),
            new Repository(new ArrayStore),
        );

        $request = CandleRequestDto::make(
            'tgju',
            Instrument::Gold18Gram,
            Interval::M5,
            1_791_100_000,
            1_791_104_500,
            $side,
        );

        return $chart->candles($request)->candles[0]->close;
    }

    public function test_they_agree_with_no_spread(): void
    {
        $this->seedPrices();

        $this->assertSame((float) $this->boardSell(), $this->chartClose());
    }

    public function test_they_agree_with_a_percent_spread(): void
    {
        $this->withSpread(-1.0, 2.0);
        $this->seedPrices();

        $this->assertSame((float) $this->boardSell(), $this->chartClose());
    }

    public function test_they_agree_with_a_negative_spread(): void
    {
        $this->withSpread(-3.0, -1.5);
        $this->seedPrices();

        $this->assertSame((float) $this->boardSell(), $this->chartClose());
    }

    /** `amount` be scale e arz vabaste ast — jayi ke asun eshtebah mishe. */
    public function test_they_agree_with_an_amount_spread(): void
    {
        $this->withSpread(-50_000, 120_000, mode: 'amount');
        $this->seedPrices();

        $this->assertSame((float) $this->boardSell(), $this->chartClose());
    }

    /** Samt e kharid ham bayad ba soton e kharid e tablo jur dar biad. */
    public function test_the_bid_chart_agrees_with_the_board_buy_column(): void
    {
        $this->withSpread(-1.0, 2.0);
        $this->seedPrices();

        $board = new BoardQuery(
            $this->hot,
            $this->app->make(SnapshotStorePort::class),
            $this->app->make(SpreadQuery::class),
            $this->clock,
        );

        $this->assertSame(
            (float) $board->board()->rows[0]->buy->minor,
            $this->chartClose(Side::Bid),
        );
    }

    /**
     * Ba'd az taghir e spread، HAR DO bayad fori avaz shan.
     * Agar yeki az un do cache e bayat bede، hamun vagerayi ye klassik
     * ettefagh mioftad.
     */
    public function test_they_stay_in_step_after_a_spread_change(): void
    {
        $this->withSpread(0.0, 1.0);
        $this->seedPrices();

        $this->assertSame((float) $this->boardSell(), $this->chartClose());

        ProviderSpread::query()->update(['sell_value' => 5.0]);
        $this->hot->bumpSpreadVersion();

        $this->assertSame((float) $this->boardSell(), $this->chartClose());
        // ...va vaghe'an avaz shode bashe، na inke har do bayat bashan.
        $this->assertSame(279_720_000, $this->boardSell());
    }
}
