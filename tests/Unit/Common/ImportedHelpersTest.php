<?php

declare(strict_types=1);

namespace Tests\Unit\Common;

use App\Modules\Common\Helpers\Jalali;
use App\Modules\Common\Helpers\NationalId;
use App\Modules\Common\Helpers\SlugHelper;
use App\Modules\Common\Helpers\SvgSanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Helper haye integrate-shode az noskhe ye ghabli (Desktop/priceboard،
 * 2026-10-06). Un noskhe test NADASHT — in ha test haye paye-and ta raftar
 * e fe'li mikhkub she؛ pushesh e kamel tar ba masraf e vaghei (Phase 3).
 */
final class ImportedHelpersTest extends TestCase
{
    // ── Jalali ──────────────────────────────────────────────────────

    public function test_jalali_round_trips_todays_date(): void
    {
        self::assertSame('2026-10-06', Jalali::toIso('1405/07/14'));
        self::assertSame('1405/07/14', Jalali::fromIso('2026-10-06'));
    }

    public function test_jalali_accepts_persian_digits_and_dashes(): void
    {
        self::assertSame('2026-10-06', Jalali::toIso('۱۴۰۵-۰۷-۱۴'));
    }

    /**
     * 🔴 Taghir e amdi نسبت be noskhe ye ghabli: default e in proje raghame
     * LATIN ast (policy e `LatinizeDigits`) — un noskhe default esh Farsi bud.
     */
    public function test_jalali_outputs_latin_digits_by_default(): void
    {
        $formatted = (string) Jalali::date(new \DateTimeImmutable('2026-10-06'));

        self::assertMatchesRegularExpression('/^[0-9\/]+$/', $formatted);
        self::assertSame('1405/07/14', $formatted);
    }

    public function test_jalali_rejects_garbage(): void
    {
        self::assertNull(Jalali::toIso('1405/13/01'));
        self::assertNull(Jalali::toIso('salam'));
        self::assertNull(Jalali::toIso(null));
        self::assertNull(Jalali::parse('1405/12/31')); // 1405 kabise nist
    }

    public function test_jalali_leap_year_table(): void
    {
        self::assertTrue(Jalali::isLeapYear(1403));
        self::assertFalse(Jalali::isLeapYear(1405));
        self::assertSame(30, Jalali::daysInMonth(1403, 12));
        self::assertSame(29, Jalali::daysInMonth(1405, 12));
    }

    // ── NationalId ──────────────────────────────────────────────────

    public function test_a_valid_personal_code_passes_even_in_persian_digits(): void
    {
        self::assertTrue(NationalId::isValidPerson('0499370899'));
        self::assertTrue(NationalId::isValidPerson('۰۴۹۹۳۷۰۸۹۹'));
    }

    public function test_checksum_and_shape_failures_are_rejected(): void
    {
        self::assertFalse(NationalId::isValidPerson('0499370898')); // checksum
        self::assertFalse(NationalId::isValidPerson('1111111111')); // tekrari
        self::assertFalse(NationalId::isValidPerson('123'));
        self::assertFalse(NationalId::isValidPerson(null));
    }

    // ── SlugHelper ──────────────────────────────────────────────────

    public function test_persian_slugs_keep_their_letters(): void
    {
        self::assertSame('طلای-18-عیار-قیمت', SlugHelper::make('طلای ۱۸ عیار — قیمت!'));
    }

    /** ZWNJ bayad joda-konande bemune: «می‌شود» do kalame-ast، na yeki. */
    public function test_zwnj_becomes_a_separator(): void
    {
        self::assertSame('می-شود', SlugHelper::make("می\u{200C}شود"));
    }

    public function test_accented_latin_is_transliterated(): void
    {
        self::assertSame('cafe-francais', SlugHelper::make('Café Français'));
        self::assertSame('strasse', SlugHelper::make('Straße'));
    }

    public function test_reserved_slugs_are_flagged(): void
    {
        self::assertTrue(SlugHelper::isReserved('admin'));
        self::assertFalse(SlugHelper::isReserved('talaye-18'));
    }

    // ── 🔴 SvgSanitizer (amniati) ───────────────────────────────────

    public function test_scripts_and_handlers_are_stripped(): void
    {
        $dirty = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)">'
            .'<script>alert(2)</script>'
            .'<a href="javascript:alert(3)"><rect onclick="x()" width="1"/></a></svg>';

        $clean = SvgSanitizer::clean($dirty);

        self::assertStringNotContainsString('script', $clean);
        self::assertStringNotContainsString('onload', $clean);
        self::assertStringNotContainsString('onclick', $clean);
        self::assertStringNotContainsString('javascript:', $clean);
        self::assertStringContainsString('<rect', $clean, 'mohtava ye salem bayad bemune.');
    }

    public function test_foreignobject_and_data_urls_are_stripped(): void
    {
        // ⚠️ `<` dakhel e attribute tu XML gheyr e mojaz e — payload bayad
        // XML e MOTABAR bashe ta masir e strip test she، na masir e reject.
        $dirty = '<svg xmlns="http://www.w3.org/2000/svg">'
            .'<foreignObject><body xmlns="http://www.w3.org/1999/xhtml">x</body></foreignObject>'
            .'<image href="data:text/html;base64,PHNjcmlwdD4="/></svg>';

        $clean = SvgSanitizer::clean($dirty);

        self::assertStringNotContainsStringIgnoringCase('foreignobject', $clean);
        self::assertStringNotContainsString('data:', $clean);
    }

    /**
     * SVG e MALFORMED (XML e kharab) kolan rad mishe — upload fail mishe،
     * na inke «har chi tunestim» pak konim: sanitize ye nesfe-nime khodesh
     * yek rakhne-ast (mXSS az masir e re-parse).
     */
    public function test_malformed_xml_is_refused_not_half_cleaned(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SvgSanitizer::clean('<svg><image href="data:text/html,<script>1</script>"/></svg>');
    }

    /** Href e amn (#id va http) bayad BEMUNE — sanitize nabayad hame chiz ro bokoshe. */
    public function test_safe_references_survive(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
            .'<use href="#icon"/><a href="https://example.com"><rect width="1"/></a></svg>';

        $clean = SvgSanitizer::clean($svg);

        self::assertStringContainsString('#icon', $clean);
        self::assertStringContainsString('https://example.com', $clean);
    }

    public function test_non_svg_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SvgSanitizer::clean('<html><body>na</body></html>');
    }
}
