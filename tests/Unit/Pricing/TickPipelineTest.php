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

    // ── 🔴 Cross-provider baraye AVVALIN tick — P1-24 ───────────

    /** @param array<int, RawQuoteDto> $quotes */
    private function twoProviderTick(array $quotes): TickReportDto
    {
        return $this->tick($quotes, new TickContext(
            at: $this->clock->tick(),
            sources: [new FakeQuoteSource('tgju'), new FakeQuoteSource('wallgold')],
            providers: [
                'tgju' => new ProviderConfigDto('tgju'),
                'wallgold' => new ProviderConfigDto('wallgold'),
            ],
        ));
    }

    /**
     * 🔴 Rakhne-i ke in task mibande، az sar ta akhar.
     *
     * `wallgold` yek provider e TAZE-st ke gheymat ro TOMAN mide dar hali ke
     * config esh rial mige. Avvalin tick esh:
     *   - band e motlagh un ro NEMIGIRE (tavarrom e rial band ha ro onghadr
     *     pahn karde ke yek dahom ham dakhelesh ja mishe)
     *   - check e enheraf `previous` nadare
     * Pas bedun e in darvaze، un meghdar mineshine — va chun tick haye
     * ba'di ba hamun meghdar e ghalat SAZEGAR-and، hargez kashf nemishe.
     * Yek dorugh e DAEMI tu Influx.
     */
    public function test_a_new_provider_with_the_wrong_unit_is_rejected_on_its_first_tick(): void
    {
        // tgju ja-oftade-st.
        $this->tick([$this->quote(266_400_000, provider: 'tgju')]);
        $this->clock->advance(15);

        $report = $this->twoProviderTick([
            $this->quote(266_400_000, provider: 'tgju'),
            // Toman jaye rial — yek dahom.
            $this->quote(26_640_000, provider: 'wallgold'),
        ]);

        self::assertSame(1, $report->accepted);
        self::assertArrayNotHasKey('wallgold:xau-18k-g-irr:last', $this->store->all());

        $reasons = array_map(static fn ($r): string => $r->reason, $report->rejections);
        self::assertStringContainsString('cross_provider', implode(' ', $reasons));
    }

    public function test_a_new_provider_with_a_sane_price_is_accepted_on_its_first_tick(): void
    {
        $this->tick([$this->quote(266_400_000, provider: 'tgju')]);
        $this->clock->advance(15);

        $report = $this->twoProviderTick([
            $this->quote(266_400_000, provider: 'tgju'),
            // 0.26% ekhtelaf — hamun chizi ke ru data ye zende didim.
            $this->quote(265_710_000, provider: 'wallgold'),
        ]);

        self::assertSame(2, $report->accepted);
        self::assertArrayHasKey('wallgold:xau-18k-g-irr:last', $this->store->all());
    }

    /**
     * 🔴 Faghat AVVALIN tick.
     *
     * Ba'd az inke yek provider ja-oftad، moghayese ba baghi ye bazaar
     * **nabayad** ejra beshe. Vagarna yek provider e vaghei ke nerkh esh
     * modam az baghi dur mishe (maghaze ye geran، ya yek bazaar e digar)
     * ba'd az chand rooz kamelan khamoosh mishe. Un kar e check e enheraf e
     * tick-be-tick ast، na in.
     */
    public function test_an_established_provider_is_never_cross_checked(): void
    {
        // `wallgold` ja mioftad ba hamun gheymat e tgju.
        $this->twoProviderTick([
            $this->quote(266_400_000, provider: 'tgju'),
            $this->quote(266_400_000, provider: 'wallgold'),
        ]);

        /*
         * ⚠️ Bayad ba JAHESH haye kuchik dur beshe، na ba yek jahesh e
         * bozorg: har gam +8% ast (zir e saghf e enheraf e 10%) vali jam'
         * eshun ~26% mishe — ya'ni az saghf e cross-provider e 20% RAD
         * mikone.
         *
         * Avval in test ro ba yek jahesh e 7.7% neveshtam va **bi-ma'ni**
         * bud: un meghdar zir e 20% mimund، pas test ham vaghti
         * cross-provider ru HAR tick ejra mishod sabz mimund. Mutation check
         * un ro gereft.
         */
        foreach ([287_712_000, 310_728_960, 335_587_276] as $price) {
            $this->clock->advance(15);

            $report = $this->twoProviderTick([
                $this->quote(266_400_000, provider: 'tgju'),
                $this->quote($price, provider: 'wallgold'),
            ]);

            self::assertSame(2, $report->accepted, "gheymat e {$price} bayad ghabul mishod.");
        }

        // 26% balatar az tgju — vali chun ja-oftade-st، cross-provider
        // aslan ejra nemishe. Vagarna yek maghaze ye geran (ya yek bazaar e
        // digar) ba'd az chand rooz kamelan khamoosh mishe.
        self::assertSame(
            335_587_276,
            $this->store->all()['wallgold:xau-18k-g-irr:last']->price->minor,
        );
    }

    /**
     * ⚠️ Halat e bootstrap — mahdudiat e sadeghane ye in ravesh.
     *
     * Avvalin ejra ye system: hich gheymat e payedari nist، pas hich marja'-i
     * ham nist va check nemitune kari bokone. Be hamin dalil seeder provider
     * ha ro `is_active=false` shoru' mikone va skill e `add-price-provider`
     * mige yeki-yeki roshan kon va `TickMonitor` ro check kon.
     */
    public function test_the_very_first_provider_cannot_be_cross_checked(): void
    {
        $report = $this->tick([$this->quote(26_640_000, provider: 'tgju')]);

        self::assertSame(1, $report->accepted, 'bedun e marja'.', check nemitune kari bokone.');
    }

    /**
     * Do provider e TAZE tu yek tick: avvali marja' nadare، vali dovomi
     * mitune ba avvali ke hamin tick paziresh shod moghayese beshe.
     */
    public function test_within_one_tick_an_accepted_quote_becomes_a_reference(): void
    {
        $report = $this->twoProviderTick([
            $this->quote(266_400_000, provider: 'tgju'),
            $this->quote(26_640_000, provider: 'wallgold'),
        ]);

        self::assertSame(1, $report->accepted);
        self::assertArrayHasKey('tgju:xau-18k-g-irr:last', $this->store->all());
        self::assertArrayNotHasKey('wallgold:xau-18k-g-irr:last', $this->store->all());
    }

    /**
     * 🔴 Provider e khodesh marja' e khodesh nist.
     *
     * `xau-melted-g-irr` do radif dare (bid va ask). Moghayese ye bid e yek
     * provider ba ask e HAMUN provider hich chizi ro ta'eed nemikone — un
     * faghat spread e khodesh ast، va agar vahed esh ghalat bashe HAR DO
     * radif be hamun nesbat ghalat-and.
     */
    /**
     * 🔴 «Har ki AVVAL berese، marja' mishe» — labe ye tiz e in ravesh.
     *
     * In test un ro MIKHKUB mikone، na inke ta'eed esh kone: agar yek
     * provider e GHALAT tu halat e bootstrap ja bioftad (vaghti hich marja'-i
     * nist)، un vaght provider e DOROST e ba'di rad mishe — chun ba un
     * marja' e ghalat moghayese mishe.
     *
     * Jologiri AMALIATI-e، na code-i: seeder provider ha ro
     * `is_active=false` shoru' mikone va skill e `add-price-provider` mige
     * YEKI-YEKI roshan kon va `TickMonitor` ro check kon. Radd shodan ba
     * `cross_provider:` barchasb mikhore، pas tu panel ma'lum ast.
     *
     * (`P1-28` file shod: ta'in kardan e yek provider e «marja' e e'temad».)
     */
    public function test_whoever_arrives_first_becomes_the_reference(): void
    {
        // Bootstrap: `wallgold` ba vahed e GHALAT avval mirese. Hich marja'-i
        // nist، pas paziresh mishe.
        $first = $this->tick([$this->quote(26_640_000, provider: 'wallgold')]);
        self::assertSame(1, $first->accepted);

        $this->clock->advance(15);

        // Alan tgju ba gheymat e DOROST miad — va rad mishe.
        $second = $this->twoProviderTick([
            $this->quote(26_640_000, provider: 'wallgold'),
            $this->quote(266_400_000, provider: 'tgju'),
        ]);

        self::assertArrayNotHasKey('tgju:xau-18k-g-irr:last', $this->store->all());

        $reasons = implode(' ', array_map(static fn ($r): string => $r->reason, $second->rejections));
        self::assertStringContainsString('cross_provider', $reasons);
    }

    // ── 🔴 Tablo = akharin-ma'lum e HAME — P1-26 ────────────────

    /**
     * 🔴 Shab ha tablo nabayad KUCHIK beshe.
     *
     * Ghablan `putBoard` faghat `snapshots` ro minevesht — ya'ni faghat
     * symbol hayi ke HAMIN tick taze budan. Bazaar e Iran ke baste mishe،
     * `ts` e upstream ghofl mishe va aksar e quote ha `stale` rad mishan،
     * pas kelid e tablo ab mirafet. Vali Postgres akharin-ma'lum e HAME ro
     * negah midare — pas masir e fallback BOZORG mimund.
     *
     * Do masiri ke bayad eyn e ham bashan، har shab vagera mishodan.
     */
    public function test_the_hot_board_keeps_symbols_that_did_not_tick(): void
    {
        // Do symbol ja mioftan.
        $this->tick([
            $this->quote(266_400_000, Instrument::Gold18Gram),
            $this->quote(354_240_000, Instrument::Gold24Gram),
        ]);
        self::assertCount(2, $this->cache->board());

        $this->clock->advance(15);

        // Tick e ba'di faghat YEKI az un ha ro mide (un yeki bayat shode).
        $this->tick([$this->quote(267_000_000, Instrument::Gold18Gram)]);

        // Tablo bayad HANUZ har do ro dashte bashe.
        $board = $this->cache->board();
        self::assertCount(2, $board);
        self::assertSame(267_000_000, $board['tgju:xau-18k-g-irr:last']->price->minor);
        self::assertSame(354_240_000, $board['tgju:xau-24k-g-irr:last']->price->minor);
    }

    /**
     * 🔴 Ta'ahod e asli ye `P1-26`: masir e DAGH va masir e FALLBACK bayad
     * daghighan hamun majmu'e ro bedan.
     */
    public function test_the_hot_board_and_postgres_hold_the_same_rows(): void
    {
        $this->tick([
            $this->quote(266_400_000, Instrument::Gold18Gram),
            $this->quote(354_240_000, Instrument::Gold24Gram),
        ]);
        $this->clock->advance(15);
        $this->tick([$this->quote(267_000_000, Instrument::Gold18Gram)]);

        $hot = array_keys($this->cache->board());
        $cold = array_keys($this->store->all());
        sort($hot);
        sort($cold);

        self::assertSame($cold, $hot);
    }

    /** Zarabān har tick be-rooz mishe — ham paye ye kashf e yakh-zadegi. */
    public function test_every_tick_updates_the_writer_heartbeat(): void
    {
        $this->tick([$this->quote()]);

        self::assertSame($this->clock->tick()->timestamp(), $this->cache->heartbeatAt());
    }

    /**
     * ⚠️ Shab e kamel: HICH quote-i paziresh nemishe (hame `stale`) — vali
     * zarabān bayad HANUZ be-rooz beshe.
     *
     * Vagarna `BoardQuery` har shab eshtebahan «tablo yakh zade» e'lam
     * mikard va be Postgres fallback mikard. Be hamin dalil zarabān tu
     * `PublishStage` ast (ke hamishe ejra mishe)، na tu `PersistStage` (ke
     * vaghti chizi nist fori bar migarde).
     */
    public function test_a_fully_stale_tick_still_updates_the_heartbeat(): void
    {
        $this->tick([$this->quote()]);
        $this->clock->advance(3_600);

        // `ageSeconds: 5000` -> kheyli bayat tar az saghf، pas rad mishe.
        $report = $this->tick([$this->quote(ageSeconds: 5_000)]);

        self::assertSame(0, $report->accepted);
        self::assertSame($this->clock->tick()->timestamp(), $this->cache->heartbeatAt());
    }

    /** Tu halat e OOM zarabān ham — ke KHODESH yek write ast — gir mikone. */
    public function test_a_redis_oom_stops_the_heartbeat(): void
    {
        $this->tick([$this->quote()]);
        $before = $this->cache->heartbeatAt();

        $this->cache->failWritesOnly();
        $this->clock->advance(15);
        $this->tick([$this->quote(267_000_000)]);

        // Zarabān pish nemire -> `BoardQuery` yakh-zadegi ro kashf mikone.
        self::assertSame($before, $this->cache->heartbeatAt());
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
