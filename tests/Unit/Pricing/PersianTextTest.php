<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Infrastructure\Support\PersianText;
use PHPUnit\Framework\TestCase;

/**
 * `fold()` tanha chizi-e ke beyn e label e Farsi ye estjt va map e ma
 * mi-neshine. Har halat-i ke fold nashe, un instrument saket nadide gerefte
 * mishe — bedun e error, faghat yek radif e kam tu tablo.
 */
final class PersianTextTest extends TestCase
{
    public function test_normalizes_arabic_letters_to_persian(): void
    {
        self::assertSame('طلای', PersianText::fold('طلاي'));
        self::assertSame('سکه', PersianText::fold('سكه'));
    }

    /**
     * «طلای ۲۴ عیار» tu HTML e estjt ZWNJ dare. Bedun e hazf esh، string e
     * ma ba kelid e map yeki nemishe hatta agar daghighan hamun be nazar berese.
     */
    public function test_strips_zero_width_non_joiner(): void
    {
        $withZwnj = "طلا\u{200C}ی ۲۴ عیار";

        self::assertSame('طلای 24 عیار', PersianText::fold($withZwnj));
    }

    public function test_converts_persian_digits_to_latin(): void
    {
        self::assertSame('طلا 18 عیار', PersianText::fold('طلا ۱۸ عیار'));
    }

    public function test_collapses_whitespace_and_trims(): void
    {
        self::assertSame('نیم سکه', PersianText::fold("  نیم   سکه \n"));
        self::assertSame('نیم سکه', PersianText::fold("نیم\u{00A0}سکه"));
    }

    public function test_strips_tatweel_and_diacritics(): void
    {
        self::assertSame('سکه', PersianText::fold('سـکـه'));
        self::assertSame('سکه', PersianText::fold('سَکِه'));
    }

    /**
     * Hame ye 9 label e vaghei ye estjt bayad be yek shekl e payedar fold
     * shan — in daghighan hamun kelid hayi-e ke `EstjtQuoteSource::MAP`
     * estefade mikone.
     */
    public function test_folds_every_real_estjt_label(): void
    {
        $expected = [
            'انس طلا' => 'انس طلا',
            'مظنه تهران' => 'مظنه تهران',
            'طلا ۱۸ عیار' => 'طلا 18 عیار',
            'طلای ۲۴ عیار' => 'طلای 24 عیار',
            'سکه طرح جدید' => 'سکه طرح جدید',
            'سکه طرح قدیم' => 'سکه طرح قدیم',
            'نیم سکه' => 'نیم سکه',
            'ربع سکه' => 'ربع سکه',
            'سکه گرمی' => 'سکه گرمی',
        ];

        foreach ($expected as $raw => $folded) {
            self::assertSame($folded, PersianText::fold($raw), "fold e «{$raw}» entezar nemirafte.");
        }
    }

    public function test_fold_is_idempotent(): void
    {
        $once = PersianText::fold('طلاي ۱۸ عیار');

        self::assertSame($once, PersianText::fold($once));
    }
}
