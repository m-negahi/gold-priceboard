<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Dto\CandleRequestDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Interval;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Domain\ValueObjects\TickTime;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxTimeSeries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Test e line protocol (bedun e shabake) + yek round-trip e VAGHEI ru
 * Influx e zende، agar bala bashe.
 */
final class InfluxTimeSeriesTest extends TestCase
{
    /** @param array<string, mixed> $overrides */
    private function series(array $overrides = []): InfluxTimeSeries
    {
        return new InfluxTimeSeries(array_merge([
            'url' => 'http://influxdb:8086',
            'org' => 'priceboard',
            'write_token' => 'priceboard-local-admin-token',
            'read_token' => 'priceboard-local-admin-token',
            'buckets' => ['ticks' => 'ticks'],
            'query_timeout' => 5,
            'write_timeout' => 5,
        ], $overrides));
    }

    /**
     * Pak kardan e data ye yek provider e test ghabl az neveshtan.
     *
     * Bedun e in، baghi-munde ye yek ejra ye ghabli tu HAMUN daghighe
     * mishine va `close` e entezari ro avaz mikone — yek test e felaki ye
     * klassik. Tag e sabet (jaye tag e tasadofi) negah dashte mishe ta
     * cardinality ye Influx ba har ejra bala nare.
     */
    private function purge(string $provider): void
    {
        Http::withToken('priceboard-local-admin-token', 'Token')
            ->timeout(5)
            ->post('http://influxdb:8086/api/v2/delete?org=priceboard&bucket=ticks', [
                'start' => '1970-01-01T00:00:00Z',
                'stop' => CarbonImmutable::now()->addDay()->format('Y-m-d\TH:i:s\Z'),
                'predicate' => sprintf('_measurement="price" AND provider="%s"', $provider),
            ]);
    }

    private function snapshot(
        int $minor = 266_400_000,
        Instrument $instrument = Instrument::Gold18Gram,
        Side $side = Side::Last,
        string $provider = 'tgju',
    ): SnapshotDto {
        return new SnapshotDto(
            providerKey: $provider,
            instrument: $instrument,
            side: $side,
            price: new Price($minor, $instrument->quote()),
            quotedAt: CarbonImmutable::parse('2026-10-04T12:21:30Z'),
            upstreamLagSeconds: 12,
        );
    }

    // ── line protocol ────────────────────────────────────────────

    public function test_builds_a_line_per_snapshot(): void
    {
        $lines = $this->series()->toLineProtocol(
            [$this->snapshot(), $this->snapshot(2_753_400_000, Instrument::CoinEmami)],
            TickTime::fromTimestamp(1_791_104_490),
        );

        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('price,', $lines[0]);
    }

    public function test_line_carries_all_four_tags(): void
    {
        $line = $this->series()->toLineProtocol([$this->snapshot()], TickTime::fromTimestamp(1_791_104_490))[0];

        $this->assertStringContainsString('provider=tgju', $line);
        $this->assertStringContainsString('symbol=xau-18k-g-irr', $line);
        $this->assertStringContainsString('side=last', $line);
        $this->assertStringContainsString('quote=irr', $line);
    }

    /** Gheymat be MAJOR neveshte mishe — chart ba float kar mikone. */
    public function test_writes_the_major_value_and_the_lag(): void
    {
        $line = $this->series()->toLineProtocol([$this->snapshot()], TickTime::fromTimestamp(1_791_104_490))[0];

        $this->assertStringContainsString('value=266400000', $line);
        $this->assertStringContainsString('lag=12', $line);
    }

    public function test_usd_values_keep_their_decimals(): void
    {
        $line = $this->series()->toLineProtocol(
            [$this->snapshot(41_401_900, Instrument::GoldOunce)],
            TickTime::fromTimestamp(1_791_104_490),
        )[0];

        $this->assertStringContainsString('value=4140.19', $line);
        $this->assertStringContainsString('quote=usd', $line);
    }

    /**
     * 🔴 Notation e elmi — dalil e vojud e `sprintf`.
     * `(string) 0.00001` tu PHP mishe `"1.0E-5"` ke Influx rad mikone.
     */
    public function test_never_emits_scientific_notation(): void
    {
        $line = $this->series()->toLineProtocol(
            [$this->snapshot(1, Instrument::CopperPound)],
            TickTime::fromTimestamp(1_791_104_490),
        )[0];

        $this->assertStringNotContainsStringIgnoringCase('e-', $line);
        $this->assertStringNotContainsStringIgnoringCase('e+', $line);
    }

    /**
     * 🔴 Paye ye idempotency: HAME ye point haye yek tick hamun timestamp e
     * floor-shode ro migiran, pas replay e spool overwrite mikone na duplicate.
     */
    public function test_every_line_shares_the_same_floored_timestamp(): void
    {
        $at = TickTime::fromTimestamp(1_791_104_497);

        $lines = $this->series()->toLineProtocol([
            $this->snapshot(),
            $this->snapshot(2_753_400_000, Instrument::CoinEmami),
            $this->snapshot(266_818_000, Instrument::Gold18Gram, Side::Last, 'estjt'),
        ], $at);

        $timestamps = array_map(
            static fn (string $line): string => substr($line, strrpos($line, ' ') + 1),
            $lines,
        );

        $this->assertCount(1, array_unique($timestamps));
        $this->assertSame('1791104490', $timestamps[0]);
        $this->assertSame(0, (int) $timestamps[0] % 15);
    }

