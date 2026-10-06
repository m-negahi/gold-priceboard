<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/** Noe e moshtari (P6-01): haghighi (code melli 10 ragham) ya hoghoghi (shenase melli 11 ragham). */
enum CustomerType: string
{
    use EnumTrait;

    case Individual = 'individual';
    case Legal = 'legal';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Individual->value => 'حقیقی',
            self::Legal->value => 'حقوقی',
        ];
    }
}
