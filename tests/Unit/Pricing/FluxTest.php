<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Application\Dto\CandleRequestDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Interval;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Infrastructure\TimeSeries\Flux;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Mohafez e chahar tale-i ke chart ro SAKET kharab mikonan.
 * Har kodum az inha bedun e error kar mikone — faghat javab e ghalat mide.
 */
final class FluxTest extends TestCase
{
    private function request(Interval $interval = Interval::M5): CandleRequestDto
    {
        return CandleRequestDto::make(
            'tgju',
            Instrument::Gold18Gram,
            $interval,
            1_791_100_000,
            1_791_104_500,
        );
    }

    // ── tale ha ──────────────────────────────────────────────────

    /** Tale 1: default e `_stop` kol e chart ro yek candle JOLO mindaze. */
    public function test_aggregate_window_anchors_bars_to_their_start(): void
    {
        $flux = (new Flux)->candles($this->request(Interval::M1));

        self::assertStringContainsString('timeSrc: "_start"', $flux);
        self::assertStringNotContainsString('timeSrc: "_stop"', $flux);
    }

    /** Tale 2: `_field` tu group key ast — bayad ghabl az `set` drop beshe. */
    public function test_drops_field_column_before_renaming_it(): void
    {
        $flux = (new Flux)->candles($this->request(Interval::M1));

        $dropPosition = strpos($flux, 'drop(columns: ["_start", "_stop", "_field"])');
        $setPosition = strpos($flux, 'set(key: "_field"');

        self::assertNotFalse($dropPosition);
        self::assertNotFalse($setPosition);
        self::assertLessThan($setPosition, $dropPosition, '`drop` bayad GHABL az `set` biad.');
    }

    /** Tale 3: `limit` ba'd az sort e soudi GHADIMI tarin ha ro mide. */
    public function test_takes_the_newest_bars_not_the_oldest(): void
    {
        foreach ([Interval::M1, Interval::M5, Interval::D1] as $interval) {
            $flux = (new Flux)->candles($this->request($interval));

            $desc = strpos($flux, 'sort(columns: ["_time"], desc: true)');
            $limit = strpos($flux, 'limit(n:');
            $asc = strrpos($flux, 'sort(columns: ["_time"])');

            self::assertNotFalse($desc, "{$interval->value}: sort e nozuli nist.");
            self::assertLessThan($limit, $desc, "{$interval->value}: sort e nozuli bayad GHABL az limit biad.");
            self::assertGreaterThan($limit, $asc, "{$interval->value}: sort e soudi bayad BA'D az limit biad.");
        }
    }

    /** Tale 4: bedun e offset, candle e ruzane do rooz e Irani ro sovar mishe. */
    public function test_uses_a_fixed_tehran_offset(): void
    {
        $flux = (new Flux)->candles($this->request(Interval::M1));

        self::assertStringContainsString('timezone.fixed(offset: 3h30m)', $flux);
        // `location(name:)` amdan estefade nemishe — Iran az 2022 DST nadare
        // va offset e sabet vabastegi be tzdata ro hazf mikone.
        self::assertStringNotContainsString('timezone.location', $flux);
    }

    // ── entekhab e bucket ────────────────────────────────────────

    /** `m1` zende az `ticks` mikhune, na az bucket e candle. */
    public function test_one_minute_reads_raw_ticks(): void
    {
        $flux = (new Flux)->candles($this->request(Interval::M1));

        self::assertStringContainsString('from(bucket: "ticks")', $flux);
        self::assertStringContainsString('aggregateWindow', $flux);
    }

    public function test_longer_intervals_read_their_candle_bucket(): void
    {
        // ⚠️ Enum nemitune kelid e array bashe — list e tuple.
        $map = [
            [Interval::M5, 'candles_5m'],
            [Interval::M15, 'candles_15m'],
            [Interval::H1, 'candles_1h'],
            [Interval::H4, 'candles_4h'],
            [Interval::D1, 'candles_1d'],
        ];

        foreach ($map as [$interval, $bucket]) {
            $flux = (new Flux)->candles($this->request($interval));

            self::assertStringContainsString("from(bucket: \"{$bucket}\")", $flux);
            // Bucket ghablan aggregate shode — dobare aggregate kardan ghalat ast.
            self::assertStringNotContainsString('aggregateWindow', $flux);
        }
    }

    // ── injection ────────────────────────────────────────────────

    /** @return array<int, array{0: string}> */
    public static function maliciousValues(): array
    {
        return [
            ['tgju" or r.symbol != "'],
            ['tgju")|>drop(columns:["_value"])|>yield(name:"'],
            ["tgju\n|> drop()"],
            ['../../etc/passwd'],
            ['tgju; rm -rf /'],
            [''],
            ['TGJU'],
            ['tgju ok'],
        ];
    }

    /**
     * 🔴 Support e query-parameter e Influx za'if ast, pas allowlist defa' e
     * ASLI ast — na yek laye ye ezafe.
     */
    #[DataProvider('maliciousValues')]
    public function test_rejects_anything_outside_the_allowlist(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        Flux::tag($value);
    }

    public function test_accepts_every_real_symbol_and_side(): void
    {
        foreach (Instrument::cases() as $instrument) {
            self::assertSame($instrument->value, Flux::tag($instrument->value));
        }

        foreach (Side::cases() as $side) {
            self::assertSame($side->value, Flux::tag($side->value));
        }

        foreach (Interval::cases() as $interval) {
            self::assertSame($interval->bucket(), Flux::tag($interval->bucket()));
        }
    }

    public function test_a_hostile_provider_key_never_reaches_the_query(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Flux)->candles(CandleRequestDto::make(
            'tgju" or true or r.provider == "',
            Instrument::Gold18Gram,
            Interval::M5,
            0,
            100,
        ));
    }

    // ── shekl e query ────────────────────────────────────────────

    public function test_filters_on_provider_symbol_and_side(): void
    {
        $flux = (new Flux)->candles($this->request());

        self::assertStringContainsString('r.provider == "tgju"', $flux);
        self::assertStringContainsString('r.symbol == "xau-18k-g-irr"', $flux);
        self::assertStringContainsString('r.side == "last"', $flux);
    }

    public function test_formats_the_range_as_rfc3339(): void
    {
        $flux = (new Flux)->candles($this->request());

        self::assertMatchesRegularExpression('/range\(start: \d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/', $flux);
    }

    public function test_applies_the_server_side_limit(): void
    {
        $request = CandleRequestDto::make(
            'tgju', Instrument::Gold18Gram, Interval::M5, 0, 100, limit: 999_999,
        );

        self::assertStringContainsString('limit(n: '.CandleRequestDto::MAX_LIMIT.')', (new Flux)->candles($request));
    }

    public function test_keeps_only_the_columns_the_chart_needs(): void
    {
        $flux = (new Flux)->candles($this->request());

        self::assertStringContainsString('keep(columns: ["_time", "open", "high", "low", "close", "count"])', $flux);
    }

    public function test_health_query_targets_the_requested_bucket(): void
    {
        $flux = (new Flux)->latestCandleTime('candles_1d');

        self::assertStringContainsString('from(bucket: "candles_1d")', $flux);
        self::assertStringContainsString('limit(n: 1)', $flux);
    }

    public function test_health_query_also_validates_its_bucket(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Flux)->latestCandleTime('candles_1d" |> drop()');
    }
}
