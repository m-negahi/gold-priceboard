<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\SpreadMode;
use App\Modules\Pricing\Domain\Services\SpreadPricer;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Domain\ValueObjects\Spread;
use App\Modules\Pricing\Domain\ValueObjects\SpreadPair;
use PHPUnit\Framework\TestCase;

final class SpreadPricerTest extends TestCase
{
    private function percent(float $value): Spread
    {
        return new Spread(SpreadMode::Percent, $value);
    }

    private function amount(float $value): Spread
    {
        return new Spread(SpreadMode::Amount, $value);
    }

    // ── mode haye paye ───────────────────────────────────────────

    public function test_applies_positive_percent_spread(): void
    {
        $result = SpreadPricer::apply(Price::irr(100_000_000), $this->percent(0.5));

        self::assertSame(100_500_000, $result->minor);
    }

    /** Meghdar ELAMATDAR ast — manfi kam mikone. Soton e jahat nadarim. */
    public function test_negative_percent_spread_subtracts(): void
    {
        $result = SpreadPricer::apply(Price::irr(100_000_000), $this->percent(-0.5));

        self::assertSame(99_500_000, $result->minor);
    }

    public function test_applies_amount_spread_in_major_units_for_irr(): void
    {
        // IRR scale 0 -> 50,000 rial hamun 50,000 minor ast.
        $result = SpreadPricer::apply(Price::irr(100_000_000), $this->amount(50_000));

        self::assertSame(100_050_000, $result->minor);
    }

    /**
     * 🔴 `amount` tu vahed e ASLI ye arz ast، na minor.
     * Admin `1.50` ro be ma'ni ye «yek dollar o nim» type mikone؛ ba scale 4
     * in bayad 15,000 minor beshe، na 1.5 minor.
     */
    public function test_applies_amount_spread_respecting_usd_scale(): void
    {
        $result = SpreadPricer::apply(Price::usd(41_401_900), $this->amount(1.5));

        self::assertSame(41_416_900, $result->minor);
        self::assertSame(4141.69, $result->toFloat());
    }

    public function test_neutral_spread_returns_identical_price(): void
    {
        $raw = Price::irr(100_000_000);

        self::assertTrue(SpreadPricer::apply($raw, Spread::none())->equals($raw));
    }

    // ── Invariant C: kaf e sefr ──────────────────────────────────

    /**
     * Yek `-150` ke eshtebah tu field e `percent` type shode nabayad
     * gheymat e sefr/manfi be moshtari bede — be KHAM fallback mikone.
     */
    public function test_falls_back_to_raw_when_spread_would_drive_price_to_zero(): void
    {
        $raw = Price::irr(100_000_000);

        self::assertTrue(SpreadPricer::apply($raw, $this->percent(-150))->equals($raw));
        self::assertTrue(SpreadPricer::apply($raw, $this->percent(-100))->equals($raw));
        self::assertTrue(SpreadPricer::apply($raw, $this->amount(-200_000_000))->equals($raw));
    }

    public function test_leaves_non_positive_raw_untouched(): void
    {
        $zero = Price::irr(0);

        self::assertTrue(SpreadPricer::apply($zero, $this->percent(5))->equals($zero));
    }

    // ── jofte kharid/foroosh ─────────────────────────────────────

    public function test_applies_independent_buy_and_sell_spreads(): void
    {
        $pair = SpreadPricer::applyPair(
            Price::irr(100_000_000),
            new SpreadPair($this->percent(-1.0), $this->percent(2.0)),
        );

        self::assertSame(99_000_000, $pair['buy']->minor);
        self::assertSame(102_000_000, $pair['sell']->minor);
    }

    // ── Invariant B: band e rooz ─────────────────────────────────

    /**
     * Har do ba delta ye SELL shift mishan (na har kodum ba spread e
     * khodeshun)، ba'd clamp mishan. Bedun e clamp، «بیشترین امروز» mitune
     * kamtar az gheymat e fe'li neshun dade she.
     */
    public function test_day_band_shifts_by_sell_delta(): void
    {
        $raw = Price::irr(100_000_000);
        $buy = Price::irr(99_000_000);
        $sell = Price::irr(102_000_000);

        $band = SpreadPricer::applyDayBand(
            high: Price::irr(105_000_000),
            low: Price::irr(95_000_000),
            raw: $raw,
            buy: $buy,
            sell: $sell,
        );

        // delta ye sell = +2,000,000
        self::assertSame(107_000_000, $band['high']->minor);
        self::assertSame(97_000_000, $band['low']->minor);
    }

    public function test_day_band_clamps_so_live_price_never_escapes(): void
    {
        $raw = Price::irr(100_000_000);
        $buy = Price::irr(80_000_000);
        $sell = Price::irr(120_000_000);

        $band = SpreadPricer::applyDayBand(
            // `high` e kham daghighan hamun `raw` ast -> ba'd az shift
            // mishe 120,000,000 ke daghighan `sell` ast. Clamp bayad
            // motma'en she kamtar az `sell` nashe.
            high: Price::irr(100_000_000),
            low: Price::irr(100_000_000),
            raw: $raw,
            buy: $buy,
            sell: $sell,
        );

        self::assertGreaterThanOrEqual($sell->minor, $band['high']->minor);
        self::assertLessThanOrEqual($buy->minor, $band['low']->minor);
    }

