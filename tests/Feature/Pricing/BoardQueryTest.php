<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Actions\ProviderAction;
use App\Modules\Pricing\Application\Dto\ProviderConfigDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Application\Queries\BoardQuery;
use App\Modules\Pricing\Application\Queries\SpreadQuery;
use App\Modules\Pricing\Domain\Enums\Direction;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\ArrayHotCache;
use Tests\Support\Fakes\FrozenClock;
use Tests\TestCase;

final class BoardQueryTest extends TestCase
{
    use RefreshDatabase;

    private ArrayHotCache $cache;

    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new ArrayHotCache;
        $this->clock = new FrozenClock('2026-10-05T10:00:00Z');
    }

    private function provider(string $key = 'tgju', string $kind = 'reference'): Provider
    {
        return Provider::create([
            'key' => $key,
            'driver' => $key,
            'kind' => $kind,
            'name' => ucfirst($key),
            'name_fa' => 'آزمایشی',
            'is_active' => true,
        ]);
    }

    private function snapshot(
        string $provider = 'tgju',
        int $minor = 266_400_000,
        Instrument $instrument = Instrument::Gold18Gram,
        Side $side = Side::Last,
        int $ageSeconds = 0,
    ): SnapshotDto {
        $quotedAt = $this->clock->now()->subSeconds($ageSeconds);

        return new SnapshotDto(
            providerKey: $provider,
            instrument: $instrument,
            side: $side,
            price: new Price($minor, $instrument->quote()),
            quotedAt: $quotedAt,
            dayOpen: new Price($minor - 1_000_000, $instrument->quote()),
            dayHigh: new Price($minor + 2_000_000, $instrument->quote()),
            dayLow: new Price($minor - 3_000_000, $instrument->quote()),
            changePercent: 1.58,
            direction: Direction::Up,
        );
    }

    private function boardQuery(): BoardQuery
    {
        return new BoardQuery(
            $this->cache,
            $this->app->make(SnapshotStorePort::class),
            $this->app->make(SpreadQuery::class),
            $this->clock,
        );
    }

    private function spread(Provider $provider, string $symbol, float $buy, float $sell): void
    {
        ProviderSpread::create([
            'provider_id' => $provider->id,
            'symbol' => $symbol,
            'buy_mode' => 'percent', 'buy_value' => $buy,
            'sell_mode' => 'percent', 'sell_value' => $sell,
        ]);
    }

    // ── 🔴 Provider e khamoosh: do masir bayad EYN e ham bashan ──

    /**
     * Masir e Redis (`putBoard`) kol e kelid ro bazneveshte mikone، pas
     * provider e khamoosh khod-be-khod az tablo mire. Vali `join` e kham tu
     * `EloquentSnapshotStore` scope e `is_active`/SoftDeletes ro NEMIBINE —
     * pas bedun e filter e sarih، masir e FALLBACK hamun provider ro bar
     * migardund.
     *
     * Natije: do masiri ke bayad eyn e ham bashan، daghighan tu bad tarin
     * lahze (ghat'i ye Redis) vagera mishan — tablo provider haye khamoosh ro
     * neshun mide.
     */
    public function test_a_deactivated_provider_leaves_both_the_hot_and_the_fallback_board(): void
    {
        $provider = $this->provider();
        $store = $this->app->make(SnapshotStorePort::class);

        $store->save([$this->snapshot()]);
        $this->cache->putBoard([$this->snapshot()]);

        $this->assertCount(1, $this->boardQuery()->board()->rows);

        (new ProviderAction)->deactivate($provider);

        // Masir e dagh: tick e ba'di kelid ro bazneveshte mikone (provider
        // dige poll nemishe) — inja simulate mishe.
        $this->cache->putBoard([]);
        $this->cache->breakIt();

        // Masir e fallback bayad HAMUN javab ro bede: khali.
        $this->assertCount(0, $this->boardQuery()->board()->rows);
    }

    public function test_a_soft_deleted_provider_leaves_the_fallback_board(): void
    {
        $provider = $this->provider();
        $this->app->make(SnapshotStorePort::class)->save([$this->snapshot()]);

        (new ProviderAction)->delete($provider);

        $this->cache->breakIt();

        // `join` e kham scope e SoftDeletes ro e'mal nemikone — in radif
        // bedun e shart e `deleted_at` bar migasht.
        $this->assertCount(0, $this->boardQuery()->board()->rows);
    }

    public function test_reactivating_brings_the_rows_back_with_their_day_band(): void
    {
        $provider = $this->provider();
        $this->app->make(SnapshotStorePort::class)->save([$this->snapshot()]);

        $action = new ProviderAction;
        $action->deactivate($provider);
        $action->activate($provider->refresh());

        $this->cache->breakIt();
        $board = $this->boardQuery()->board();

        // `price_snapshots` amdan PAK NEMISHE: `dayOpen`/`direction` e un
        // rooz sar e jash mimunan.
        $this->assertCount(1, $board->rows);
        $this->assertSame(265_400_000, $board->rows[0]->dayOpen?->minor);
    }

    // ── masir e asli ─────────────────────────────────────────────

    public function test_empty_board_when_there_is_nothing(): void
    {
        $this->assertTrue($this->boardQuery()->board()->isEmpty());
    }

    public function test_reads_from_the_hot_store(): void
    {
        $this->provider();
        $this->cache->putBoard([$this->snapshot()]);

        $board = $this->boardQuery()->board();

        $this->assertCount(1, $board->rows);
        $this->assertFalse($board->degraded);
        $this->assertSame(266_400_000, $board->rows[0]->raw->minor);
    }

    /**
     * 🔴 Store e dagh ghat' (ya kelid ha evict shodan) -> Postgres.
     * Bedun e in، yek evict e saket e Redis tablo ro khali mikone.
     */
    public function test_falls_back_to_postgres_when_the_hot_store_is_down(): void
    {
        $this->provider();
        $this->app->make(SnapshotStorePort::class)->save([$this->snapshot()]);

        $this->cache->breakIt();
        $board = $this->boardQuery()->board();

        $this->assertCount(1, $board->rows);
        $this->assertTrue($board->degraded, 'bayad e\'lam kone ke az masir e kond umade.');
    }

    /** Hamun masir vaghti Redis salem ast vali kelid ha evict shodan. */
    public function test_falls_back_when_the_hot_store_is_silently_empty(): void
    {
        $this->provider();
        $this->app->make(SnapshotStorePort::class)->save([$this->snapshot()]);

        $this->cache->putBoard([$this->snapshot()]);
        $this->cache->evictEverything();

        $board = $this->boardQuery()->board();

        $this->assertCount(1, $board->rows);
        $this->assertTrue($board->degraded);
    }

    // ── spread ───────────────────────────────────────────────────

    public function test_applies_the_spread_on_read(): void
    {
        $provider = $this->provider();
        $this->spread($provider, Instrument::Gold18Gram->value, -1.0, 2.0);
        $this->cache->putBoard([$this->snapshot()]);

        $row = $this->boardQuery()->board()->rows[0];

        $this->assertSame(266_400_000, $row->raw->minor, 'kham bayad dast nakhorde bashe.');
        $this->assertSame(263_736_000, $row->buy->minor);
        $this->assertSame(271_728_000, $row->sell->minor);
    }

    public function test_falls_back_to_the_wildcard_spread(): void
    {
        $provider = $this->provider();
        $this->spread($provider, ProviderSpread::WILDCARD, 0.0, 1.0);
        $this->cache->putBoard([$this->snapshot()]);

        $this->assertSame(269_064_000, $this->boardQuery()->board()->rows[0]->sell->minor);
    }

    /** Spread e daghigh tar bar wildcard barande mishe. */
    public function test_symbol_spread_beats_the_wildcard(): void
    {
        $provider = $this->provider();
        $this->spread($provider, ProviderSpread::WILDCARD, 0.0, 10.0);
        $this->spread($provider, Instrument::Gold18Gram->value, 0.0, 1.0);
        $this->cache->putBoard([$this->snapshot()]);

        $this->assertSame(269_064_000, $this->boardQuery()->board()->rows[0]->sell->minor);
    }

    /** Invariant: spread e darsadi ru darsad e taghir neutral ast. */
    public function test_spread_never_touches_the_change_percent(): void
    {
        $provider = $this->provider();
        $this->spread($provider, Instrument::Gold18Gram->value, -5.0, 5.0);
        $this->cache->putBoard([$this->snapshot()]);

        $this->assertSame(1.58, $this->boardQuery()->board()->rows[0]->changePercent);
    }

    // ── is_derived ───────────────────────────────────────────────

    /**
     * 🔴 Provider e `reference` yek gheymat mide — kharid/foroosh
     * ARITHMETIC e ma-st va bayad barchasb bokhore.
     */
    public function test_reference_provider_rows_are_marked_derived(): void
    {
        $this->provider('tgju', 'reference');
        $this->cache->putBoard([$this->snapshot()]);

        $this->assertTrue($this->boardQuery()->board()->rows[0]->isDerived);
    }

    /** Platform nerkh e VAGHEI mide — nabayad barchasb bokhore. */
    public function test_platform_provider_rows_are_not_derived(): void
    {
        $this->provider('wallgold', 'platform');
        $this->cache->putBoard([$this->snapshot('wallgold')]);

        $this->assertFalse($this->boardQuery()->board()->rows[0]->isDerived);
    }

    /** Abshode jofte NATIVE dare — hatta az yek provider e reference. */
    public function test_native_bid_ask_is_never_marked_derived(): void
    {
        $this->provider('tgju', 'reference');
        $this->cache->putBoard([
            $this->snapshot('tgju', 263_175_000, Instrument::GoldMeltedGram, Side::Bid),
        ]);

        $this->assertFalse($this->boardQuery()->board()->rows[0]->isDerived);
    }

    // ── tazegi ───────────────────────────────────────────────────

    public function test_marks_a_stale_row(): void
    {
        $this->provider();
        // `Gold18Gram` saghf e 900s dare.
        $this->cache->putBoard([$this->snapshot(ageSeconds: 1_200)]);

        $row = $this->boardQuery()->board()->rows[0];

        $this->assertTrue($row->isStale);
        $this->assertSame(1_200, $row->ageSeconds);
    }

    /** Override e provider bayad ru tazegi ye tablo ham e'mal beshe. */
    public function test_provider_freshness_override_applies_to_the_board(): void
    {
        $this->provider();
        $this->cache->putBoard([$this->snapshot(ageSeconds: 3_600, instrument: Instrument::GoldOunce)]);

        $strict = $this->boardQuery()->board()->rows[0];
        $this->assertTrue($strict->isStale);

        $loose = $this->boardQuery()->board([
            'tgju' => new ProviderConfigDto('tgju', symbolFreshness: ['xau-oz-usd' => 90_000]),
        ])->rows[0];

        $this->assertFalse($loose->isStale);
    }

    // ── shekl ────────────────────────────────────────────────────

    public function test_groups_rows_by_symbol_for_the_comparison_table(): void
    {
        $this->provider('tgju');
        $this->provider('estjt');

        $this->cache->putBoard([
            $this->snapshot('tgju', 266_400_000),
            $this->snapshot('estjt', 266_818_000),
            $this->snapshot('tgju', 2_753_400_000, Instrument::CoinEmami),
        ]);

        $grouped = $this->boardQuery()->board()->bySymbol();

        $this->assertCount(2, $grouped);
        $this->assertCount(2, $grouped['xau-18k-g-irr']);
        $this->assertCount(1, $grouped['xau-coin-emami-irr']);
    }

    /** ETag: poll e bi-taghir bayad 304 bede, na kol e payload. */
    public function test_etag_changes_with_the_board_version(): void
    {
        $this->provider();
        $this->cache->putBoard([$this->snapshot()]);
        $first = $this->boardQuery()->board()->etag();

        $this->cache->putBoard([$this->snapshot(minor: 267_000_000)]);

        $this->assertNotSame($first, $this->boardQuery()->board()->etag());
    }

    public function test_etag_changes_with_the_spread_version(): void
    {
        $this->provider();
        $this->cache->putBoard([$this->snapshot()]);
        $first = $this->boardQuery()->board()->etag();

        $this->cache->bumpSpreadVersion();

        $this->assertNotSame($first, $this->boardQuery()->board()->etag());
    }

    /** Payload bayad `scale` ro hamrah dashte bashe — frontend lookup nakone. */
    public function test_payload_carries_everything_the_frontend_needs(): void
    {
        $this->provider();
        $this->cache->putBoard([$this->snapshot(minor: 41_401_900, instrument: Instrument::GoldOunce)]);

        $row = $this->boardQuery()->board()->rows[0]->toArray();

        $this->assertSame('USD', $row['quote']);
        $this->assertSame(4, $row['scale']);
        $this->assertSame(1, $row['displayDivisor']);
        $this->assertSame(2, $row['precision']);
        $this->assertArrayHasKey('isDerived', $row);
        $this->assertArrayHasKey('ageSeconds', $row);
    }

    /** Rial `displayDivisor` e 10 dare — yek formatter، na tabdil tu storage. */
    public function test_rial_rows_expose_the_toman_divisor(): void
    {
        $this->provider();
        $this->cache->putBoard([$this->snapshot()]);

        $this->assertSame(10, $this->boardQuery()->board()->rows[0]->toArray()['displayDivisor']);
    }
}
