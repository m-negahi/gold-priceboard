<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Ravesh e e'mal e spread.
 *
 * `percent` -> darsad e hamun samt e gheymat e kham
 * `amount`  -> meghdar e sabet tu vahed e ASLI ye arz (rial / dollar)
 *
 * Meghdar hamishe ELAMATDAR ast: manfi kam mikone. Soton e jodagane baraye
 * jahat nadarim chun `+`/`-` tu form khanatar az yek select ast.
 */
enum SpreadMode: string
{
    use EnumTrait;

    case Percent = 'percent';
    case Amount = 'amount';

    public function symbol(): string
    {
        return $this === self::Percent ? '٪' : 'مبلغ';
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Percent->value => 'درصدی',
            self::Amount->value => 'مبلغ ثابت',
        ];
    }
}
