<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Vahed-i ke UPSTREAM gheymat ro ba un mide (ghabl az normalize).
 *
 * 🔴 In meghdar PER-INSTRUMENT ast, na per-provider.
 * estjt «انس طلا» ro USD mide vali 8 instrument e digar esh ro TOMAN.
 * Yek `upstream_unit = toman` e provider-level ons ro x10 mikone va
 * `$39,005` dar miare — onghadr bavar-pazir ke az zir e cheshm rad beshe
 * va HAR point e Influx ro masmum kone.
 *
 * `none` ya'ni hich tabdili lazem nist (gheymat ghablan tu arz e marja' ast).
 */
enum UpstreamUnit: string
{
    use EnumTrait;

    case Rial = 'rial';
    case Toman = 'toman';
    case None = 'none';

    /** Zarib baraye tabdil be vahed e asli ye arz (rial baraye IRR). */
    public function multiplier(): int
    {
        return match ($this) {
            self::Rial, self::None => 1,
            self::Toman => 10,
        };
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Rial->value => 'ریال',
            self::Toman->value => 'تومان',
            self::None->value => 'بدون تبدیل',
        ];
    }
}
