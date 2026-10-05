<?php

declare(strict_types=1);

namespace Tests\Unit\Common;

use App\Modules\Common\Helpers\PhoneHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Format e ZAKHIRE: E.164 (`+989XXXXXXXXX`).
 *
 * 🔴 Chera daghigh budan e in mohem ast: `users.phone` `unique` ast va
 * hoviat e login ast. Agar yek vorudi do khoruji e motefavet bede، YEK nafar
 * mitune DO hesab dashte bashe — va un ba yek migration dorost nemishe.
 */
final class PhoneHelperTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function sameNumberManyWays(): array
    {
        return [
            'mahalli' => ['09121234567'],
            'bedun e sefr' => ['9121234567'],
            'ba code e keshvar' => ['989121234567'],
            'E.164' => ['+989121234567'],
            'ba 00' => ['00989121234567'],
            'ba fasele' => ['0912 123 4567'],
            'ba khat-tire' => ['0912-123-4567'],
            'ba parantez' => ['(0912) 123 4567'],
            'raghame Farsi' => ['۰۹۱۲۱۲۳۴۵۶۷'],
            'raghame Arabi' => ['٠٩١٢١٢٣٤٥٦٧'],
            'makhlut' => ['+۹۸ ۹۱۲-۱۲۳۴۵۶۷'],
        ];
    }

    /**
     * 🔴 HAME ye in shekl ha bayad be YEK rishte berasan.
     *
     * Har kodum az in ha chizi-e ke yek karbar e vaghei vaghe'an type mikone.
     */
    #[DataProvider('sameNumberManyWays')]
    public function test_every_accepted_shape_normalises_to_one_string(string $input): void
    {
        self::assertSame('+989121234567', PhoneHelper::normalize($input));
        self::assertSame('09121234567', PhoneHelper::toLocal($input));
        self::assertTrue(PhoneHelper::isValid($input));
    }

    /** @return array<string, array{0: ?string}> */
    public static function invalid(): array
    {
        return [
            'null' => [null],
            'khali' => [''],
            'kheyli kutah' => ['0912123456'],
            'kheyli boland' => ['091212345678'],
            'khat e sabet' => ['02128421130'],
            'ba 8 shoru mishe' => ['08121234567'],
            'matn' => ['salam'],
            'faghat code e keshvar' => ['+98'],
            'shomare ye khareji' => ['+14155552671'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_invalid_input_is_rejected(?string $input): void
    {
        self::assertNull(PhoneHelper::normalize($input));
        self::assertNull(PhoneHelper::toLocal($input));
        self::assertFalse(PhoneHelper::isValid($input));
    }

    /**
     * 🔴 Tartib e bardashtan e pishvand: `0098` bayad GHABL az `98` check
     * beshe.
     *
     * Vagarna `00989121234567` be `98` match mishe، do raghame avval
     * bardashte mishe va `989121234567` e baghi-munde yek maghz e GHALAT
     * misaze.
     */
    public function test_the_double_zero_country_prefix_is_stripped_before_the_bare_one(): void
    {
        self::assertSame('+989121234567', PhoneHelper::normalize('00989121234567'));
    }

    public function test_mask_hides_the_middle_digits(): void
    {
        // Shomare data ye SHAKHSI-e: log e khali ye shomare ye kamel ya'ni har
        // kasi ba dastresi be log، lista ye moshtari ha ro dare.
        self::assertSame('+98912***4567', PhoneHelper::mask('+989121234567'));
        self::assertSame('', PhoneHelper::mask(null));
    }

    public function test_tel_link_also_handles_landlines(): void
    {
        // `telLink` amdan narm tar az helper haye mobile-only-e: `021…` maghz
        // e `9XXXXXXXXX` nist vali bayad betune zang bokhore.
        self::assertSame('tel:+982128421130', PhoneHelper::telLink('02128421130'));
        self::assertSame('tel:+989121234567', PhoneHelper::telLink('09121234567'));
        self::assertSame('', PhoneHelper::telLink(null));
        self::assertSame('', PhoneHelper::telLink('salam'));
    }

    /** ⚠️ Bedun e `tel:`، browser href ro masir e nesbi mikhune va zang nemizane. */
    public function test_tel_link_always_carries_the_scheme(): void
    {
        self::assertStringStartsWith('tel:+', PhoneHelper::telLink('۰۹۱۲۱۲۳۴۵۶۷'));
    }

    public function test_pretty_groups_a_tehran_landline(): void
    {
        self::assertSame('021-28421130', PhoneHelper::pretty('02128421130'));
        self::assertSame('09121234567', PhoneHelper::pretty('09121234567'));
    }

    /** Normalize bayad IDEMPOTENT bashe — vagarna ejra ye dobare kharab mikone. */
    public function test_normalising_twice_changes_nothing(): void
    {
        $once = PhoneHelper::normalize('۰۹۱۲۱۲۳۴۵۶۷');

        self::assertSame($once, PhoneHelper::normalize((string) $once));
    }
}