    public function test_day_band_tolerates_missing_values(): void
    {
        $band = SpreadPricer::applyDayBand(
            high: null,
            low: null,
            raw: Price::irr(100),
            buy: Price::irr(99),
            sell: Price::irr(101),
        );

        self::assertNull($band['high']);
        self::assertNull($band['low']);
    }

    // ── series e chart ───────────────────────────────────────────

    private function candles(): array
    {
        return [
            ['timestamp' => 1_000, 'open' => 100.0, 'high' => 110.0, 'low' => 90.0, 'close' => 105.0, 'volume' => 4.0],
            ['timestamp' => 2_000, 'open' => 105.0, 'high' => 115.0, 'low' => 95.0, 'close' => 112.0, 'volume' => 3.0],
        ];
    }

    public function test_applies_spread_to_all_four_ohlc_fields(): void
    {
        $result = SpreadPricer::applySeries($this->candles(), $this->percent(10), QuoteCurrency::IRR);

        self::assertSame(110.0, $result[0]['open']);
        self::assertSame(121.0, $result[0]['high']);
        self::assertSame(99.0, $result[0]['low']);
        // 105 +10% = 115.5 -> IRR scale 0 ast (rial e ashari nadarim) -> 116.
        self::assertSame(116.0, $result[0]['close']);
    }

    /**
     * Baraye IRR، spread be nazdik tarin RIAL round mishe chun `scale` sefr ast.
     * Ba adad haye vaghei (sad ha milion rial) in round kardan bi-ahamiyat ast،
     * vali in test un raftar ro sabt mikone ta ba'dan kasi fekr nakone bug ast.
     */
    public function test_irr_series_rounds_to_whole_rial(): void
    {
        $candles = [[
            'timestamp' => 1, 'open' => 266_400_000.0, 'high' => 266_760_000.0,
            'low' => 262_237_000.0, 'close' => 266_400_005.0,
        ]];

        $result = SpreadPricer::applySeries($candles, $this->percent(0.5), QuoteCurrency::IRR);

        foreach (['open', 'high', 'low', 'close'] as $field) {
            self::assertSame(
                (float) (int) $result[0][$field],
                $result[0][$field],
                "{$field} bayad rial e sahih bashe.",
            );
        }
    }

    /** Spread nabayad `timestamp` ya `volume` ro dast bezane. */
    public function test_series_preserves_timestamp_and_volume(): void
    {
        $result = SpreadPricer::applySeries($this->candles(), $this->percent(10), QuoteCurrency::IRR);

        self::assertSame(1_000, $result[0]['timestamp']);
        self::assertSame(4.0, $result[0]['volume']);
    }

    /** Spread e darsadi monotone ast -> tartib e `high >= low` hefz mishe. */
    public function test_series_preserves_high_low_ordering(): void
    {
        foreach ([$this->percent(25), $this->percent(-25), $this->amount(1_000)] as $spread) {
            $result = SpreadPricer::applySeries($this->candles(), $spread, QuoteCurrency::IRR);

            foreach ($result as $candle) {
                self::assertGreaterThanOrEqual($candle['low'], $candle['high']);
            }
        }
    }

    /**
     * 🔴 Vorudi mitune daghighan hamun entry e cache bashe. Dastkari ye
     * in-place spread e yek moshtari ro be moshtari ye ba'di nasht mide.
     */
    public function test_series_does_not_mutate_the_input_array(): void
    {
        $original = $this->candles();
        $snapshot = $original;

        SpreadPricer::applySeries($original, $this->percent(10), QuoteCurrency::IRR);

        self::assertSame($snapshot, $original);
    }

    public function test_series_short_circuits_on_neutral_spread(): void
    {
        $candles = $this->candles();

        self::assertSame($candles, SpreadPricer::applySeries($candles, Spread::none(), QuoteCurrency::IRR));
    }

    public function test_series_handles_empty_input(): void
    {
        self::assertSame([], SpreadPricer::applySeries([], $this->percent(10), QuoteCurrency::IRR));
    }

    /**
     * Series e USD bayad ba scale 4 kar kone — vagarna mode e `amount` ru
     * chart va ru tablo do javab e motefavet mide.
     */
    public function test_series_respects_usd_scale_for_amount_spread(): void
    {
        $candles = [[
            'timestamp' => 1_000, 'open' => 4140.19, 'high' => 4142.22,
            'low' => 4135.91, 'close' => 4140.00, 'volume' => 1.0,
        ]];

        $result = SpreadPricer::applySeries($candles, $this->amount(1.5), QuoteCurrency::USD);

        self::assertSame(4141.69, $result[0]['open']);
        self::assertSame(4141.5, $result[0]['close']);
    }

    /**
     * Hamun spread ke ru tablo e'mal mishe bayad ru chart ham hamun javab
     * ro bede — vagarna `close` e akharin candle ba gheymat e tablo fargh
     * mikone va har karbar un ro bug gozaresh mikone.
     */
    public function test_series_and_board_agree_for_the_same_spread(): void
    {
        $spread = $this->percent(2.0);
        $rawMinor = 266_400_000;

        $board = SpreadPricer::apply(Price::irr($rawMinor), $spread);
        $series = SpreadPricer::applySeries(
            [['timestamp' => 1, 'open' => 0.0, 'high' => 0.0, 'low' => 0.0, 'close' => (float) $rawMinor]],
            $spread,
            QuoteCurrency::IRR,
        );

        self::assertSame((float) $board->minor, $series[0]['close']);
    }
}
