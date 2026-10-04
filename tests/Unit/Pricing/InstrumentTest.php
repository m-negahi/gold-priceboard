<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Metal;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Unit;
use PHPUnit\Framework\TestCase;

/**
 * `Instrument` ghalb e moghayese ast: hamin symbol ro ham tgju mide ham
 * estjt, va daghighan hamin chizi-e ke do ta provider ro ghabl e moghayese
 * mikone. Har na-sazegari inja be hame ja sarayat mikone.
 */
final class InstrumentTest extends TestCase
{
    public function test_symbols_follow_the_canonical_format(): void
    {
        foreach (Instrument::cases() as $instrument) {
            self::assertMatchesRegularExpression(
                '/^[a-z0-9]+(-[a-z0-9]+)*$/',
                $instrument->value,
                "{$instrument->value} bayad kebab e lowercase bashe (URL-safe).",
            );
        }
    }

    public function test_uses_standard_metal_codes(): void
    {
        self::assertSame('xau', Metal::Gold->value);
        self::assertSame('xag', Metal::Silver->value);
        self::assertSame('xcu', Metal::Copper->value);
    }

    public function test_every_instrument_has_a_persian_label(): void
    {
        foreach (Instrument::cases() as $instrument) {
            self::assertNotSame(
                $instrument->value,
                $instrument->label(),
                "{$instrument->value} label e Farsi nadare.",
            );
        }
    }

    /** `scale` hamishe az arz miad — nabayad do manba' e haghighat dashte bashim. */
    public function test_scale_always_matches_the_quote_currency(): void
    {
        foreach (Instrument::cases() as $instrument) {
            self::assertSame($instrument->quote()->scale(), $instrument->scale());
        }
    }

    public function test_usd_quoted_instruments_are_the_global_ones(): void
    {
        $usd = array_filter(
            Instrument::cases(),
            static fn (Instrument $i): bool => $i->quote() === QuoteCurrency::USD,
        );

        self::assertEqualsCanonicalizing(
            [Instrument::GoldOunce, Instrument::SilverOunce, Instrument::CopperPound],
            array_values($usd),
        );
    }

    public function test_units_match_the_instrument_shape(): void
    {
        self::assertSame(Unit::Gram, Instrument::Gold18Gram->unit());
        self::assertSame(Unit::Piece, Instrument::CoinEmami->unit());
        self::assertSame(Unit::Ounce, Instrument::GoldOunce->unit());
        self::assertSame(Unit::Pound, Instrument::CopperPound->unit());
        self::assertSame(Unit::Currency, Instrument::UsdIrr->unit());
    }

    public function test_weights_are_strings_to_keep_bcmath_usable(): void
    {
        self::assertSame('8.133', Instrument::CoinEmami->weightGrams());
        self::assertSame('4.6083', Instrument::GoldMesghal->weightGrams());
        self::assertSame('31.1035', Instrument::GoldOunce->weightGrams());
        self::assertNull(Instrument::UsdIrr->weightGrams());
    }

    /** Nim sekke daghighan nesf e sekke ast, rob' daghighan yek-chaharom. */
    public function test_coin_weights_are_internally_consistent(): void
    {
        $full = (float) Instrument::CoinEmami->weightGrams();

        self::assertEqualsWithDelta($full / 2, (float) Instrument::CoinHalf->weightGrams(), 0.001);
        self::assertEqualsWithDelta($full / 4, (float) Instrument::CoinQuarter->weightGrams(), 0.001);
    }

    /**
     * 🔴 Faghat abshode ye naghdi jofte bid/ask e VAGHEI dare (tgju ba do
     * kelid e zende). Baghi hame moshtagh-and va bayad ba `is_derived` neshun
     * dade shan — vagarna be karbar miguim «nerkh e kharid e folan maghaze»
     * dar hali ke arithmetic e khodemun ast.
     */
    public function test_only_melted_gold_has_native_sides(): void
    {
        $native = array_filter(
            Instrument::cases(),
            static fn (Instrument $i): bool => $i->hasNativeSides(),
        );

        self::assertSame([Instrument::GoldMeltedGram], array_values($native));
    }

    public function test_fast_moving_instruments_have_tight_freshness(): void
    {
        // Dollar va ons taghriban 24/5 moamele mishan.
        self::assertSame(180, Instrument::UsdIrr->freshnessSeconds());
        self::assertSame(180, Instrument::GoldOunce->freshnessSeconds());

        // Noghre 925 mitune yek sa'at nakhore.
        self::assertSame(1800, Instrument::Silver925Gram->freshnessSeconds());
    }

    public function test_base_instrument_is_eighteen_karat_gold(): void
    {
        self::assertSame(Instrument::Gold18Gram, Instrument::base());
    }

    public function test_groups_split_into_metals_plus_fx(): void
    {
        self::assertSame('xau', Instrument::Gold18Gram->group());
        self::assertSame('xag', Instrument::Silver999Gram->group());
        self::assertSame('xcu', Instrument::CopperPound->group());
        self::assertSame('fx', Instrument::UsdIrr->group());
    }

    public function test_options_expose_everything_a_form_needs(): void
    {
        $options = Instrument::options();

        self::assertCount(count(Instrument::cases()), $options);
        self::assertSame(
            ['value', 'label', 'unit', 'quote', 'group', 'icon'],
            array_keys($options[0]),
        );
    }

    /**
     * Har case bayad tu HAR method e `match` pushesh dade shode bashe. Agar
     * kasi case e jadid ezafe kone va yeki az inha ro farāmush kone, PHP
     * `UnhandledMatchError` mide va in test un ro migire — na production.
     */
    public function test_no_instrument_is_missing_from_any_match(): void
    {
        foreach (Instrument::cases() as $instrument) {
            $instrument->metal();
            $instrument->unit();
            $instrument->quote();
            $instrument->purity();
            $instrument->weightGrams();
            $instrument->sanityBand();
            $instrument->freshnessSeconds();
            $instrument->icon();
            $instrument->group();
        }

        self::assertTrue(true, 'hame ye case ha hame ye match ha ro pushesh midan.');
    }
}
