<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Infrastructure\Support\NumberParser;
use PHPUnit\Framework\TestCase;

/**
 * `NumberParser` khatarnak tarin ghesmat e kol e projeh ast: har eshtebah
 * inja yek gheymat e ghalat ro be Influx mifreste va unja daemi mishe.
 *
 * Policy e separator ha PER-SOURCE ast:
 *   tgju (IRR)  thousands [','] decimal null
 *   tgju (USD)  thousands [','] decimal '.'
 *   estjt (all) thousands ['٫','٬',','] decimal null
 */
final class NumberParserTest extends TestCase
{
    /** Policy e tgju baraye gheymat haye rial. */
    private const TGJU_IRR = [[','], null, 0];

    /** Policy e tgju baraye gheymat haye dollari (ons, mes). */
    private const TGJU_USD = [[','], '.', 4];

    /** Policy e estjt — hame ye gheymat hash sahih ast. */
    private const ESTJT = [['٫', '٬', ','], null, 0];

    private function parse(string $raw, array $policy): ?int
    {
        [$thousands, $decimal, $scale] = $policy;

        return NumberParser::toMinor($raw, $thousands, $decimal, $scale);
    }

    // ── tgju: rial ───────────────────────────────────────────────

    public function test_parses_tgju_rial_with_comma_separators(): void
    {
        self::assertSame(266_400_000, $this->parse('266,400,000', self::TGJU_IRR));
        self::assertSame(1_154_030_000, $this->parse('1,154,030,000', self::TGJU_IRR));
        self::assertSame(2_753_400_000, $this->parse('2,753,400,000', self::TGJU_IRR));
    }

    public function test_parses_tgju_value_without_separators(): void
    {
        // `tgju_gold_irg18` bedun e comma miad.
        self::assertSame(264_921_000, $this->parse('264921000', self::TGJU_IRR));
    }

    /**
     * `mesghal_buy` e tgju vaghe'an `"\t8520000"` ast — ba tab e jolosh.
     * Bedun e paksazi, regex e e'tebarsanji fail mikone va meghdar `null`
     * mishe (ya badtar: jayi `(int)` bokhore va 0 beshe).
     */
    public function test_strips_leading_tab_and_whitespace(): void
    {
        self::assertSame(8_520_000, $this->parse("\t8520000", self::TGJU_IRR));
        self::assertSame(8_520_000, $this->parse('  8,520,000  ', self::TGJU_IRR));
        self::assertSame(8_520_000, $this->parse("\u{00A0}8520000", self::TGJU_IRR));
    }

    // ── tgju: dollar (scale 4) ───────────────────────────────────

    public function test_parses_usd_with_decimal_into_minor_units(): void
    {
        // ons e tala: $4140.19 -> minor ba scale 4
        self::assertSame(41_401_900, $this->parse('4,140.19', self::TGJU_USD));
        // ons e noghre: $60.37
        self::assertSame(603_700, $this->parse('60.37', self::TGJU_USD));
        // mes: $6.492 (har pound)
        self::assertSame(64_920, $this->parse('6.492', self::TGJU_USD));
    }

    public function test_rounds_half_up_beyond_scale(): void
    {
        // 6.49215 -> 64921.5 -> half-up -> 64922
        self::assertSame(64_922, $this->parse('6.49215', self::TGJU_USD));
        // 6.49214 -> 64921.4 -> 64921
        self::assertSame(64_921, $this->parse('6.49214', self::TGJU_USD));
    }

    // ── estjt: raghame Farsi + separator e `٫` ───────────────────

    /**
     * 🔴 Mohem tarin test e in file.
     *
     * `٫` (U+066B) ma'nan separator e ASHAR e Arabi-e, vali estjt un ro
     * HEZARGAN estefade mikone. Agar kasi yek ghanun e global e
     * «U+066B = ashar» bezare, `۲۶٫۶۸۱٫۸۰۰` mishe `26.681` —
     * khata ye yek-milion barabari.
     */
    public function test_treats_arabic_decimal_separator_as_thousands_for_estjt(): void
    {
        // طلا ۱۸ عیار = ۲۶٫۶۸۱٫۸۰۰ toman
        self::assertSame(26_681_800, $this->parse('۲۶٫۶۸۱٫۸۰۰', self::ESTJT));
        // مظنه تهران
        self::assertSame(115_580_000, $this->parse('۱۱۵٫۵۸۰٫۰۰۰', self::ESTJT));
        // سکه طرح جدید
        self::assertSame(275_200_000, $this->parse('۲۷۵٫۲۰۰٫۰۰۰', self::ESTJT));
        // ربع سکه
        self::assertSame(80_000_000, $this->parse('۸۰٫۰۰۰٫۰۰۰', self::ESTJT));
    }

    public function test_converts_persian_and_arabic_digits(): void
    {
        self::assertSame(123_456_789, $this->parse('۱۲۳۴۵۶۷۸۹', self::TGJU_IRR));
        self::assertSame(123_456_789, $this->parse('١٢٣٤٥٦٧٨٩', self::TGJU_IRR));
    }

    /**
     * estjt ons e tala ro ba `$` midе: `"$ ۴۱۴۲"`.
     * Alamat e arz bayad bashe ta parse fail kone va Source khodesh un ro
     * paksazi kone — ma `$` ro saket nadide nemigirim chun momkene jaye
     * digar-i ma'ni dashte bashe.
     */
    public function test_rejects_value_with_currency_symbol(): void
    {
        self::assertNull($this->parse('$ ۴۱۴۲', self::ESTJT));
    }

    // ── gheyr e motabar ──────────────────────────────────────────

    public function test_returns_null_for_unparsable_values(): void
    {
        self::assertNull($this->parse('', self::TGJU_IRR));
        self::assertNull($this->parse('—', self::TGJU_IRR));
        self::assertNull($this->parse('N/A', self::TGJU_IRR));
        self::assertNull($this->parse('abc', self::TGJU_IRR));
        self::assertNull($this->parse('12.34.56.78x', self::TGJU_IRR));
    }

    /**
     * `null` bar migardune, na `0`. Tafavot hayati ast: `0` be onvan e
     * gheymat zakhire mishe va tu chart yek sotun e sefr misaze; `null`
     * ya'ni «rad kon».
     */
    public function test_distinguishes_unparsable_from_zero(): void
    {
        self::assertNull($this->parse('nothing', self::TGJU_IRR));
        self::assertSame(0, $this->parse('0', self::TGJU_IRR));
    }

    // ── tale haye noghte ─────────────────────────────────────────

    public function test_treats_dots_as_thousands_when_source_says_integer(): void
    {
        // Source e'lam karde adad hash sahih ast -> noghte faghat hezargan ast.
        self::assertSame(1_234_567, $this->parse('1.234.567', self::TGJU_IRR));
    }

    public function test_treats_multiple_dots_as_thousands_even_in_decimal_mode(): void
    {
        // "1.234.567" nemitune ashar bashe, hatta vaghti decimal mode roshan ast.
        self::assertSame(12_345_670_000, $this->parse('1.234.567', self::TGJU_USD));
    }

    public function test_handles_negative_values(): void
    {
        self::assertSame(-5_000, $this->parse('-5,000', self::TGJU_IRR));
    }

    public function test_latinize_digits_leaves_other_characters_alone(): void
    {
        self::assertSame('a1b2c3', NumberParser::latinizeDigits('a۱b۲c۳'));
    }
}
