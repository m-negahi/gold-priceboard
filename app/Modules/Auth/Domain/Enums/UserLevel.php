<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Sath e karbar (P6-01): `basic` pish-farz؛ `invoice_verified` faghat ba
 * ta'eed e admin ru ticket e KYC (Phase 7) — factor e rasmi faghat baraye
 * in sath.
 */
enum UserLevel: string
{
    use EnumTrait;

    case Basic = 'basic';
    case InvoiceVerified = 'invoice_verified';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Basic->value => 'پایه',
            self::InvoiceVerified->value => 'تأییدشده برای فاکتور',
        ];
    }
}
