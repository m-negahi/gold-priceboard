<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Vaziat e hesab e karbar (P6-01).
 *
 * `disabled` = na login (session haye baz ham mioftan)، na API (hame ye
 * site hash 403) — PLAN «Karbar e disable = na login، na API».
 */
enum UserStatus: string
{
    use EnumTrait;

    case Active = 'active';
    case Disabled = 'disabled';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Active->value => 'فعال',
            self::Disabled->value => 'غیرفعال',
        ];
    }
}
