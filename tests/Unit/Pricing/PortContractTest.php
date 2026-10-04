<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Application\Dto\CandleDto;
use App\Modules\Pricing\Application\Dto\CandleRequestDto;
use App\Modules\Pricing\Application\Dto\ProviderHealthDto;
use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Interval;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Domain\Ports\QuoteSourcePort;
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use App\Modules\Pricing\Domain\Ports\TimeSeriesPort;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Fakes\ArrayHotCache;
use Tests\Support\Fakes\FakeQuoteSource;
use Tests\Support\Fakes\FakeTimeSeries;
use Tests\Support\Fakes\FrozenClock;
use Tests\Support\Fakes\InMemorySnapshotStore;

/**
 * Har fake bayad GHARARDAD e port esh ro kamel emza kone.
 *
 * Agar yek method be port ezafe she va fake be-rooz nashe، PHP khodesh fatal
 * mide — vali in test un ro be sorat e yek shekast e tamiz e test neshun mide،
 * na yek crash e vasat e test e digar.
 */
final class PortContractTest extends TestCase
{
    private function snapshot(int $minor = 266_400_000, string $provider = 'tgju'): SnapshotDto
    {
        return new SnapshotDto(
            providerKey: $provider,
            instrument: Instrument::Gold18Gram,
            side: Side::Last,
            price: Price::irr($minor),
            quotedAt: (new FrozenClock)->now(),
        );
    }

    // ── emza ye port ha ──────────────────────────────────────────

    public function test_every_fake_implements_its_port(): void
    {
        self::assertInstanceOf(ClockPort::class, new FrozenClock);
        self::assertInstanceOf(QuoteSourcePort::class, new FakeQuoteSource);
        self::assertInstanceOf(SnapshotStorePort::class, new InMemorySnapshotStore);
        self::assertInstanceOf(HotCachePort::class, new ArrayHotCache);
        self::assertInstanceOf(TimeSeriesPort::class, new FakeTimeSeries);
    }

    /**
     * 🔴 `Domain/` nabayad hich chiz e Laravel bebine. In test un marz ro
     * mohafezat mikone — ta'rif e me'mari ye kol e module ast.
     *
     * Carbon estesna ast: yek kitabkhane ye zaman ast، na framework، va
     * Laravel khodesh un ro be onvan e type e paye estefade mikone.
     */
    public function test_domain_layer_does_not_depend_on_laravel(): void
    {
        $files = [];
        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(__DIR__.'/../../../app/Modules/Pricing/Domain'),
        );

        foreach ($directory as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        self::assertNotEmpty($files, 'hich file-i tu Domain peyda nashod.');

        foreach ($files as $file) {
            // Comment ha hazf mishan: in ghanun dar morede CODE ast, na prose.
            // (Khod e `ClockPort` tu docblock esh minevise «`now()` mamnu' ast»
            //  — yek jostoju ye sade hamun ro be onvan e takhallof migereft.)
            $contents = self::stripComments((string) file_get_contents($file));
            $name = basename($file);

            self::assertStringNotContainsString(
                'use Illuminate\\',
                $contents,
                "{$name}: Domain nabayad Illuminate import kone.",
            );

            foreach (['Cache::', 'Http::', 'DB::', 'Log::', 'Redis::'] as $facade) {
                self::assertStringNotContainsString(
                    $facade,
                    $contents,
                    "{$name}: facade e `{$facade}` tu Domain mamnu' ast.",
                );
            }

            // Helper haye global e Laravel. Regex (na jostoju ye sade) chun
            // `TickTime::now()` va `public static function now()` khodemun-and
            // va nabayad ba `now()` e Laravel eshtebah gerefte shan.
            foreach (['config', 'app', 'now', 'cache', 'resolve', 'report'] as $helper) {
                self::assertDoesNotMatchRegularExpression(
                    '/(?<![:>\w])(?<!function )'.$helper.'\(/',
                    $contents,
                    "{$name}: helper e global e `{$helper}()` tu Domain mamnu' ast.",
                );
            }
        }
    }

    // ── ClockPort ────────────────────────────────────────────────

    public function test_frozen_clock_does_not_move_on_its_own(): void
    {
        $clock = new FrozenClock('2026-10-04T12:21:33Z');

        self::assertSame($clock->now()->toIso8601String(), $clock->now()->toIso8601String());
        self::assertSame('2026-10-04T12:21:30+00:00', $clock->tick()->toString());
    }

    public function test_frozen_clock_can_be_advanced(): void
    {
        $clock = new FrozenClock('2026-10-04T12:21:30Z');
        $before = $clock->tick()->timestamp();

        $clock->advance(15);

        self::assertSame($before + 15, $clock->tick()->timestamp());
    }

    // ── QuoteSourcePort ──────────────────────────────────────────

