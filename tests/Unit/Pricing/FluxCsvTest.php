<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Infrastructure\TimeSeries\FluxCsv;
use PHPUnit\Framework\TestCase;

/**
 * Influx «annotated CSV» mide: se khat e `#`, ba'd header, ba'd data.
 * Mitune CHAND block bede — har kodum header e khodesh ro dare.
 */
final class FluxCsvTest extends TestCase
{
    private function csv(): string
    {
        return <<<'CSV'
        #datatype,string,long,dateTime:RFC3339,double,double,double,double,double
        #group,false,false,false,false,false,false,false,false
        #default,_result,,,,,,,
        ,result,table,_time,open,high,low,close,count
        ,,0,2026-10-04T12:00:00Z,266400000,266500000,266300000,266480000,4
        ,,0,2026-10-04T12:05:00Z,266480000,266600000,266450000,266550000,5
        CSV;
    }

    public function test_parses_candles(): void
    {
        $candles = FluxCsv::toCandles($this->csv());

        self::assertCount(2, $candles);
        self::assertSame(266_400_000.0, $candles[0]->open);
        self::assertSame(266_500_000.0, $candles[0]->high);
        self::assertSame(266_300_000.0, $candles[0]->low);
        self::assertSame(266_480_000.0, $candles[0]->close);
        self::assertSame(4.0, $candles[0]->volume);
    }

    /** 🔴 KLineCharts timestamp e MILLISECOND mikhad, na saniye. */
    public function test_timestamps_are_in_milliseconds(): void
    {
        $candles = FluxCsv::toCandles($this->csv());

        self::assertSame(1_791_115_200_000, $candles[0]->timestamp);
        self::assertSame(13, strlen((string) $candles[0]->timestamp));
    }

    public function test_keeps_the_order_influx_returned(): void
    {
        $candles = FluxCsv::toCandles($this->csv());

        self::assertLessThan($candles[1]->timestamp, $candles[0]->timestamp);
    }

    public function test_ignores_annotation_lines(): void
    {
        self::assertCount(2, FluxCsv::toCandles($this->csv()));
    }

    /** Influx mitune chand table bede — har kodum ba header e taze. */
    public function test_handles_multiple_result_blocks(): void
    {
        $csv = $this->csv()."\n\n".<<<'CSV'
        #datatype,string,long,dateTime:RFC3339,double,double,double,double,double
        ,result,table,_time,open,high,low,close,count
        ,,1,2026-10-04T12:10:00Z,266550000,266700000,266500000,266650000,6
        CSV;

        self::assertCount(3, FluxCsv::toCandles($csv));
    }

    /** Soton ha mitunan ja be ja shan — header manba' e haghighat ast. */
    public function test_reads_columns_by_name_not_position(): void
    {
        $csv = <<<'CSV'
        ,result,table,close,_time,open,count,high,low
        ,,0,266480000,2026-10-04T12:00:00Z,266400000,4,266500000,266300000
        CSV;

        $candles = FluxCsv::toCandles($csv);

        self::assertSame(266_400_000.0, $candles[0]->open);
        self::assertSame(266_480_000.0, $candles[0]->close);
        self::assertSame(266_300_000.0, $candles[0]->low);
    }

    /** Radif e na-kamel be dard e chart nemikhore — saket rad mishe. */
    public function test_skips_rows_missing_an_ohlc_field(): void
    {
        $csv = <<<'CSV'
        ,result,table,_time,open,high,low,close,count
        ,,0,2026-10-04T12:00:00Z,266400000,266500000,266300000,266480000,4
        ,,0,2026-10-04T12:05:00Z,,266600000,266450000,266550000,5
        CSV;

        self::assertCount(1, FluxCsv::toCandles($csv));
    }

    public function test_defaults_volume_to_zero_when_absent(): void
    {
        $csv = <<<'CSV'
        ,result,table,_time,open,high,low,close
        ,,0,2026-10-04T12:00:00Z,266400000,266500000,266300000,266480000
        CSV;

        self::assertSame(0.0, FluxCsv::toCandles($csv)[0]->volume);
    }

    public function test_handles_an_empty_response(): void
    {
        self::assertSame([], FluxCsv::toCandles(''));
        self::assertSame([], FluxCsv::toCandles("\n\n"));
    }

    /** Query e khali faghat annotation mide, bedun e radif. */
    public function test_handles_a_header_without_rows(): void
    {
        $csv = <<<'CSV'
        #datatype,string,long,dateTime:RFC3339
        ,result,table,_time,open,high,low,close,count
        CSV;

        self::assertSame([], FluxCsv::toCandles($csv));
    }

    public function test_parses_usd_values_with_decimals(): void
    {
        $csv = <<<'CSV'
        ,result,table,_time,open,high,low,close,count
        ,,0,2026-10-04T12:00:00Z,4140.19,4142.22,4135.91,4140.5,3
        CSV;

        $candle = FluxCsv::toCandles($csv)[0];

        self::assertSame(4140.19, $candle->open);
        self::assertSame(4140.5, $candle->close);
    }

    // ── query e salamati ─────────────────────────────────────────

    /** Query e salamati faghat `_time` keep mikone — bedun e OHLC. */
    public function test_reads_the_first_time_from_a_time_only_result(): void
    {
        $csv = <<<'CSV'
        #datatype,string,long,dateTime:RFC3339
        ,result,table,_time
        ,,0,2026-10-04T12:00:00Z
        CSV;

        self::assertSame(
            '2026-10-04T12:00:00+00:00',
            FluxCsv::firstTime($csv)?->toIso8601String(),
        );
    }

    public function test_first_time_works_on_a_full_candle_result(): void
    {
        self::assertSame(
            '2026-10-04T12:00:00+00:00',
            FluxCsv::firstTime($this->csv())?->toIso8601String(),
        );
    }

    public function test_first_time_is_null_when_there_is_nothing(): void
    {
        self::assertNull(FluxCsv::firstTime(''));
    }
}
