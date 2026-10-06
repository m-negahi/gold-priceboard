<?php

declare(strict_types=1);

namespace App\Modules\Common\Helpers;

/**
 * Pul e platform be rial e sahih parse va zakhire mishe؛ hich float.
 *
 * Tanha tabdil e toman، NAMAYESH e tablo ye omumi ast (khaste ye sahebe
 * proje، 2026-10-06): `tomanDivisor()`/`tomanDisplay()`. DB، Redis، Influx،
 * API va panel e admin hamishe rial-and.
 */
final class Money
{
    /** Yek toman = 10 rial. Tanha jayi ke in adad neveshte mishe. */
    public const RIAL_PER_TOMAN = 10;

    /**
     * Zarib e namayesh e TOMAN baraye yek arz e marja' (`'IRR'`/`'USD'`).
     * Tablo ye omumi `divisor` ro be SSR va JS mide ta har do yek adad ro
     * render konan؛ dollar hich tabdili nadare.
     */
    public static function tomanDivisor(string $quote): int
    {
        return $quote === 'IRR' ? self::RIAL_PER_TOMAN : 1;
    }

    /**
     * Rial (int) → toman e GERD-SHODE ba jodakonande ye hezargan — bedun e
     * float، nim-be-bala. `precision` ragham e ashar e toman (0 = sahih).
     */
    public static function tomanDisplay(int $rial, int $precision = 0): string
    {
        $negative = $rial < 0;
        $abs = ltrim((string) $rial, '-');

        // round(abs × 10^p / 10) ba bcmath: floor((2·abs·10^p + 10) / 20)
        $scaled = bcmul($abs, bcpow('10', (string) $precision), 0);
        $rounded = bcdiv(bcadd(bcmul($scaled, '2', 0), (string) self::RIAL_PER_TOMAN, 0), (string) (self::RIAL_PER_TOMAN * 2), 0);

        $rounded = str_pad($rounded, $precision + 1, '0', STR_PAD_LEFT);
        $int = $precision > 0 ? substr($rounded, 0, -$precision) : $rounded;
        $fraction = $precision > 0 ? '.'.substr($rounded, -$precision) : '';

        $grouped = strrev(implode(',', str_split(strrev($int), 3)));
        $isZero = $rounded === str_repeat('0', strlen($rounded));

        return ($negative && ! $isZero ? '-' : '').$grouped.$fraction;
    }

    public static function rialDisplay(int $rial): string
    {
        $sign = $rial < 0 ? '-' : '';
        $digits = ltrim((string) $rial, '-');

        return $sign.strrev(implode(',', str_split(strrev($digits), 3)));
    }

    public static function rialFromInput(?string $input): ?int
    {
        if ($input === null) {
            return null;
        }
        $clean = (string) StringHelper::numbersToEnglish($input);
        $clean = str_replace([',', '٬', '،', ' '], '', trim($clean));
        if (preg_match('/^\d{1,15}$/', $clean) !== 1) {
            return null;
        }

        return (int) $clean;
    }
}