    public function test_fake_source_returns_programmed_quotes(): void
    {
        $quote = new RawQuoteDto('tgju', Instrument::Gold18Gram, Side::Last, Price::irr(266_400_000));
        $source = (new FakeQuoteSource('tgju'))->returns([$quote]);

        self::assertSame([$quote], $source->fetch());
        self::assertSame(1, $source->fetchCount());
    }

    /**
     * Source bayad THROW kone، na array e khali bargardune — array e khali
     * ba «bazaar baste ast» eshtebah gerefte mishe va pipeline provider ro
     * salem hesab mikone.
     */
    public function test_failing_source_throws_rather_than_returning_empty(): void
    {
        $this->expectException(RuntimeException::class);

        (new FakeQuoteSource)->fails()->fetch();
    }

    public function test_raw_quote_key_is_unique_per_provider_symbol_and_side(): void
    {
        $bid = new RawQuoteDto('tgju', Instrument::GoldMeltedGram, Side::Bid, Price::irr(1));
        $ask = new RawQuoteDto('tgju', Instrument::GoldMeltedGram, Side::Ask, Price::irr(2));

        self::assertNotSame($bid->key(), $ask->key());
        self::assertSame('tgju:xau-melted-g-irr:bid', $bid->key());
    }

    // ── SnapshotStorePort ────────────────────────────────────────

    public function test_store_upserts_by_key(): void
    {
        $store = new InMemorySnapshotStore;

        $store->save([$this->snapshot(100)]);
        $store->save([$this->snapshot(200)]);

        self::assertCount(1, $store->all());
        self::assertSame(200, $store->all()['tgju:xau-18k-g-irr:last']->price->minor);
    }

    public function test_store_keeps_providers_separate(): void
    {
        $store = new InMemorySnapshotStore;

        $store->save([$this->snapshot(100, 'tgju'), $this->snapshot(101, 'estjt')]);

        self::assertCount(2, $store->all());
    }

    public function test_store_appends_changes_without_replacing(): void
    {
        $store = new InMemorySnapshotStore;

        $store->appendChanges([$this->snapshot(100)]);
        $store->appendChanges([$this->snapshot(200)]);

        self::assertCount(2, $store->changes());
    }

    public function test_broken_store_throws_so_the_pipeline_can_isolate_it(): void
    {
        $this->expectException(RuntimeException::class);

        (new InMemorySnapshotStore)->breakIt()->all();
    }

    // ── HotCachePort ─────────────────────────────────────────────

    public function test_cache_bumps_version_on_every_write(): void
    {
        $cache = new ArrayHotCache;
        self::assertSame(0, $cache->boardVersion());

        $cache->putBoard([$this->snapshot()]);
        $cache->putBoard([$this->snapshot(200)]);

        self::assertSame(2, $cache->boardVersion());
    }

    public function test_cache_stores_health_per_provider(): void
    {
        $cache = new ArrayHotCache;
        $clock = new FrozenClock;

        $cache->putHealth(new ProviderHealthDto('tgju', true, $clock->now()));
        $cache->putHealth(new ProviderHealthDto('estjt', false, $clock->now(), error: 'timeout'));

        self::assertCount(2, $cache->health());
        self::assertSame('timeout', $cache->health()['estjt']->error);
    }

    public function test_broken_cache_reports_unavailable_before_throwing(): void
    {
        $cache = (new ArrayHotCache)->breakIt();

        // `isAvailable()` nabayad throw kone — caller bahash tasmim migire.
        self::assertFalse($cache->isAvailable());

        $this->expectException(RuntimeException::class);
        $cache->board();
    }

    /**
     * Shabih-sazi ye daghighan hamun chizi ke `allkeys-lru` mikone: Redis
     * salem ast vali kelid ha nistan. Tablo bayad betune az Postgres backfill
     * she — in test un halat ro mojud mikone ta pipeline betune test esh kone.
     */
    public function test_cache_can_simulate_silent_eviction(): void
    {
        $cache = new ArrayHotCache;
        $cache->putBoard([$this->snapshot()]);

        $cache->evictEverything();

        self::assertTrue($cache->isAvailable(), 'Redis khodesh ro salem gozaresh mide.');
        self::assertSame([], $cache->board(), '...vali kelid ha rafte-an.');
    }

    // ── TimeSeriesPort ───────────────────────────────────────────

    public function test_time_series_records_the_tick_timestamp(): void
    {
        $series = new FakeTimeSeries;
        $clock = new FrozenClock('2026-10-04T12:21:33Z');

        $series->writeTicks([$this->snapshot()], $clock->tick());

        self::assertSame(1, $series->writeCount());
        // floor-shode ru grid e 15 saniye-i
        self::assertSame(0, $series->writtenTimestamps()[0] % 15);
    }

