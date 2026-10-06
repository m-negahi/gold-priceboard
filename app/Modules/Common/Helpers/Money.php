<?php

declare(strict_types=1);

namespace App\Modules\Common\Helpers;

/**
 * Pul e RIAL (minor unit e IRR، scale 0) <-> TOMAN e namayesh — bedun e float.
 *
 * 🔴 Hargez `(float)`/`round()`: adad e rial ta 10^15 miresan va double
 * fraction e 0.5 ro ham kharab mikone. Har do jahat ba string/bcmath.
 */
final class Money
{
    /** Saghf e ma'gul baraye yek mablagh e dasti (10^15 rial = 10^14 toman). */
    private const MAX_TOMAN_DIGITS = 14;

    /**
     * 1_234_565 rial -> «123,456.5» toman. Ragham e ashar faghat vaghti
     * rial e baghi-mande sefr nist.
     */
    public static function rialToToman(int $rial): string
    {
        $sign = $rial < 0 ? '-' : '';
        $abs = (string) abs($rial);
        $toman = strlen($abs) > 1 ? substr($abs, 0, -1) : '0';
        $remainder = substr($abs, -1);

        // Group e hezargan ba string (`number_format` int ro float mikone).
        $grouped = ltrim(strrev(implode(',', str_split(strrev($toman), 3))), ',');

        return $sign.$grouped.($remainder === '0' ? '' : '.'.$remainder);
    }

    /**
     * Vorudi e toman e karbar («1,250,000» / «۱۲۵۰۰۰۰» / «12.5») -> rial.
     * `null` ya'ni vorudi motabar nist (manfi، bish az yek ragham e ashar،
     * horuf، khali، ya kheyli bozorg).
     */
    public static function tomanToRial(?string $input): ?int
    {
        if ($input === null) {
            return null;
        }

        $clean = (string) StringHelper::numbersToEnglish($input);
        // Separator e hezargan (Latin `,` / Farsi `٬` / `،`) va fasele.
        $clean = str_replace([',', '٬', '،', ' ', "\u{200c}"], '', trim($clean));
        $clean = str_replace('٫', '.', $clean);

        if (preg_match('/^(\d{1,'.self::MAX_TOMAN_DIGITS.'})(?:\.(\d))?$/', $clean, $m) !== 1) {
            return null;
        }

        $rial = bcadd(bcmul($m[1], '10', 0), $m[2] ?? '0', 0);

        return (int) $rial;
    }
}
