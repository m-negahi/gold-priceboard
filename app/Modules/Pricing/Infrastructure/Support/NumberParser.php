<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Support;

/**
 * Tabdil e string e gheymat e upstream be INT e minor unit.
 *
 * ── Chera bcmath va na float ─────────────────────────────────────────
 * `(float) "266,400,000"` zaheran salem ast, vali `(int) round($f * 10**4)` ru
 * adad haye bozorg mitune 1 vahed khata bede. Ba bcmath dar hich halati
 * daghighat gom nemishe.
 *
 * ── Chera policy e separator PER-SOURCE ast ──────────────────────────
 * 🔴 `٫` (U+066B) MA'NAN separator e ASHAR e Arabi-e, vali site haye Irani
 * (estjt) un ro be onvan e separator e HEZARGAN estefade mikonan.
 * Yek ghanun e global e «U+066B = ashar» `۱٫۲۳۴٫۵۶۷` ro mikone `1.234` →
 * khata ye 1,000,000 barabari. Pas har Source khodesh e'lam mikone ke
 * separator hash chi-e; hich heuristic e khodkari nist.
 *
 * Jadval e policy:
 *   tgju (IRR)  thousands [','] decimal null   (rial sahih ast)
 *   tgju (USD)  thousands [','] decimal '.'    ("3,900.50")
 *   estjt (all) thousands ['٫','٬',','] decimal null
 */
final readonly class NumberParser
{
    /** Raghamha ye Farsi (U+06F0-F9) va Arabi (U+0660-69) → Latin. */
    private const DIGITS = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /**
     * Chiz hayi ke bi-sar-o-seda tu payload e upstream hastan.
     * `\t` vaghe'an tu `mesghal_buy` e tgju hast: `"\t8520000"`.
     */
    private const JUNK = [
        "\t", "\n", "\r", ' ',
        "\u{00A0}", // NBSP
        "\u{200C}", // ZWNJ
        "\u{200E}", "\u{200F}", "\u{061C}", // bidi mark ha
        "\u{FEFF}", // BOM
        "\u{0640}", // tatweel
    ];

    /**
     * @param  array<int, string>  $thousands  separator haye hezargan (bedun e shart hazf mishan)
     * @param  string|null  $decimal  TANHA alamat e ashar, ya null agar adad sahih ast
     * @param  int  $scale  chand raghame ashar tu minor jaa she
     * @return int|null null = ghabl e parse nist (bayad rad she, na sefr)
     */
    public static function toMinor(string $raw, array $thousands, ?string $decimal, int $scale): ?int
    {
        $value = strtr(str_replace(self::JUNK, '', $raw), self::DIGITS);
        $value = str_replace($thousands, '', $value);

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        if ($decimal !== null && $decimal !== '.') {
            $value = str_replace($decimal, '.', $value);
        }

        if ($decimal === null) {
            // Source e'lam karde adad hash sahih ast — pas har noghte-i
            // separator e hezargan bude, na ashar.
            $value = str_replace('.', '', $value);
        } elseif (substr_count($value, '.') > 1) {
            // "1.234.567" nemitune ashar bashe.
            $value = str_replace('.', '', $value);
        }

        if ($value === '' || preg_match('/^\d+(\.\d+)?$/', $value) !== 1) {
            return null;
        }

        // bcmath: zarb dar 10^scale, ba'd round e half-up.
        $scaled = bcmul($value, bcpow('10', (string) $scale), 1);
        $rounded = bcadd($scaled, '0.5', 0);

        $minor = (int) $rounded;

        return $negative ? -$minor : $minor;
    }

    /** Raghamha ye Farsi/Arabi ro be Latin mikone, bedun e hich kar e digar. */
    public static function latinizeDigits(string $value): string
    {
        return strtr($value, self::DIGITS);
    }

    /** @return array<string, string> */
    public static function digitMap(): array
    {
        return self::DIGITS;
    }
}