    public function test_time_series_returns_programmed_candles(): void
    {
        $candle = new CandleDto(1_791_104_490_000, 100.0, 110.0, 90.0, 105.0, 4.0);
        $series = (new FakeTimeSeries)->willReturn([$candle]);

        $request = CandleRequestDto::make(
            'tgju',
            Instrument::Gold18Gram,
            Interval::M5,
            1_791_100_000,
            1_791_104_500,
        );

        self::assertSame([$candle], $series->candles($request));
    }

    public function test_broken_time_series_throws_so_caller_can_spool(): void
    {
        $this->expectException(RuntimeException::class);

        (new FakeTimeSeries)->breakIt()->writeTicks([$this->snapshot()], (new FrozenClock)->tick());
    }

    // ── CandleRequestDto ─────────────────────────────────────────

    /**
     * 🔴 Bedun e quantize، `to = now()` e har karbar yek kelid e yekta misaze
     * va hit rate e cache daghighan sefr mishe.
     */
    public function test_candle_request_quantizes_the_range_to_the_interval(): void
    {
        $a = CandleRequestDto::make('tgju', Instrument::Gold18Gram, Interval::M5, 1_791_100_003, 1_791_104_497);
        $b = CandleRequestDto::make('tgju', Instrument::Gold18Gram, Interval::M5, 1_791_100_111, 1_791_104_499);

        self::assertSame($a->from, $b->from);
        self::assertSame($a->to, $b->to);
        self::assertSame($a->cacheKey(1), $b->cacheKey(1));

        self::assertSame(0, $a->from % Interval::M5->seconds());
        self::assertSame(0, $a->to % Interval::M5->seconds());
    }

    /** `spreadVersion` bayad tu kelid bashe — vagarna tablo va chart vagera mishan. */
    public function test_cache_key_changes_with_spread_version(): void
    {
        $request = CandleRequestDto::make('tgju', Instrument::Gold18Gram, Interval::M5, 1_791_100_000, 1_791_104_500);

        self::assertNotSame($request->cacheKey(1), $request->cacheKey(2));
    }

    public function test_candle_request_caps_the_limit(): void
    {
        $request = CandleRequestDto::make(
            'tgju', Instrument::Gold18Gram, Interval::M1, 0, 100, limit: 999_999,
        );

        self::assertSame(CandleRequestDto::MAX_LIMIT, $request->limit);
    }

    public function test_candle_request_never_produces_an_empty_window(): void
    {
        $request = CandleRequestDto::make('tgju', Instrument::Gold18Gram, Interval::H1, 1_000, 1_000);

        self::assertGreaterThan($request->from, $request->to);
    }

    // ── Interval ─────────────────────────────────────────────────

    /** `m1` amdan az `ticks` mikhune ta be latency e task e Influx vabaste nabashe. */
    public function test_only_one_minute_reads_raw_ticks(): void
    {
        $raw = array_filter(Interval::cases(), static fn (Interval $i): bool => $i->readsRawTicks());

        self::assertSame([Interval::M1], array_values($raw));
        self::assertSame('ticks', Interval::M1->bucket());
    }

    public function test_every_interval_maps_to_a_distinct_bucket(): void
    {
        $buckets = array_map(static fn (Interval $i): string => $i->bucket(), Interval::cases());

        self::assertSame($buckets, array_unique($buckets));
    }

    public function test_cache_ttl_grows_with_the_bar_size(): void
    {
        $previous = 0;

        foreach (Interval::cases() as $interval) {
            self::assertGreaterThan(
                $previous,
                $interval->cacheSeconds(),
                "{$interval->value}: TTL bayad az interval e kuchik tar bishtar bashe.",
            );
            $previous = $interval->cacheSeconds();
        }
    }

    // ── SnapshotDto ──────────────────────────────────────────────

    public function test_snapshot_detects_a_price_change(): void
    {
        $old = $this->snapshot(100);
        $same = $this->snapshot(100);
        $different = $this->snapshot(200);

        self::assertFalse($same->differsFrom($old));
        self::assertTrue($different->differsFrom($old));
        self::assertTrue($different->differsFrom(null), 'snapshot e aval hamishe taghir hesab mishe.');
    }

    public function test_snapshot_age_never_goes_negative(): void
    {
        $clock = new FrozenClock('2026-10-04T12:21:30Z');
        $snapshot = $this->snapshot();

        self::assertSame(0, $snapshot->ageSeconds($clock->now()));
        self::assertSame(60, $snapshot->ageSeconds($clock->now()->addSeconds(60)));
        self::assertSame(0, $snapshot->ageSeconds($clock->now()->subSeconds(60)));
    }

    /** Hazf e hame ye comment ha ba tokenizer — hich regex e shekanande. */
    private static function stripComments(string $code): string
    {
        $out = '';

        foreach (token_get_all($code) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $out .= $token[1];

                continue;
            }

            $out .= $token;
        }

        return $out;
    }
}
