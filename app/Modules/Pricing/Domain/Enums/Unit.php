<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Vahed e gheymat-gozari.
 *
 * `gram`  -> gheymat e har geram
 * `piece` -> gheymat e kol e ghet'e (hobab dakhel e gheymat ast — sekke)
 * `ounce` -> ons e troy (31.1035 g) — bazaar e jahani
 * `pound` -> pound (453.592 g) — mes e jahani
 */
enum Unit: string
{
    use EnumTrait;

    case Gram = 'gram';
    case Piece = 'piece';
    case Ounce = 'ounce';
    case Pound = 'pound';
    case Currency = 'currency';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Gram->value => 'گرم',
            self::Piece->value => 'عدد',
            self::Ounce->value => 'انس',
            self::Pound->value => 'پوند',
            self::Currency->value => 'واحد',
        ];
    }
}
