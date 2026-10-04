<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Services\PriceSanity;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use PHPUnit\Framework\TestCase;

/**
 * `PriceSanity` akharin darvaze ghabl az Redis/Postgres/Influx ast.
 * Har meghdar-i ke az inja rad she va be `candles_1d` berese, yek dorugh e
 * daemi mishe ke pak kardanesh `delete` e predicate-i + re-run e task mikhahe.
 */
final class PriceSanityTest extends TestCase
{
    private const MAX_DEVIATION = 10.0;

    private function check(
        Instrument $instrument,
        int $minor,
        ?int $previous = null,
        ?int $pending = null,
        float $maxDeviation = self::MAX_DEVIATION,
    ) {
        return PriceSanity::check(
            $instrument,
            new Price($minor, $instrument->quote()),
            $previous === null ? null : new Price($previous, $instrument->quote()),
            $pending === null ? null : new Price($pending, $instrument->quote()),
            $maxDeviation,
        );
    }

    // ── band e motlagh ───────────────────────────────────────────

    public function test_accepts_a_realistic_price(): void
    {
        $verdict = $this->check(Instrument::Gold18Gram, 266_400_000);

        self::assertTrue($verdict->accepted);
        self::assertNull($verdict->reason);
    }

    public function test_rejects_non_positive_price(): void
    {
        self::assertTrue($this->check(Instrument::Gold18Gram, 0)->rejected());
        self::assertTrue($this->check(Instrument::Gold18Gram, -1)->rejected());
        self::assertSame('non_positive', $this->check(Instrument::Gold18Gram, 0)->reason);
    }

    /**
     * 🔴 Sabt e yek mahdudiat e vaghei, na yek arezu.
     *
     * Band e motlagh khata ye 10x ro NEMIGIRE va nemitune begire: rial onghadr
     * sari tavarrom dare (tala ye 18 ayar ~70 barabar tu 8 sal) ke har band e
     * chand-sale az yek martabe ham pahn tar ast.
     *
     * 26,681,800 (Toman ke eshtebahan Rial hesab shode) dakhel e band mimune.
     * Chizi ke un ro migire `test_deviation_catches_the_toman_mistake()` ast.
     */
    public function test_absolute_band_alone_does_not_catch_a_ten_times_error(): void
    {
        self::assertTrue($this->check(Instrument::Gold18Gram, 26_681_800)->accepted);
        self::assertTrue($this->check(Instrument::Gold18Gram, 2_668_180_000)->accepted);
    }

    /**
     * ...va in mohafez e ASLI ye runtime ast.
     *
     * Khata ye x10 be paeen = 90% enheraf, x10 be bala = 901% —
     * har do az saghf e 10% kheyli balatar-and.
     */
    public function test_deviation_catches_the_toman_mistake(): void
    {
        $real = 266_400_000;

        $tooLow = $this->check(Instrument::Gold18Gram, 26_681_800, previous: $real);
        self::assertTrue($tooLow->rejected());
        self::assertStringStartsWith('deviation', (string) $tooLow->reason);

        $tooHigh = $this->check(Instrument::Gold18Gram, 2_668_180_000, previous: $real);
        self::assertTrue($tooHigh->rejected());
    }

    /**
     * Band e motlagh kar e khodesh ro mikone: mozakhraf e vaghei ro migire —
     * flip e arz ya soton e eshtebah e HTML.
     */
    public function test_absolute_band_catches_outright_nonsense(): void
    {
        // gheymat e ons e dollari (4140) ke eshtebahan be onvan e rial e
        // har geram sabt shode
        self::assertTrue($this->check(Instrument::Gold18Gram, 4_140)->rejected());
        // yek adad e bi-ma'ni az soton e eshtebah
        self::assertTrue($this->check(Instrument::Gold18Gram, 99_999_999_999_999)->rejected());
    }

    /**
     * Band haye USD ba scale 4 hesab mishan — yek eshtebah inja ya'ni
     * hame ye gheymat haye dollari rad mishan (ya hamashun paziresh mishan).
     */
    public function test_usd_bands_use_minor_units(): void
    {
        // $4140.19 -> minor 41,401,900
        self::assertTrue($this->check(Instrument::GoldOunce, 41_401_900)->accepted);
        // $6.492 baraye mes
        self::assertTrue($this->check(Instrument::CopperPound, 64_920)->accepted);
        // ons e tala be ghaymat e mes -> bayad rad she
        self::assertTrue($this->check(Instrument::GoldOunce, 64_920)->rejected());
    }

    // ── enheraf e tick-be-tick ───────────────────────────────────

