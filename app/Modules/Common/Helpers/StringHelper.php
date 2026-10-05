<?php

declare(strict_types=1);

namespace App\Modules\Common\Helpers;

/**
 * Normalize kardan e matn e Farsi/Arabi.
 *
 *   - horoof e Arabi -> mo'adel e Farsi (`ي→ی`، `ك→ک`)
 *   - hazf e e'raab e Arabi (zir/zebar/tashdid)
 *   - raghamha ye Farsi/Arabi -> English
 *
 * 🔴 Chera lazem ast: karbar e Irani ba keyboard haye motefavet type mikone.
 * `ي` e Arabi va `ی` e Farsi do CODEPOINT e motefavet-and va `=` eshun
 * barabar nist — pas bedun e in، jostoju ye «طلای» natije nemide va do
 * radif e «ظریف» tu DB yeki hesab nemishan.
 */
final class StringHelper
{
    private const CHARACTER_MAP = [
        // Horoof e Arabi -> Farsi
        'ي' => 'ی',  // Ya ye Arabi -> Ya ye Farsi
        'ك' => 'ک',  // Kaf e Arabi -> Kaf e Farsi
        'ة' => 'ه',  // Ta ye marbute -> He
        'ؤ' => 'و',  // Vav ba hamze -> Vav
        'إ' => 'ا',  // Alef ba hamze (zir) -> Alef
        'أ' => 'ا',  // Alef ba hamze (bala) -> Alef
        'ٱ' => 'ا',  // Alef e vasle -> Alef

        // E'raab -> hazf
        "\u{064E}" => '',   // فتحه
        "\u{0650}" => '',   // کسره
        "\u{064F}" => '',   // ضمه
        "\u{0652}" => '',   // سکون
        "\u{0651}" => '',   // تشدید
        "\u{064B}" => '',   // تنوین فتح
        "\u{064D}" => '',   // تنوین کسر
        "\u{064C}" => '',   // تنوین ضم

        // Raghame Farsi -> English
        '۰' => '0',
        '۱' => '1',
        '۲' => '2',
        '۳' => '3',
        '۴' => '4',
        '۵' => '5',
        '۶' => '6',
        '۷' => '7',
        '۸' => '8',
        '۹' => '9',

        // Raghame Arabi -> English
        '٠' => '0',
        '١' => '1',
        '٢' => '2',
        '٣' => '3',
        '٤' => '4',
        '٥' => '5',
        '٦' => '6',
        '٧' => '7',
        '٨' => '8',
        '٩' => '9',
    ];

    private const NUMBER_MAP = [
        '۰' => '0',
        '۱' => '1',
        '۲' => '2',
        '۳' => '3',
        '۴' => '4',
        '۵' => '5',
        '۶' => '6',
        '۷' => '7',
        '۸' => '8',
        '۹' => '9',
        '٠' => '0',
        '١' => '1',
        '٢' => '2',
        '٣' => '3',
        '٤' => '4',
        '٥' => '5',
        '٦' => '6',
        '٧' => '7',
        '٨' => '8',
        '٩' => '9',
    ];

    /** Normalize e kamel: horoof e Arabi -> Farsi + ragham -> English. */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! self::hasNonAscii($value)) {
            return $value;
        }

        return strtr($value, self::CHARACTER_MAP);
    }

    /** FAGHAT ragham — horoof dast nemikhoran. */
    public static function numbersToEnglish(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! self::hasNonAscii($value)) {
            return $value;
        }

        return strtr($value, self::NUMBER_MAP);
    }

    /**
     * Policy e namayesh: raghame Latin tu kol e site. Esm e tarikhi hefz shode
     * ta call-site ha dast nakhoran; hala faghat raghamha ye Farsi/Arabi ro
     * Latin mikone.
     */
    public static function toPersianDigits(?string $value): string
    {
        return (string) self::numbersToEnglish($value);
    }

    /**
     * Aya hich character e gheyr e ASCII dare?
     *
     * Yek short-circuit e arzan: aksar e vorudi ha (email، adad، slug) kolan
     * ASCII-and va hich normalize-i lazem nadaran.
     */
    public static function hasNonAscii(string $value): bool
    {
        return preg_match('/[^\x00-\x7F]/', $value) === 1;
    }
}
