<?php

declare(strict_types=1);

namespace Tests\Unit\Common;

use App\Modules\Common\Helpers\StringHelper;
use PHPUnit\Framework\TestCase;

/**
 * 🔴 Chera in normalize lazem ast.
 *
 * Karbar e Irani ba keyboard haye motefavet type mikone. `ي` e Arabi va `ی`
 * e Farsi do CODEPOINT e motefavet-and va `=` eshun barabar NIST. Pas bedun
 * e in، jostoju ye «طلای» natije nemide va do radif e «ظریف» tu DB yeki
 * hesab nemishan.
 */
final class StringHelperTest extends TestCase
{
    public function test_arabic_letters_become_persian(): void
    {
        self::assertSame('کیان', StringHelper::normalize('كيان'));
        self::assertSame('مکه', StringHelper::normalize('مكة'));
        self::assertSame('اسماعیل', StringHelper::normalize('إسماعيل'));
    }

    public function test_diacritics_are_removed(): void
    {
        // E'raab (zir/zebar/tashdid) tu matn e type-shode mimune va do matn e
        // zahiran yeksan ro na-barabar mikone.
        self::assertSame('سلام', StringHelper::normalize('سِلاْم'));
        self::assertSame('علی', StringHelper::normalize('عَلي'));
    }

    public function test_persian_and_arabic_digits_become_english(): void
    {
        self::assertSame('1234567890', StringHelper::numbersToEnglish('۱۲۳۴۵۶۷۸۹۰'));
        self::assertSame('1234567890', StringHelper::numbersToEnglish('١٢٣٤٥٦٧٨٩٠'));
        self::assertSame('09121234567', StringHelper::numbersToEnglish('۰۹۱۲۱۲۳۴۵۶۷'));
    }

    /** `numbersToEnglish` bayad FAGHAT ragham ro dast bezane. */
    public function test_numbers_to_english_leaves_letters_alone(): void
    {
        self::assertSame('كيان 123', StringHelper::numbersToEnglish('كيان ۱۲۳'));
    }

    public function test_normalize_handles_letters_and_digits_together(): void
    {
        self::assertSame('کیان 123', StringHelper::normalize('كيان ۱۲۳'));
    }

    public function test_null_and_empty_pass_through(): void
    {
        self::assertNull(StringHelper::normalize(null));
        self::assertSame('', StringHelper::normalize(''));
        self::assertNull(StringHelper::numbersToEnglish(null));
    }

    public function test_ascii_only_input_is_untouched(): void
    {
        // Short-circuit e arzan: aksar e vorudi ha (email، slug، adad) kolan
        // ASCII-and.
        self::assertFalse(StringHelper::hasNonAscii('hello@example.com'));
        self::assertFalse(StringHelper::hasNonAscii('09121234567'));
        self::assertSame('hello@example.com', StringHelper::normalize('hello@example.com'));
    }

    public function test_non_ascii_is_detected(): void
    {
        self::assertTrue(StringHelper::hasNonAscii('طلا'));
        self::assertTrue(StringHelper::hasNonAscii('۱۲۳'));
    }

    /** Normalize bayad IDEMPOTENT bashe. */
    public function test_normalising_twice_changes_nothing(): void
    {
        $once = (string) StringHelper::normalize('كيان ۱۲۳ عَلي');

        self::assertSame($once, StringHelper::normalize($once));
    }

    /**
     * ⚠️ Policy e namayesh: raghame LATIN tu kol e site.
     *
     * Esm e `toPersianDigits` tarikhi-e (call-site ha dast nakhordan) vali
     * raftar esh aks esh ast. Agar ruzi kasi fekr kone esm esh durost ast va
     * ru un hesab kone، in test jolosh ro migire.
     */
    public function test_the_historically_named_helper_now_produces_latin_digits(): void
    {
        self::assertSame('1234', StringHelper::toPersianDigits('۱۲۳۴'));
    }
}
