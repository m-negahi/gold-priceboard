<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Sath e karbar: `basic` pish-farz; ertegha ba tasmim e sarih e admin
 * (ticket e KYC ya virayesh e audit-dar). Factor e rasmi alave bar in
 * sath, snapshot e ta'eed-shode ye invoice_profile ro ham mikhad.
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
