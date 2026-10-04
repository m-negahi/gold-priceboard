<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Support;

use Normalizer;

/**
 * Yeksan-sazi ye matn e Farsi baraye MATCH kardan (na baraye namayesh).
 *
 * ── Chera lazem ast ──────────────────────────────────────────────────
 * Ba'zi Source ha (estjt) kelid eshun label e Farsi ye dakhel e HTML ast.
 * Un label mitune har kodum az inha ro dashte bashe:
 *   - `ي`/`ك` e Arabi jaye `ی`/`ک` e Farsi
 *   - ZWNJ (U+200C) dakhel e «طلای»
 *   - tatweel, e'rab, NBSP, faseleh ye dotaei
 *   - raghame Farsi tu «طلا ۱۸ عیار»
 *
 * Har kodum az inha match ro mishkane va un instrument saket nadide gerefte
 * mishe. `fold()` hame ro be yek shekl e vahed mirune.
 *
 * ⚠️ `Str::slug()` baraye in kar MAMNU' ast — Farsi ro kolan hazf mikone va
 * string e khali bar migardune.
 *
 * ⚠️ Khoruji e `fold()` faghat baraye kelid e array ast. Baraye namayesh
 * hamishe label e asli estefade she.
 */
final readonly class PersianText
{
    /** Shekl haye Arabi → Farsi. */
    private const LETTERS = [
        'ي' => 'ی',
        'ك' => 'ک',
        'ۀ' => 'ه',
        'ة' => 'ه',
        'أ' => 'ا',
        'إ' => 'ا',
        'آ' => 'ا',
        'ؤ' => 'و',
        'ء' => '',
    ];

    /** E'rab, tatweel, va mark haye na-mar'i. */
    private const STRIP_PATTERN = '/[\x{064B}-\x{065F}\x{0670}\x{0640}\x{200C}\x{200D}\x{200E}\x{200F}\x{061C}\x{FEFF}]/u';

    public static function fold(string $value): string
    {
        $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        $value = strtr($value, self::LETTERS);
        $value = preg_replace(self::STRIP_PATTERN, '', $value) ?? $value;
        $value = NumberParser::latinizeDigits($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
