<?php

declare(strict_types=1);

namespace App\Modules\Common\Helpers;

/**
 * String helper for Persian/Arabic text normalization.
 * - Arabic letters → Persian equivalents
 * - Removes Arabic diacritics (اِعراب)
 * - Persian/Arabic digits → English digits
 */
final class StringHelper
{
    private const CHARACTER_MAP = [
        // Arabic to Persian letters
        'ي' => 'ی',  // Arabic Ya → Persian Ya
        'ك' => 'ک',  // Arabic Kaf → Persian Kaf
        'ة' => 'ه',  // Arabic Ta Marbuta → Persian He
        'ؤ' => 'و',  // Arabic Waw with Hamza → Persian Waw
        'إ' => 'ا',  // Arabic Alef with Hamza Below → Persian Alef
        'أ' => 'ا',  // Arabic Alef with Hamza Above → Persian Alef
        'ٱ' => 'ا',  // Arabic Alef Wasla → Persian Alef

        // Diacritics (اِعراب) → remove
        "\u{064E}" => '',   // فتحه
        "\u{0650}" => '',   // کسره
        "\u{064F}" => '',   // ضمه
        "\u{0652}" => '',   // سکون
        "\u{0651}" => '',   // تشدید
        "\u{064B}" => '',   // تنوین فتح
        "\u{064D}" => '',   // تنوین کسر
        "\u{064C}" => '',   // تنوین ضم

        // Persian numbers → English
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

        // Arabic numbers → English
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

    /**
     * Normalize a string (Arabic → Persian letters + digits → English).
     */
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

    /**
     * Convert Persian/Arabic digits to English digits only.
     */
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
     * Check if a string contains any non-ASCII characters.
     */
    public static function hasNonAscii(string $value): bool
    {
        return preg_match('/[^\x00-\x7F]/', $value) === 1;
    }
}
