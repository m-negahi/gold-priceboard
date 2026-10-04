<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Samt e gheymat.
 *
 * `last` -> provider faghat YEK gheymat mide (aksar e mavared).
 * `bid`/`ask` -> provider vaghe'an jofte kharid/foroosh mide.
 *
 * ⚠️ Mohem: `bid`/`ask` FAGHAT vaghti estefade mishe ke upstream khodesh
 * har do ro bede. Gheymat e kharid/foroosh-i ke ma az `last` + spread
 * misazim `last` mimune va ba flag e `is_derived` neshun dade mishe —
 * vagarna be moshtari dorugh gofteim.
 */
enum Side: string
{
    use EnumTrait;

    case Last = 'last';
    case Bid = 'bid';
    case Ask = 'ask';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Last->value => 'آخرین',
            self::Bid->value => 'خرید',
            self::Ask->value => 'فروش',
        ];
    }
}
