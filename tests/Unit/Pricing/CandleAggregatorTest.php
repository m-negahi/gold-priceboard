<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Application\Dto\CandleDto;
use App\Modules\Pricing\Application\Support\CandleAggregator;
use App\Modules\Pricing\Application\Support\JalaliBucket;
use App\Modules\Pricing\Domain\Enums\ChartPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Chart e haftegi/mahane/salane (P5-08) az candle e RUZANE — ba taghvim e
 * JALALI (hafte az shanbe، mah/sal e shamsi). Flux faghat mah e miladi
 * dare، pas tajmi' inja-st.
 *
 * 🔴 Tale ye asli: bar e ruzane az 00:00 e TEHRAN shoru' mishe، ya'ni
 * timestamp esh `20:30Z` e rooz e GHABL ast. Bucket e UTC un ro tu
 * rooz/mah e eshtebah mindaze.
 */
final class CandleAggregatorTest extends TestCase
{
    /** Bar e ruzane ye yek tarikh e TEHRAN. */
    private function day(string $tehranDate, float $o, float $h, float $l, float $c, float $v = 10): CandleDto
    {
        $ms = CarbonImmutable::parse($tehranDate.' 00:00:00', 'Asia/Tehran')->getTimestampMs();

        return new CandleDto(timestamp: $ms, open: $o, high: $h, low: $l, close: $c, volume: $v);
    }

    private function ms(string $tehranDate): int
    {
        return CarbonImmutable::parse($tehranDate.' 00:00:00', 'Asia/Tehran')->getTimestampMs();
    }

    // ── JalaliBucket ────────────────────────────────────────────────

    public function test_the_first_of_mehr_is_in_mehr_not_shahrivar(): void
    {
        // 1405/07/01 = 2026-09-23 Tehran = 2026-09-22T20:30Z (rooz e GHABL be UTC).
        self::assertSame('1405-07', JalaliBucket::key('month', $this->ms('2026-09-23')));
        // Akharin rooz e Shahrivar.
        self::assertSame('1405-06', JalaliBucket::key('month', $this->ms('2026-09-22')));
    }

    public function test_the_year_turns_at_nowruz(): void
    {
        // 1405/01/01 = 2026-03-21؛ rooz e ghablesh hanuz 1404.
        self::assertSame('1405', JalaliBucket::key('year', $this->ms('2026-03-21')));
        self::assertSame('1404', JalaliBucket::key('year', $this->ms('2026-03-20')));
    }

    public function test_the_week_starts_on_saturday(): void
    {
        // 2026-10-03 shanbe-st، 2026-10-09 jom'e.
        $saturday = JalaliBucket::key('week', $this->ms('2026-10-03'));

        self::assertSame($saturday, JalaliBucket::key('week', $this->ms('2026-10-09')), 'shanbe ta jom\'e yek hafte-and.');
        self::assertNotSame($saturday, JalaliBucket::key('week', $this->ms('2026-10-10')), 'shanbe ye ba\'d hafte ye jadid.');
        self::assertNotSame($saturday, JalaliBucket::key('week', $this->ms('2026-10-02')), 'jom\'e ye ghabl hafte ye ghabli.');
    }

    // ── CandleAggregator ────────────────────────────────────────────

    public function test_ohlc_of_a_bucket_is_first_open_max_high_min_low_last_close(): void
    {
        $bars = CandleAggregator::aggregate([
            $this->day('2026-10-03', 100, 120, 95, 110, 5),
            $this->day('2026-10-04', 110, 130, 105, 125, 7),
            $this->day('2026-10-05', 125, 126, 90, 100, 3),
        ], static fn (int $ms): string => JalaliBucket::key('week', $ms));

        self::assertCount(1, $bars);
        $week = $bars[0];
        self::assertSame($this->ms('2026-10-03'), $week->timestamp, 'timestamp = baz shodan e avvalin bar.');
        self::assertSame(100.0, $week->open);
        self::assertSame(130.0, $week->high);
        self::assertSame(90.0, $week->low);
        self::assertSame(100.0, $week->close);
        self::assertSame(15.0, $week->volume);
    }

    public function test_buckets_are_split_and_kept_in_time_order(): void
    {
        $bars = CandleAggregator::aggregate([
            $this->day('2026-09-21', 1, 2, 1, 2),   // Shahrivar
            $this->day('2026-09-22', 2, 3, 2, 3),   // Shahrivar (akharin rooz)
            $this->day('2026-09-23', 3, 4, 3, 4),   // Mehr
            $this->day('2026-10-21', 4, 9, 4, 9),   // Mehr
        ], static fn (int $ms): string => JalaliBucket::key('month', $ms));

        self::assertCount(2, $bars);
        self::assertSame(1.0, $bars[0]->open);
        self::assertSame(3.0, $bars[0]->close);
        self::assertSame(3.0, $bars[1]->open);
        self::assertSame(9.0, $bars[1]->close);
        self::assertLessThan($bars[1]->timestamp, $bars[0]->timestamp);
    }

    public function test_unsorted_input_is_sorted_first(): void
    {
        // Open/close bayad az ZAMAN biad، na tartib e vorudi.
        $bars = CandleAggregator::aggregate([
            $this->day('2026-10-05', 125, 126, 90, 100),
            $this->day('2026-10-03', 100, 120, 95, 110),
        ], static fn (int $ms): string => JalaliBucket::key('week', $ms));

        self::assertSame(100.0, $bars[0]->open);
        self::assertSame(100.0, $bars[0]->close);
    }

    public function test_an_empty_series_stays_empty(): void
    {
        self::assertSame([], CandleAggregator::aggregate([], static fn (int $ms): string => 'x'));
    }

    // ── ChartPeriod ─────────────────────────────────────────────────

    public function test_calendar_periods_are_built_from_daily_candles(): void
    {
        foreach ([ChartPeriod::W1, ChartPeriod::MO1, ChartPeriod::Y1] as $period) {
            self::assertSame('d1', $period->base()->value, "{$period->value} az candle e ruzane sakhte mishe.");
            self::assertNotNull($period->calendar());
        }

        self::assertNull(ChartPeriod::H1->calendar());
        self::assertSame('h1', ChartPeriod::H1->base()->value);
    }

    public function test_the_lookback_fits_inside_the_candle_request_limit(): void
    {
        // 🔴 Bishtar az MAX_LIMIT (5000) bar e ruzane = chart e salane SAKET boride mishe.
        foreach (ChartPeriod::cases() as $period) {
            self::assertLessThanOrEqual(
                5_000,
                intdiv($period->lookbackSeconds(), $period->base()->seconds()),
                "{$period->value}: lookback az saghf e candle request rad mikone.",
            );
        }
    }
}
