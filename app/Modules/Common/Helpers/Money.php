<?php

declare(strict_types=1);

namespace App\Modules\Common\Helpers;

/** Pul e platform be rial e sahih parse va namayesh dade mishe; hich float ya tabdil e toman nadarim. */
final class Money
{
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
