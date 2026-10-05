<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Application\Dto\ProviderConfigDto;
use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Application\Dto\TickReportDto;
use App\Modules\Pricing\Application\Pipeline\Stages\EnrichStage;
use App\Modules\Pricing\Application\Pipeline\Stages\PersistStage;
use App\Modules\Pricing\Application\Pipeline\Stages\PublishStage;
use App\Modules\Pricing\Application\Pipeline\Stages\ValidateStage;
use App\Modules\Pricing\Application\Pipeline\TickContext;
use App\Modules\Pricing\Application\Pipeline\TickPipeline;
use App\Modules\Pricing\Domain\Enums\Direction;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\ArrayHotCache;
use Tests\Support\Fakes\FakeQuoteSource;
use Tests\Support\Fakes\FakeTimeSeries;
use Tests\Support\Fakes\FrozenClock;
use Tests\Support\Fakes\InMemorySnapshotStore;

/**
 * 🔴 Kol e pipeline — BEDUN e Docker, DB, Redis ya Influx.
 *
 * Hamin chizi-e ke marz e `Domain`/`Infrastructure` barash sakhte shod. Agar
 * yek rooz baraye test kardan e yek mantegh e tick majbur shodi container
 * bala biari, ya'ni un mantegh tu laye ye ghalat neshaste.
 */
final class TickPipelineTest extends TestCase
{
    private FrozenClock $clock;

    private InMemorySnapshotStore $store;

    private ArrayHotCache $cache;

