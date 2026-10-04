<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\Enums\Direction;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Infrastructure\Persistence\EloquentSnapshotStore;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceChange;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceSnapshot;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class EloquentSnapshotStoreTest extends TestCase
{
    use RefreshDatabase;

    private EloquentSnapshotStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new EloquentSnapshotStore;
    }

    private function provider(string $key = 'tgju'): Provider
    {
        return Provider::create([
            'key' => $key,
            'driver' => $key,
            'name' => ucfirst($key),
            'name_fa' => 'آزمایشی',
        ]);
    }

    private function snapshot(
        string $providerKey = 'tgju',
        int $minor = 266_400_000,
        Instrument $instrument = Instrument::Gold18Gram,
        Side $side = Side::Last,
        ?Price $previous = null,
        ?Price $pendingOutlier = null,
    ): SnapshotDto {
        return new SnapshotDto(
            providerKey: $providerKey,
            instrument: $instrument,
            side: $side,
            price: new Price($minor, $instrument->quote()),
            quotedAt: CarbonImmutable::parse('2026-10-04T12:21:30Z'),
            previous: $previous,
            dayOpen: new Price($minor - 1_000_000, $instrument->quote()),
            dayHigh: new Price($minor + 2_000_000, $instrument->quote()),
            dayLow: new Price($minor - 3_000_000, $instrument->quote()),
            changePercent: 1.58,
            direction: Direction::Up,
            upstreamAt: CarbonImmutable::parse('2026-10-04T12:21:18Z'),
            upstreamLagSeconds: 12,
            pendingOutlier: $pendingOutlier,
        );
    }

    // ── neveshtan ────────────────────────────────────────────────

    public function test_saves_a_snapshot(): void
    {
        $this->provider();

        $this->store->save([$this->snapshot()]);

        $this->assertSame(1, PriceSnapshot::query()->count());
        $this->assertSame(266_400_000, PriceSnapshot::query()->first()->raw_minor);
    }

    /**
     * 🔴 Tick e dovom bayad UPDATE kone, na insert.
     * Agar unique/upsert kar nakone, jadval e «akharin gheymat» ba'd az yek
     * rooz 5,760 radif be ezaye har symbol dare.
     */
    public function test_second_tick_updates_instead_of_inserting(): void
    {
        $this->provider();

        $this->store->save([$this->snapshot(minor: 266_400_000)]);
        $this->store->save([$this->snapshot(minor: 267_000_000)]);

        $this->assertSame(1, PriceSnapshot::query()->count());
        $this->assertSame(267_000_000, PriceSnapshot::query()->first()->raw_minor);
    }

    /**
     * 🔴 Yek dastur, na halghe.
     *
     * `hg_website` tu halghe `updateOrCreate` mizad: ba 50 radif ya'ni 50
     * round-trip tu HAR tick, 5,760 bar tu rooz. In test un regression ro
     * jologiri mikone.
     */
    public function test_saves_many_snapshots_in_a_single_statement(): void
    {
        $this->provider();

        $snapshots = [];
        foreach ([Instrument::Gold18Gram, Instrument::Gold24Gram, Instrument::CoinEmami] as $i => $instrument) {
            $snapshots[] = $this->snapshot(minor: 100_000_000 + $i, instrument: $instrument);
        }

        DB::enableQueryLog();
        $this->store->save($snapshots);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(3, PriceSnapshot::query()->count());

        $writes = array_filter(
            $queries,
            static fn (array $q): bool => str_contains(strtolower($q['query']), 'insert'),
        );

        $this->assertCount(1, $writes, 'bayad FAGHAT yek dastur e insert/upsert bashe.');
    }

    public function test_saves_bid_and_ask_as_separate_rows(): void
    {
        $this->provider();

        $this->store->save([
            $this->snapshot(minor: 263_175_000, instrument: Instrument::GoldMeltedGram, side: Side::Bid),
            $this->snapshot(minor: 264_921_000, instrument: Instrument::GoldMeltedGram, side: Side::Ask),
        ]);

        $this->assertSame(2, PriceSnapshot::query()->count());
    }

    /** `has_native_sides` az khod e `Instrument` miad, na az caller. */
    public function test_derives_native_sides_flag_from_the_instrument(): void
    {
        $this->provider();

        $this->store->save([
            $this->snapshot(instrument: Instrument::GoldMeltedGram, side: Side::Bid),
            $this->snapshot(instrument: Instrument::Gold18Gram),
        ]);

        $melted = PriceSnapshot::query()->where('symbol', 'xau-melted-g-irr')->first();
        $gold18 = PriceSnapshot::query()->where('symbol', 'xau-18k-g-irr')->first();

        $this->assertTrue($melted->has_native_sides);
        $this->assertFalse($gold18->has_native_sides);
    }

    /** Snapshot e provider e nashenas saket rad mishe, na inke tick ro bokoshe. */
    public function test_ignores_snapshots_for_unknown_providers(): void
    {
        $this->provider('tgju');

        $this->store->save([
            $this->snapshot('tgju'),
            $this->snapshot('provider-e-khialI'),
        ]);

        $this->assertSame(1, PriceSnapshot::query()->count());
    }

    public function test_saving_nothing_is_a_no_op(): void
    {
        $this->provider();

        $this->store->save([]);

        $this->assertSame(0, PriceSnapshot::query()->count());
    }

    // ── khundan ──────────────────────────────────────────────────

    public function test_reads_back_a_full_round_trip(): void
    {
        $this->provider();
        $original = $this->snapshot(
            previous: Price::irr(265_000_000),
            pendingOutlier: Price::irr(400_000_000),
        );

        $this->store->save([$original]);
        $loaded = $this->store->all()[$original->key()];

        $this->assertSame($original->providerKey, $loaded->providerKey);
        $this->assertSame($original->instrument, $loaded->instrument);
        $this->assertSame($original->side, $loaded->side);
        $this->assertTrue($original->price->equals($loaded->price));
        $this->assertTrue($original->previous->equals($loaded->previous));
        $this->assertTrue($original->dayHigh->equals($loaded->dayHigh));
        $this->assertSame($original->direction, $loaded->direction);
        $this->assertSame(1.58, $loaded->changePercent);
        $this->assertSame(12, $loaded->upstreamLagSeconds);
    }

    /**
     * 🔴 `pending_outlier` bayad az DB bargarde.
     *
     * Ghanun e «do outlier e mote'ali vaghei-and» bedun e in kar nemikone:
     * tick e ba'di nemidune ke tick e ghabli chi ro rad karde bud.
     */
    public function test_pending_outlier_survives_a_round_trip(): void
    {
        $this->provider();

        $this->store->save([$this->snapshot(pendingOutlier: Price::irr(400_000_000))]);

        $loaded = array_values($this->store->all())[0];

        $this->assertNotNull($loaded->pendingOutlier);
        $this->assertSame(400_000_000, $loaded->pendingOutlier->minor);
    }

    public function test_reads_are_keyed_by_provider_symbol_and_side(): void
    {
        $this->provider('tgju');
        $this->provider('estjt');

        $this->store->save([
            $this->snapshot('tgju', 266_400_000),
            $this->snapshot('estjt', 266_818_000),
        ]);

        $all = $this->store->all();

        $this->assertArrayHasKey('tgju:xau-18k-g-irr:last', $all);
        $this->assertArrayHasKey('estjt:xau-18k-g-irr:last', $all);
        $this->assertSame(266_818_000, $all['estjt:xau-18k-g-irr:last']->price->minor);
    }

    /**
     * Symbol-i ke dige tu enum nist (esm esh avaz shode) nabayad kol e
     * khundan ro beterkune — saket rad mishe.
     */
    public function test_skips_rows_whose_symbol_is_no_longer_known(): void
    {
        $provider = $this->provider();

        DB::table('price_snapshots')->insert([
            'provider_id' => $provider->id,
            'symbol' => 'xau-symbol-e-ghadimi',
            'side' => 'last',
            'quote_currency' => 'IRR',
            'scale' => 0,
            'raw_minor' => 1,
            'quoted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame([], $this->store->all());
    }

    /** `bigint` e Postgres be sorat e string bar migarde — cast bayad kar kone. */
    public function test_large_values_come_back_as_integers(): void
    {
        $this->provider();

        $this->store->save([$this->snapshot(minor: 2_753_400_000, instrument: Instrument::CoinEmami)]);

        $loaded = array_values($this->store->all())[0];

        $this->assertIsInt($loaded->price->minor);
        $this->assertSame(2_753_400_000, $loaded->price->minor);
    }

    public function test_usd_instruments_keep_their_scale(): void
    {
        $this->provider();

        $this->store->save([$this->snapshot(minor: 41_401_900, instrument: Instrument::GoldOunce)]);

        $loaded = array_values($this->store->all())[0];

        $this->assertSame(4, $loaded->price->quote->scale());
        $this->assertSame(4140.19, $loaded->price->toFloat());
    }

    // ── daftarche ye taghirat ────────────────────────────────────

    public function test_appends_changes(): void
    {
        $this->provider();

        $this->store->appendChanges([$this->snapshot(minor: 266_400_000)]);
        $this->store->appendChanges([$this->snapshot(minor: 266_500_000)]);

        $this->assertSame(2, PriceChange::query()->count());
    }

    /** Daftarche append-only ast — radif e ghabli dast nemikhore. */
    public function test_changes_are_append_only(): void
    {
        $this->provider();

        $this->store->appendChanges([$this->snapshot(minor: 100)]);
        $this->store->appendChanges([$this->snapshot(minor: 200)]);

        $minors = PriceChange::query()->orderBy('id')->pluck('raw_minor')->all();

        $this->assertSame([100, 200], array_map('intval', $minors));
    }

    /** `observed_at` zaman e TICK ast, na lahze ye insert. */
    public function test_change_records_the_tick_time_not_the_insert_time(): void
    {
        $this->provider();

        $this->store->appendChanges([$this->snapshot()]);

        $this->assertSame(
            '2026-10-04T12:21:30+00:00',
            PriceChange::query()->first()->observed_at->toIso8601String(),
        );
    }

    public function test_appending_nothing_is_a_no_op(): void
    {
        $this->provider();

        $this->store->appendChanges([]);

        $this->assertSame(0, PriceChange::query()->count());
    }
}