    public function test_accepts_small_movement(): void
    {
        $verdict = $this->check(Instrument::Gold18Gram, 267_000_000, previous: 266_400_000);

        self::assertTrue($verdict->accepted);
    }

    public function test_rejects_sudden_jump_beyond_deviation_ceiling(): void
    {
        // +50% tu yek tick e 15 saniye-i
        $verdict = $this->check(Instrument::Gold18Gram, 399_600_000, previous: 266_400_000);

        self::assertTrue($verdict->rejected());
        self::assertStringStartsWith('deviation', (string) $verdict->reason);
    }

    /**
     * Vaghti enheraf rad mishe, meghdar bayad NEGAH dashte beshe
     * (`holdAsOutlier`) — vagarna ghanun e ta'eed e tick e ba'di kar nemikone.
     */
    public function test_deviation_rejection_asks_caller_to_hold_the_outlier(): void
    {
        $verdict = $this->check(Instrument::Gold18Gram, 399_600_000, previous: 266_400_000);

        self::assertTrue($verdict->holdAsOutlier);
    }

    /** Khata ye band e motlagh NEGAH dashte nemishe — un parse error ast, na harekat. */
    public function test_out_of_band_rejection_is_not_held(): void
    {
        $verdict = $this->check(Instrument::Gold18Gram, 26_681_800);

        self::assertFalse($verdict->holdAsOutlier);
    }

    // ── ghanun e «do outlier e mote'ali vaghei-and» ──────────────

    /**
     * Yek devaluation e vaghei mitune 12% bezane. Bedun e in ghanun, daghighan
     * vaghti bazaar jaleb mishe ma gheymat ro nemineveshtim.
     *
     * Tick e aval rad mishe va negah dashte mishe; tick e dovom ke hamun hodud
     * ro ta'eed mikone, paziresh mishe.
     */
    public function test_confirmed_outlier_is_accepted_on_the_next_tick(): void
    {
        $previous = 266_400_000;
        $spike = 320_000_000; // ~+20%

        $first = $this->check(Instrument::Gold18Gram, $spike, previous: $previous);
        self::assertTrue($first->rejected());
        self::assertTrue($first->holdAsOutlier);

        // Tick e ba'di hamun hodud ro migē -> harekat vaghei bude.
        $second = $this->check(Instrument::Gold18Gram, $spike + 100_000, previous: $previous, pending: $spike);

        self::assertTrue($second->accepted);
        self::assertTrue($second->confirmedOutlier);
    }

    /**
     * Do meghdar e PARAKANDE ta'eed nistan — un do ta parse error e
     * motefavet-e, na yek harekat e vaghei.
     */
    public function test_scattered_outliers_are_not_treated_as_confirmation(): void
    {
        $verdict = $this->check(
            Instrument::Gold18Gram,
            400_000_000,
            previous: 266_400_000,
            pending: 320_000_000, // kheyli dur az 400M
        );

        self::assertTrue($verdict->rejected());
    }

    // ── halat haye marzi ─────────────────────────────────────────

    /** Tick e aval chizi baraye moghayese nadare — faghat band check mishe. */
    public function test_first_ever_tick_only_checks_the_band(): void
    {
        self::assertTrue($this->check(Instrument::Gold18Gram, 266_400_000, previous: null)->accepted);
    }

    public function test_zero_previous_price_does_not_divide_by_zero(): void
    {
        self::assertTrue($this->check(Instrument::Gold18Gram, 266_400_000, previous: 0)->accepted);
    }

    /**
     * Saghf e enheraf e USD saft tar ast (5%) chun bazaar e jahani aram tar
     * harekat mikone.
     */
    public function test_respects_a_tighter_ceiling_for_usd(): void
    {
        $verdict = $this->check(
            Instrument::GoldOunce,
            44_000_000,           // ~+6.3%
            previous: 41_401_900,
            maxDeviation: 5.0,
        );

        self::assertTrue($verdict->rejected());
    }

    /**
     * Har instrument bayad band dashte bashe — agar case e jadidi ezafe she va
     * `sanityBand()` be-rooz nashe, PHP khodesh `UnhandledMatchError` mide,
     * vali in test un ro sarih mikone.
     */
    public function test_every_instrument_has_a_sane_band(): void
    {
        foreach (Instrument::cases() as $instrument) {
            [$min, $max] = $instrument->sanityBand();

            self::assertGreaterThan(0, $min, "{$instrument->value}: kaf bayad mosbat bashe.");
            self::assertGreaterThan($min, $max, "{$instrument->value}: saghf bayad az kaf bishtar bashe.");
        }
    }
}