    private FakeTimeSeries $series;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new FrozenClock('2026-10-04T12:21:30Z');
        $this->store = new InMemorySnapshotStore;
        $this->cache = new ArrayHotCache;
        $this->series = new FakeTimeSeries;
    }

    private function quote(
        int $minor = 266_400_000,
        Instrument $instrument = Instrument::Gold18Gram,
        string $provider = 'tgju',
        ?int $ageSeconds = 12,
        Side $side = Side::Last,
    ): RawQuoteDto {
        return new RawQuoteDto(
            providerKey: $provider,
            instrument: $instrument,
            side: $side,
            price: new Price($minor, $instrument->quote()),
            upstreamAt: $ageSeconds === null ? null : $this->clock->now()->subSeconds($ageSeconds),
        );
    }

    /** @param array<int, RawQuoteDto> $quotes */
    private function tick(array $quotes, ?TickContext $context = null): TickReportDto
    {
        $source = (new FakeQuoteSource('tgju'))->returns($quotes);

        $context ??= new TickContext(
            at: $this->clock->tick(),
            sources: [$source],
            providers: ['tgju' => new ProviderConfigDto('tgju')],
        );

        // `FetchStage` amdan inja nist: `Http::pool` facade mikhad va in
        // test bayad bedun e Laravel ejra beshe. Jaye un، gheymat ha
        // mostaghim gozashte mishan — baghi ye pipeline eyn e vaghe'iat ast.
        $context->raw = $quotes;

        return (new TickPipeline($this->store, [
            new ValidateStage,
            new EnrichStage,
            new PersistStage($this->cache, $this->store, $this->series),
            new PublishStage($this->cache),
        ]))->run($context);
    }

    // ── masir e salem ────────────────────────────────────────────

    public function test_a_clean_tick_reaches_every_sink(): void
    {
        $report = $this->tick([$this->quote()]);

        self::assertSame(1, $report->accepted);
        self::assertSame(0, $report->rejectedCount());

        self::assertCount(1, $this->store->all());
        self::assertCount(1, $this->cache->board());
        self::assertSame(1, $this->series->writeCount());
    }

    public function test_sinks_are_reported_individually(): void
    {
        $report = $this->tick([$this->quote()]);

        foreach (['redis', 'postgres', 'influx'] as $sink) {
            self::assertArrayHasKey($sink, $report->sinks);
            self::assertTrue($report->sinks[$sink]->succeeded, "{$sink} bayad movafagh bashe.");
        }
    }

    public function test_first_tick_has_no_direction_and_opens_the_day_band(): void
    {
        $this->tick([$this->quote(266_400_000)]);

        $snapshot = array_values($this->store->all())[0];

        self::assertSame(Direction::Flat, $snapshot->direction);
        self::assertSame(266_400_000, $snapshot->dayOpen->minor);
        self::assertSame(266_400_000, $snapshot->dayHigh->minor);
        self::assertSame(266_400_000, $snapshot->dayLow->minor);
        self::assertNull($snapshot->previous);
    }

    public function test_second_tick_computes_direction_and_widens_the_band(): void
    {
        $this->tick([$this->quote(266_400_000)]);
        $this->clock->advance(15);
        $this->tick([$this->quote(267_000_000)]);

        $snapshot = array_values($this->store->all())[0];

        self::assertSame(Direction::Up, $snapshot->direction);
        self::assertSame(266_400_000, $snapshot->dayOpen->minor);
        self::assertSame(267_000_000, $snapshot->dayHigh->minor);
        self::assertSame(266_400_000, $snapshot->dayLow->minor);
        self::assertSame(266_400_000, $snapshot->previous->minor);
    }

    /**
     * Gheymat e bi-taghir nabayad jahat ro be `flat` bargardune.
     *
     * Tu bazaar e aram aksar e tick ha yeksan-and؛ agar har bar `flat`
     * bezanim، arrow ha hamishe khamoosh-an — daghighan hamun chizi ke tu
     * projeh ye marja' ettefagh oftad.
     */
    public function test_unchanged_price_keeps_the_previous_direction(): void
    {
        $this->tick([$this->quote(266_400_000)]);
        $this->clock->advance(15);
        $this->tick([$this->quote(267_000_000)]);
        $this->clock->advance(15);
        $this->tick([$this->quote(267_000_000)]);

        self::assertSame(Direction::Up, array_values($this->store->all())[0]->direction);
    }

    /** Faghat taghir ha mirizan tu daftarche — na har tick. */
    public function test_only_real_changes_are_appended_to_the_ledger(): void
    {
        $this->tick([$this->quote(266_400_000)]);
        $this->clock->advance(15);
        $this->tick([$this->quote(266_400_000)]);
        $this->clock->advance(15);
        $this->tick([$this->quote(267_000_000)]);

        self::assertCount(2, $this->store->changes(), 'tick e bi-taghir nabayad sabt beshe.');
    }

    // ── darvaze ──────────────────────────────────────────────────

    public function test_rejects_a_stale_quote(): void
    {
        // `Gold18Gram` saghf e 900 saniye dare.
        $report = $this->tick([$this->quote(ageSeconds: 1_200)]);

        self::assertSame(0, $report->accepted);
        self::assertSame(1, $report->rejectedCount());
        self::assertSame('stale', $report->rejections[0]->category());
        self::assertSame(0, $this->store->count(), 'chiz e bayat nabayad zakhire beshe.');
    }

    /** `ts` e ayande = clock skew ya timestamp e kharab e upstream. */
    public function test_rejects_a_quote_from_the_future(): void
    {
        $report = $this->tick([$this->quote(ageSeconds: -600)]);

        self::assertSame('future', $report->rejections[0]->category());
    }

    public function test_rejects_an_implausible_value(): void
    {
        $report = $this->tick([$this->quote(4_140)]);

        self::assertSame('out_of_band', $report->rejections[0]->category());
    }

    /** 🔴 Hamun khata ye 10x e Toman/Rial. */
    public function test_rejects_a_ten_times_error_on_the_second_tick(): void
    {
        $this->tick([$this->quote(266_400_000)]);
        $this->clock->advance(15);
        $report = $this->tick([$this->quote(26_681_800)]);

        self::assertSame('deviation', $report->rejections[0]->category());
        // Gheymat e salem e ghabli bayad dast nakhorde bemune.
        self::assertSame(266_400_000, array_values($this->store->all())[0]->price->minor);
    }

    /**
     * Harekat e vaghei ye bazaar (devaluation) nabayad baraye hamishe rad
     * beshe: avvalin breach negah dashte mishe va tick e ba'di ta'eed esh
     * mikone.
     */
    public function test_a_confirmed_outlier_is_accepted_on_the_next_tick(): void
    {
        $this->tick([$this->quote(266_400_000)]);

        $this->clock->advance(15);
        $spike = $this->tick([$this->quote(320_000_000)]);
        self::assertSame(1, $spike->rejectedCount(), 'avvalin jahesh bayad rad beshe.');

        $this->clock->advance(15);
        $confirm = $this->tick([$this->quote(320_500_000)]);

        self::assertSame(1, $confirm->accepted, 'tick e dovom bayad ta'.'eed esh kone.');
        self::assertSame(320_500_000, array_values($this->store->all())[0]->price->minor);
    }

    /** Saghf e USD saft tar ast (5%) — bazaar e jahani aram tar harekat mikone. */
    public function test_usd_uses_a_tighter_deviation_ceiling(): void
    {
        $context = new TickContext(
            at: $this->clock->tick(),
            sources: [new FakeQuoteSource('tgju')],
            providers: ['tgju' => new ProviderConfigDto('tgju', symbolFreshness: ['xau-oz-usd' => 90_000])],
        );

        $this->tick([$this->quote(41_401_900, Instrument::GoldOunce)], $context);

        $this->clock->advance(15);
        $context2 = new TickContext(
            at: $this->clock->tick(),
            sources: [new FakeQuoteSource('tgju')],
            providers: ['tgju' => new ProviderConfigDto('tgju', symbolFreshness: ['xau-oz-usd' => 90_000])],
        );

        // +7% — baraye IRR ghabul mishod، baraye USD na.
        $report = $this->tick([$this->quote(44_300_000, Instrument::GoldOunce)], $context2);

        self::assertSame('deviation', $report->rejections[0]->category());
    }

    /**
     * 🔴 `ons` e tgju faghat RUZANE be-rooz mishe. Bedun e override e sath e
     * provider، default e 180s har tick radesh mikone.
     */
    public function test_provider_can_loosen_freshness_for_a_daily_symbol(): void
    {
        $strict = new TickContext(
            at: $this->clock->tick(),
            sources: [new FakeQuoteSource('tgju')],
            providers: ['tgju' => new ProviderConfigDto('tgju')],
        );

        $report = $this->tick([$this->quote(41_401_900, Instrument::GoldOunce, ageSeconds: 3_600)], $strict);
        self::assertSame(1, $report->rejectedCount(), 'bedun e override bayad rad beshe.');

        $loose = new TickContext(
            at: $this->clock->tick(),
            sources: [new FakeQuoteSource('tgju')],
            providers: ['tgju' => new ProviderConfigDto('tgju', symbolFreshness: ['xau-oz-usd' => 90_000])],
        );

        $report = $this->tick([$this->quote(41_401_900, Instrument::GoldOunce, ageSeconds: 3_600)], $loose);
        self::assertSame(1, $report->accepted, 'ba override bayad ghabul beshe.');
    }

    // ── taab-avari ───────────────────────────────────────────────

    /** Influx ghat' + tablo salem = halat e SALEM, na shekast. */
    public function test_influx_outage_does_not_stop_the_board(): void
    {
        $this->series->breakIt();

        $report = $this->tick([$this->quote()]);

        self::assertSame(1, $report->accepted);
        self::assertCount(1, $this->cache->board(), 'tablo bayad zende bemune.');
        self::assertCount(1, $this->store->all(), 'Postgres bayad neveshte bashe.');
        self::assertFalse($report->sinks['influx']->succeeded);
        self::assertTrue($report->sinks['redis']->succeeded);
    }

    /** Redis ghat' = tablo az Postgres khunde mishe. */
    public function test_redis_outage_does_not_stop_persistence(): void
    {
        $this->cache->breakIt();

        $report = $this->tick([$this->quote()]);

        self::assertSame(1, $report->accepted);
        self::assertCount(1, $this->store->all());
        self::assertSame(1, $this->series->writeCount());
        self::assertFalse($report->sinks['redis']->succeeded);
    }

    /** Postgres ghat' = tablo hanuz zende ast (Redis ghablan neveshte shode). */
    public function test_postgres_outage_still_leaves_a_live_board(): void
    {
        $this->tick([$this->quote()]);
        $this->store->breakIt();
        $this->clock->advance(15);

        $report = $this->tick([$this->quote(267_000_000)]);

        // `LoadPrevious` critical ast — bedun e gheymat e ghabli، mohafez ha
        // khamoosh mishan، pas tick amdan motevaghef mishe.
        self::assertTrue($report->skipped);
        self::assertStringContainsString('snapshot', (string) $report->skipReason);
    }

    public function test_all_sinks_down_does_not_throw(): void
    {
        $this->cache->breakIt();
        $this->series->breakIt();

        $report = $this->tick([$this->quote()]);

        self::assertSame(1, $report->accepted);
        self::assertSame(2, $report->failedSinks());
    }

    // ── 🔴 Shekast e NIME: write miterke، read salem ─────────────

    /**
     * 🔴 OOM e Redis — bad tarin nou' e ghat'i، chun hame chiz SALEM be nazar
     * mirese.
     *
     * `redis-prices` ba `--maxmemory 64mb --maxmemory-policy noeviction`
     * ejra mishe. Vaghti por she، Redis write ha ro ba
     * `OOM command not allowed` rad mikone **vali read ha ro kamel javab
     * mide** va `PING` ham salem ast.
     *
     * Natije: tablo YAKH mizane ru akharin chizi ke neveshte shode.
     * `isAvailable()` `true` mide، `board()` data mide، `BoardQuery`
     * `degraded=false` e'lam mikone — pas masir e fallback e Postgres
     * HICHVAGHT roshan nemishe. Tanha chizi ke dad mizane `ageSeconds` e
     * radif hast ke bala mire va `isStale` mikone.
     *
     * In test un raftar ro MIKHKUB mikone: tick nabayad beterke، Postgres
     * bayad data ye taze ro begire، va sink e redis bayad SHEKAST e'lam kone
     * ta `TickMonitor` betune neshun esh bede.
     */
    public function test_a_redis_oom_freezes_the_board_but_keeps_postgres_current(): void
    {
        // Tick e avval salem — tablo yek gheymat dare.
        $this->tick([$this->quote(266_400_000)]);
        self::assertSame(266_400_000, $this->cache->board()['tgju:xau-18k-g-irr:last']->price->minor);

        // Alan Redis por mishe: write rad mishe، read salem.
        $this->cache->failWritesOnly();
        $this->clock->advance(15);

        $report = $this->tick([$this->quote(267_000_000)]);

        self::assertSame(1, $report->accepted, 'tick nabayad beterke.');
        self::assertFalse($report->sinks['redis']->succeeded, 'bayad shekast e sarih e\'lam she.');
        self::assertStringContainsString('OOM', (string) $report->sinks['redis']->error);

        // Postgres taze-st…
        self::assertSame(267_000_000, $this->store->all()['tgju:xau-18k-g-irr:last']->price->minor);
        self::assertTrue($report->sinks['postgres']->succeeded);

        // …vali tablo ye dagh YAKH zade ru gheymat e ghadim. Read kar mikone،
        // pas hich fallback-i roshan nemishe.
        self::assertSame(266_400_000, $this->cache->board()['tgju:xau-18k-g-irr:last']->price->minor);
        self::assertTrue($this->cache->isAvailable(), 'Redis khodesh ro salem gozaresh mide.');
    }

    /**
     * Influx mitune yek write ro ba 422 rad kone (timestamp birun e
     * retention، type mismatch e field) dar hali ke query ha salem-and — tu
     * `P1` hamin ro zende didim.
     */
    public function test_an_influx_write_rejection_only_fails_that_sink(): void
    {
        $this->series->failWritesOnly();

        $report = $this->tick([$this->quote()]);

        self::assertSame(1, $report->accepted);
        self::assertFalse($report->sinks['influx']->succeeded);
        self::assertStringContainsString('422', (string) $report->sinks['influx']->error);

        // Query salem munde — `isAvailable()` hanuz `true` ast، pas
        // `CandleQuery` be tabaghe ye `:last` fallback NEMIKONE. Chart
        // chizi ke ghablan neveshte shode ro mide، na khata.
        self::assertTrue($this->series->isAvailable());

        // Do sink e digar dast nakhordan.
        self::assertTrue($report->sinks['redis']->succeeded);
        self::assertTrue($report->sinks['postgres']->succeeded);
        self::assertSame(1, $report->failedSinks());
    }

    public function test_the_board_recovers_as_soon_as_redis_accepts_writes_again(): void
    {
        $this->tick([$this->quote(266_400_000)]);

        $this->cache->failWritesOnly();
        $this->clock->advance(15);
        $this->tick([$this->quote(267_000_000)]);

        // Memory azad shod.
        $this->cache->repair();
        $this->clock->advance(15);
        $report = $this->tick([$this->quote(268_000_000)]);

        // Hich kar e dasti lazem nist: tick e ba'di kol e kelid e tablo ro
        // BAZNEVISI mikone، pas yakh-zadegi khod-be-khod bar-taraf mishe.
        self::assertTrue($report->sinks['redis']->succeeded);
        self::assertSame(268_000_000, $this->cache->board()['tgju:xau-18k-g-irr:last']->price->minor);
    }

    // ── chand provider ───────────────────────────────────────────

    public function test_two_providers_quote_the_same_symbol_independently(): void
    {
        $context = new TickContext(
            at: $this->clock->tick(),
            sources: [new FakeQuoteSource('tgju'), new FakeQuoteSource('estjt')],
            providers: [
                'tgju' => new ProviderConfigDto('tgju'),
                'estjt' => new ProviderConfigDto('estjt'),
            ],
        );

        $report = $this->tick([
            $this->quote(266_400_000, provider: 'tgju'),
            $this->quote(266_818_000, provider: 'estjt'),
        ], $context);

        self::assertSame(2, $report->accepted);
        self::assertCount(2, $this->store->all());
        self::assertArrayHasKey('tgju:xau-18k-g-irr:last', $this->store->all());
        self::assertArrayHasKey('estjt:xau-18k-g-irr:last', $this->store->all());
    }

    /** Gheymat e kharab e yek provider nabayad provider e digar ro rad kone. */
    public function test_one_bad_provider_does_not_reject_the_other(): void
    {
        $context = new TickContext(
            at: $this->clock->tick(),
            sources: [new FakeQuoteSource('tgju'), new FakeQuoteSource('estjt')],
            providers: [
                'tgju' => new ProviderConfigDto('tgju'),
                'estjt' => new ProviderConfigDto('estjt'),
            ],
        );

        $report = $this->tick([
            $this->quote(266_400_000, provider: 'tgju'),
            $this->quote(4_140, provider: 'estjt'),
        ], $context);

        self::assertSame(1, $report->accepted);
        self::assertSame(1, $report->rejectedCount());
        self::assertSame('estjt', $report->rejections[0]->providerKey);
    }

    public function test_bid_and_ask_are_stored_separately(): void
    {
        $report = $this->tick([
            $this->quote(263_175_000, Instrument::GoldMeltedGram, side: Side::Bid),
            $this->quote(264_921_000, Instrument::GoldMeltedGram, side: Side::Ask),
        ]);

        self::assertSame(2, $report->accepted);
        self::assertCount(2, $this->store->all());
    }

    public function test_an_empty_tick_writes_nothing(): void
    {
        $report = $this->tick([]);

        self::assertSame(0, $report->accepted);
        self::assertSame(0, $this->store->count());
        self::assertSame(0, $this->series->writeCount());
    }

    /** Hame ye point haye yek tick hamun timestamp e floor-shode ro migiran. */
    public function test_every_sink_sees_the_same_floored_tick_time(): void
    {
        $clock = new FrozenClock('2026-10-04T12:21:37Z');
        $this->clock = $clock;

        $this->tick([$this->quote(), $this->quote(instrument: Instrument::CoinEmami, minor: 2_753_400_000)]);

        $timestamps = $this->series->writtenTimestamps();

        self::assertCount(1, array_unique($timestamps));
        self::assertSame(0, $timestamps[0] % 15);
    }
}
