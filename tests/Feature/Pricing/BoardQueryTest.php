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

    private function boardQuery(int $freezeAfterSeconds = 120): BoardQuery
    {
        return new BoardQuery(
            $this->cache,
            $this->app->make(SnapshotStorePort::class),
            $this->app->make(SpreadQuery::class),
            $this->clock,
            $freezeAfterSeconds,
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
    /**
     * Neveshtan e tablo ye dagh HAMUN tori ke pipeline mineviseh.
     *
     * 🔴 Zarabān ham lazem ast: `BoardQuery` tablo-i ke zarabān esh ghadimi
     * (ya nabud) bashe ro GHABL E E'TEMAD nemidune va be Postgres fallback
     * mikone. Un check hamun chizi-e ke yakh-zadegi ye saket e OOM ro kashf
     * mikone (`P1-27`)، pas test ham bayad mesl e `PublishStage` raftar
     * kone.
     *
     * @param  array<int, SnapshotDto>  $snapshots
     */
    private function warmBoard(array $snapshots): void
    {
        $this->cache->putBoard($snapshots);
        $this->cache->putHeartbeat($this->clock->now()->timestamp);
    }

    public function test_a_deactivated_provider_leaves_both_the_hot_and_the_fallback_board(): void
    {
        $provider = $this->provider();
        $store = $this->app->make(SnapshotStorePort::class);

        $store->save([$this->snapshot()]);
        $this->warmBoard([$this->snapshot()]);

        $this->assertCount(1, $this->boardQuery()->board()->rows);

        (new ProviderAction)->deactivate($provider);

        // Masir e dagh: tick e ba'di kelid ro bazneveshte mikone (provider
        // dige poll nemishe) — inja simulate mishe.
        $this->warmBoard([]);
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
        $this->warmBoard([$this->snapshot()]);

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

        $this->warmBoard([$this->snapshot()]);
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
        $this->warmBoard([$this->snapshot()]);

        $row = $this->boardQuery()->board()->rows[0];

        $this->assertSame(266_400_000, $row->raw->minor, 'kham bayad dast nakhorde bashe.');
        $this->assertSame(263_736_000, $row->buy->minor);
        $this->assertSame(271_728_000, $row->sell->minor);
    }

    public function test_falls_back_to_the_wildcard_spread(): void
    {
        $provider = $this->provider();
        $this->spread($provider, ProviderSpread::WILDCARD, 0.0, 1.0);
        $this->warmBoard([$this->snapshot()]);

        $this->assertSame(269_064_000, $this->boardQuery()->board()->rows[0]->sell->minor);
    }

    /** Spread e daghigh tar bar wildcard barande mishe. */
    public function test_symbol_spread_beats_the_wildcard(): void
    {
        $provider = $this->provider();
        $this->spread($provider, ProviderSpread::WILDCARD, 0.0, 10.0);
        $this->spread($provider, Instrument::Gold18Gram->value, 0.0, 1.0);
        $this->warmBoard([$this->snapshot()]);

        $this->assertSame(269_064_000, $this->boardQuery()->board()->rows[0]->sell->minor);
    }

    /** Invariant: spread e darsadi ru darsad e taghir neutral ast. */
    public function test_spread_never_touches_the_change_percent(): void
    {
        $provider = $this->provider();
        $this->spread($provider, Instrument::Gold18Gram->value, -5.0, 5.0);
        $this->warmBoard([$this->snapshot()]);

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
        $this->warmBoard([$this->snapshot()]);

        $this->assertTrue($this->boardQuery()->board()->rows[0]->isDerived);
    }

    /** Platform nerkh e VAGHEI mide — nabayad barchasb bokhore. */
    public function test_platform_provider_rows_are_not_derived(): void
    {
        $this->provider('wallgold', 'platform');
        $this->warmBoard([$this->snapshot('wallgold')]);

        $this->assertFalse($this->boardQuery()->board()->rows[0]->isDerived);
    }

    /** Abshode jofte NATIVE dare — hatta az yek provider e reference. */
    public function test_native_bid_ask_is_never_marked_derived(): void
    {
        $this->provider('tgju', 'reference');
        $this->warmBoard([
            $this->snapshot('tgju', 263_175_000, Instrument::GoldMeltedGram, Side::Bid),
        ]);

        $this->assertFalse($this->boardQuery()->board()->rows[0]->isDerived);
    }

    // ── tazegi ───────────────────────────────────────────────────

    public function test_marks_a_stale_row(): void
    {
        $this->provider();
        // `Gold18Gram` saghf e 900s dare.
        $this->warmBoard([$this->snapshot(ageSeconds: 1_200)]);

        $row = $this->boardQuery()->board()->rows[0];

        $this->assertTrue($row->isStale);
        $this->assertSame(1_200, $row->ageSeconds);
    }

    /** Override e provider bayad ru tazegi ye tablo ham e'mal beshe. */
    public function test_provider_freshness_override_applies_to_the_board(): void
    {
        $this->provider();
        $this->warmBoard([$this->snapshot(ageSeconds: 3_600, instrument: Instrument::GoldOunce)]);

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

        $this->warmBoard([
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
        $this->warmBoard([$this->snapshot()]);
        $first = $this->boardQuery()->board()->etag();

        $this->warmBoard([$this->snapshot(minor: 267_000_000)]);

        $this->assertNotSame($first, $this->boardQuery()->board()->etag());
    }

    public function test_etag_changes_with_the_spread_version(): void
    {
        $this->provider();
        $this->warmBoard([$this->snapshot()]);
        $first = $this->boardQuery()->board()->etag();

        $this->cache->bumpSpreadVersion();

        $this->assertNotSame($first, $this->boardQuery()->board()->etag());
    }

    /** Payload bayad `scale` ro hamrah dashte bashe — frontend lookup nakone. */
    public function test_payload_carries_everything_the_frontend_needs(): void
    {
        $this->provider();
        $this->warmBoard([$this->snapshot(minor: 41_401_900, instrument: Instrument::GoldOunce)]);

        $row = $this->boardQuery()->board()->rows[0]->toArray();

        $this->assertSame('USD', $row['quote']);
        $this->assertSame(4, $row['scale']);
        $this->assertSame(1, $row['displayDivisor']);
        $this->assertSame(2, $row['precision']);
        $this->assertArrayHasKey('isDerived', $row);
        $this->assertArrayHasKey('ageSeconds', $row);
    }

    /** Rial `displayDivisor` e 1 dare — yek formatter، na tabdil tu storage. */
    public function test_rial_rows_expose_the_rial_divisor(): void
    {
        $this->provider();
        $this->warmBoard([$this->snapshot()]);

        $this->assertSame(1, $this->boardQuery()->board()->rows[0]->toArray()['displayDivisor']);
    }

    // ── 🔴 `hide_after_seconds` — P1-26 ─────────────────────────────

    /** @param array<string, mixed> $overrides */
    private function config(array $overrides = []): array
    {
        return ['tgju' => new ProviderConfigDto(
            key: 'tgju',
            freshnessSeconds: (int) ($overrides['freshness'] ?? 900),
            symbolFreshness: (array) ($overrides['symbolFreshness'] ?? []),
            hideAfterSeconds: (int) ($overrides['hideAfter'] ?? 600),
        )];
    }

    /**
     * 🔴 `hide_after_seconds` NESBI ast، na motlagh.
     *
     * Default haye jadval MA'KUS-and (`freshness = 900`، `hide_after = 600`).
     * Ba ma'ni ye MOTLAGH، radif tu 600s makhfi mishod GHABL az inke tu 900s
     * «bayat» e'lam she — ya'ni badge e bayat HICHVAGHT dide nemishod.
     */
    public function test_a_row_is_marked_stale_before_it_is_hidden(): void
    {
        $this->provider();

        // `xau-18k-g-irr` freshness esh 900s ast. Ba `hideAfter=600`:
        // bayat ba'd az 900s، makhfi ba'd az 1500s. 1200 vasat e un do-st.
        $this->warmBoard([$this->snapshot(ageSeconds: 1_200)]);

        $rows = $this->boardQuery()->board($this->config())->rows;

        self::assertCount(1, $rows, 'hanuz nabayad makhfi she.');
        self::assertTrue($rows[0]->isStale, 'bayad badge e bayat bokhore.');
    }

    public function test_a_row_older_than_freshness_plus_hide_after_is_dropped(): void
    {
        $this->provider();
        // 900 (freshness) + 600 (hideAfter) = 1500.
        $this->warmBoard([$this->snapshot(ageSeconds: 2_000)]);

        self::assertCount(0, $this->boardQuery()->board($this->config())->rows);
    }

    /**
     * 🔴 Dalil e dovom baraye NESBI budan: tazegi PER-INSTRUMENT ast.
     *
     * `xau-oz-usd` e tgju 90,000 saniye tazegi dare (un feed faghat RUZANE
     * be-rooz mishe). Yek saghf e MOTLAGH e 600 saniye ye sath e provider،
     * un symbol ro BARAYE HAMISHE makhfi mikard.
     */
    public function test_a_daily_symbol_is_not_hidden_by_a_short_hide_after(): void
    {
        $this->provider();
        $this->warmBoard([$this->snapshot(
            minor: 41_508_700,
            instrument: Instrument::GoldOunce,
            ageSeconds: 50_000,
        )]);

        $rows = $this->boardQuery()->board($this->config([
            'symbolFreshness' => [Instrument::GoldOunce->value => 90_000],
            'hideAfter' => 600,
        ]))->rows;

        self::assertCount(1, $rows, 'symbol e ruzane nabayad ba saghf e 600s makhfi she.');
        self::assertFalse($rows[0]->isStale);
    }

    public function test_a_zero_hide_after_never_hides(): void
    {
        $this->provider();
        $this->warmBoard([$this->snapshot(ageSeconds: 10_000_000)]);

        self::assertCount(1, $this->boardQuery()->board($this->config(['hideAfter' => 0]))->rows);
    }

    /**
     * ⚠️ Bedun e config e provider hazf NEMIKONIM: nadunestan e tanzim
     * nabayad be hazf e saket e radif ha tarjome beshe.
     */
    public function test_without_provider_config_nothing_is_hidden(): void
    {
        $this->provider();
        $this->warmBoard([$this->snapshot(ageSeconds: 10_000_000)]);

        self::assertCount(1, $this->boardQuery()->board()->rows);
    }

    // ── 🔴 Yakh-zadegi ye SAKET e tablo — P1-27 ─────────────────────

    /**
     * 🔴 Hamun halat e OOM: write ha rad mishan، read ha salem.
     *
     * `redis-prices` ba `--maxmemory-policy noeviction` vaghti por she
     * `OOM command not allowed` mide ru write va read ha ro KAMEL javab
     * mide va `PING` ham salem ast. Pas `isAvailable()` `true` bud،
     * `board()` data mid، va `degraded=false` e'lam mishod — masir e
     * Postgres HICHVAGHT roshan nemishod، dar hali ke Postgres data ye TAZE
     * dasht.
     *
     * Zarabān khodesh yek WRITE ast، pas tu OOM be-rooz nemishe — va hamin
     * un yakh-zadegi ro ghabl e kashf mikone.
     */
    public function test_a_frozen_hot_board_falls_back_to_postgres(): void
    {
        $this->provider();

        // Tablo ye dagh gheymat e GHADIM ro dare va zarabān esh kohne-ast.
        $this->cache->putBoard([$this->snapshot(minor: 266_400_000)]);
        $this->cache->putHeartbeat($this->clock->now()->subMinutes(10)->timestamp);

        // Postgres gheymat e TAZE ro dare.
        $this->app->make(SnapshotStorePort::class)->save([$this->snapshot(minor: 300_000_000)]);

        $board = $this->boardQuery()->board();

        self::assertTrue($board->degraded, 'bayad degraded e\'lam she.');
        self::assertSame(300_000_000, $board->rows[0]->raw->minor, 'bayad data ye TAZE bede.');
    }

    public function test_a_fresh_heartbeat_keeps_the_hot_path(): void
    {
        $this->provider();
        $this->warmBoard([$this->snapshot(minor: 266_400_000)]);
        $this->app->make(SnapshotStorePort::class)->save([$this->snapshot(minor: 300_000_000)]);

        $board = $this->boardQuery()->board();

        self::assertFalse($board->degraded);
        self::assertSame(266_400_000, $board->rows[0]->raw->minor, 'bayad az masir e dagh biad.');
    }

    public function test_a_missing_heartbeat_is_treated_as_frozen(): void
    {
        $this->provider();
        $this->cache->putBoard([$this->snapshot(minor: 266_400_000)]);
        $this->app->make(SnapshotStorePort::class)->save([$this->snapshot(minor: 300_000_000)]);

        $board = $this->boardQuery()->board();

        self::assertTrue($board->degraded);
        self::assertSame(300_000_000, $board->rows[0]->raw->minor);
    }

    public function test_the_freeze_check_can_be_disabled(): void
    {
        $this->provider();
        $this->cache->putBoard([$this->snapshot(minor: 266_400_000)]);
        $this->app->make(SnapshotStorePort::class)->save([$this->snapshot(minor: 300_000_000)]);

        $board = $this->boardQuery(freezeAfterSeconds: 0)->board();

        self::assertFalse($board->degraded);
        self::assertSame(266_400_000, $board->rows[0]->raw->minor);
    }

    /**
     * ⚠️ «Hame ye radif ha bayat-an» ba «hich kas nemineviseh» YEKI NIST.
     *
     * Shab ha (bazaar e baste) hame ye radif ha be-haghghe ghadimi-and vali
     * worker hanuz tick mizane va zarabān ro be-rooz mikone. Nabayad false
     * alarm bede.
     */
    public function test_an_all_stale_board_with_a_live_writer_is_not_degraded(): void
    {
        $this->provider();
        $this->warmBoard([$this->snapshot(ageSeconds: 1_200)]);

        $board = $this->boardQuery()->board($this->config());

        self::assertFalse($board->degraded, 'bazaar e baste yek ghat\'i nist.');
        self::assertCount(1, $board->rows);
        self::assertTrue($board->rows[0]->isStale);
    }
}