    public function test_writing_nothing_is_a_no_op(): void
    {
        $this->series()->writeTicks([], TickTime::fromTimestamp(1_791_104_490));

        $this->assertTrue(true, 'nabayad exception bede.');
    }

    public function test_missing_tokens_fail_loudly(): void
    {
        $this->expectExceptionMessage('PRICING_INFLUX_WRITE_TOKEN');

        $this->series(['write_token' => ''])->writeLines(['price,provider=tgju value=1 1']);
    }

    // ── round-trip e vaghei ──────────────────────────────────────

    /**
     * Minevise va ba'd hamun ro mikhune — ta'eed mikone ke line protocol,
     * bucket, precision va query e Flux hamashun ba ham jur-and.
     */
    public function test_writes_and_reads_back_from_a_live_influx(): void
    {
        $series = $this->series();

        if (! $series->isAvailable()) {
            $this->markTestSkipped('influxdb dar dastres nist.');
        }

        // ⚠️ Timestamp bayad DAKHEL e retention e bucket (30 rooz) bashe —
        // vagarna Influx ba 422 radesh mikone («violates a Retention Policy
        // Lower Bound»). Yek saat pish، floor-shode ru grid.
        // Ru marz e DAGHIGHE floor mishe: bar haye `m1` ba `timeSrc: "_start"`
        // ru shoru' e daghighe mishinan, na ru timestamp e 15-saniye-i e tick.
        $base = intdiv(time() - 3_600, 60) * 60;
        // Provider e makhsus e test ta ba data ye dev ghati nashe.
        $provider = 'phpunit-a';
        $this->purge($provider);

        $written = [];
        for ($i = 0; $i < 5; $i++) {
            $at = TickTime::fromTimestamp($base + $i * 60);
            $minor = 266_400_000 + $i * 100_000;

            $series->writeTicks([$this->snapshot($minor, Instrument::Gold18Gram, Side::Last, $provider)], $at);
            $written[$at->timestamp()] = $minor;
        }

        // Influx neveshtan ro fowri ghabel e query mikone, vali yek mohlat e
        // kutah be sorat e tajrobi payedari ro behtar mikone.
        usleep(300_000);

        $candles = $series->candles(CandleRequestDto::make(
            $provider,
            Instrument::Gold18Gram,
            Interval::M1,
            $base - 60,
            $base + 5 * 60,
        ));

        $this->assertNotEmpty($candles, 'hichi bargasht — line protocol ya query kharab ast.');

        $byTimestamp = [];
        foreach ($candles as $candle) {
            $byTimestamp[(int) ($candle->timestamp / 1000)] = $candle;
        }

        foreach ($written as $timestamp => $minor) {
            $this->assertArrayHasKey($timestamp, $byTimestamp, "bar e {$timestamp} bargasht nakhord.");
            $this->assertSame((float) $minor, $byTimestamp[$timestamp]->close);
        }
    }

    /**
     * 🔴 Neveshtan e dobare ye hamun tick bayad OVERWRITE kone, na duplicate.
     * Hamin chizi-e ke replay e spool ro emn mikone.
     */
    public function test_rewriting_the_same_tick_overwrites_instead_of_duplicating(): void
    {
        $series = $this->series();

        if (! $series->isAvailable()) {
            $this->markTestSkipped('influxdb dar dastres nist.');
        }

        $at = TickTime::fromTimestamp(intdiv(time() - 7_200, 60) * 60);
        $provider = 'phpunit-b';
        $this->purge($provider);

        $series->writeTicks([$this->snapshot(100_000_000, Instrument::Gold18Gram, Side::Last, $provider)], $at);
        usleep(200_000);
        // Hamun tick, gheymat e motefavet — shabih e replay e spool.
        $series->writeTicks([$this->snapshot(200_000_000, Instrument::Gold18Gram, Side::Last, $provider)], $at);
        usleep(300_000);

        $candles = $series->candles(CandleRequestDto::make(
            $provider,
            Instrument::Gold18Gram,
            Interval::M1,
            $at->timestamp() - 60,
            $at->timestamp() + 60,
        ));

        $matching = array_filter(
            $candles,
            static fn ($candle): bool => (int) ($candle->timestamp / 1000) === $at->timestamp(),
        );

        $this->assertCount(1, $matching, 'bayad FAGHAT yek bar bashe — na do ta.');
        $this->assertSame(200_000_000.0, array_values($matching)[0]->close, 'akharin neveshtan bayad barande bashe.');
    }

    public function test_reports_availability(): void
    {
        $this->assertTrue($this->series()->isAvailable());
        $this->assertFalse($this->series(['url' => 'http://influx.invalid:9999'])->isAvailable());
    }
}
