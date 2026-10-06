<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use DomainException;
use PHPUnit\Framework\TestCase;

final class PriceTest extends TestCase
{
    public function test_irr_has_scale_zero_and_usd_has_scale_four(): void
    {
        self::assertSame(0, Price::irr(266_400_000)->scale());
        self::assertSame(4, Price::usd(41_401_900)->scale());
    }

    public function test_converts_minor_to_major_for_display(): void
    {
        self::assertSame(266_400_000.0, Price::irr(266_400_000)->toFloat());
        self::assertSame(4140.19, Price::usd(41_401_900)->toFloat());
        self::assertSame(6.492, Price::usd(64_920)->toFloat());
    }

    /**
     * `display()` (P5-03) — matn e namayeshi BEDUN e float: rial -> toman
     * (÷10) va dollar ba 2 ragham. Gerd kardan nim-be-bala (half-up).
     */
    public function test_display_formats_minor_units_without_float(): void
    {
        self::assertSame('26,640,000', Price::irr(266_400_000)->display());
        self::assertSame('26,640,001', Price::irr(266_400_005)->display());
        self::assertSame('26,640,000', Price::irr(266_400_004)->display());
        self::assertSame('4,140.19', Price::usd(41_401_900)->display());
        self::assertSame('4,140.20', Price::usd(41_401_950)->display());
        self::assertSame('6.49', Price::usd(64_920)->display());
        self::assertSame('0.05', Price::usd(500)->display());
        self::assertSame('0.00', Price::usd(49)->display());
        self::assertSame('0', Price::irr(0)->display());
        self::assertSame('-4,140.20', Price::usd(-41_401_950)->display());
    }

    /**
     * Adad e bozorg tar az 2^53: ba `float` raghame akhar gom mishe —
     * daghighan chizi ke in method baraye jologirish ast.
     */
    public function test_display_keeps_every_digit_of_a_huge_value(): void
    {
        self::assertSame('900,719,925,474,099,321', Price::irr(9_007_199_254_740_993_210)->display());
    }

    /**
     * 🔴 Asli tarin mohafez e in class.
     *
     * Bedun e in، `Price::irr(100)->plus(Price::usd(100))` yek adad e
     * bi-ma'ni mide ke kamelan salem be nazar mirese. Chun minor e har do
     * faghat `int` ast، hich chiz e digar-i jologir nist.
     */
    public function test_rejects_arithmetic_across_currencies(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('IRR');

        Price::irr(100)->plus(Price::usd(100));
    }

    public function test_rejects_comparison_across_currencies(): void
    {
        $this->expectException(DomainException::class);

        Price::irr(100)->isGreaterThan(Price::usd(100));
    }

    public function test_adds_and_subtracts_within_same_currency(): void
    {
        $sum = Price::irr(100)->plus(Price::irr(50));
        self::assertSame(150, $sum->minor);
        self::assertSame(QuoteCurrency::IRR, $sum->quote);

        self::assertSame(50, Price::irr(100)->minus(Price::irr(50))->minor);
    }

    /**
     * `multipliedBy(float)` amdan VOJUD NADARE.
     *
     * `100 * 1.005` tu float mishe `100.49999999999999` va `round()` un ro
     * 100 mikone, na 101 — daghighan hamun khata ye saket-i ke in class
     * baraye jologiri azash sakhte shode. Har zarb e nesbati bayad tu
     * `SpreadPricer` ba mantegh e delta e khodesh anjam she.
     */
    public function test_has_no_float_multiplier(): void
    {
        self::assertFalse(
            method_exists(Price::class, 'multipliedBy'),
            'Price nabayad zarb e float-based dashte bashe — daghighat ro saket az dast mide.',
        );
    }

    public function test_min_and_max_pick_the_right_side(): void
    {
        $low = Price::irr(10);
        $high = Price::irr(20);

        self::assertTrue(Price::min($low, $high)->equals($low));
        self::assertTrue(Price::max($low, $high)->equals($high));
    }

    public function test_equality_requires_same_currency_and_amount(): void
    {
        self::assertTrue(Price::irr(100)->equals(Price::irr(100)));
        self::assertFalse(Price::irr(100)->equals(Price::irr(101)));
        self::assertFalse(Price::irr(100)->equals(Price::usd(100)));
    }

    public function test_change_percent_from_base(): void
    {
        self::assertSame(10.0, Price::irr(110)->changePercentFrom(Price::irr(100)));
        self::assertSame(-10.0, Price::irr(90)->changePercentFrom(Price::irr(100)));
        self::assertSame(0.0, Price::irr(100)->changePercentFrom(Price::irr(100)));
    }

    /** Taghsim bar sefr nabayad kol e tick ro bokoshe. */
    public function test_change_percent_returns_zero_when_base_is_zero(): void
    {
        self::assertSame(0.0, Price::irr(100)->changePercentFrom(Price::irr(0)));
    }

    /**
     * `scale` hamrah e payload mire ta hich reader-i (Redis, JS, API e
     * moshtari) baraye tafsir e `minor` majbur nabashe enum e ma ro bebine.
     */
    public function test_serializes_with_scale_included(): void
    {
        self::assertSame(
            ['minor' => 41_401_900, 'quote' => 'USD', 'scale' => 4],
            Price::usd(41_401_900)->toArray(),
        );
    }

    public function test_round_trips_through_array(): void
    {
        $price = Price::usd(41_401_900);

        self::assertTrue($price->equals(Price::fromArray($price->toArray())));
    }

    public function test_positive_and_zero_checks(): void
    {
        self::assertTrue(Price::irr(1)->isPositive());
        self::assertFalse(Price::irr(0)->isPositive());
        self::assertFalse(Price::irr(-1)->isPositive());
        self::assertTrue(Price::irr(0)->isZero());
    }

    /**
     * Yek gheymat e rial e vaghei bayad kheyli dur az saghf e `bigint`
     * bemune — in test headroom ro sabt mikone ta kasi negaran e overflow
     * nabashe.
     */
    public function test_real_world_rial_values_stay_far_below_int_max(): void
    {
        $mostExpensive = Price::irr(40_000_000_000); // ~saghf e band e sekke

        self::assertLessThan(PHP_INT_MAX / 1_000_000, $mostExpensive->minor);
    }
}
