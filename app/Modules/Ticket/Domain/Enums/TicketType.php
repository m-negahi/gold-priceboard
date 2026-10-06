<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Noe e ticket (P7-01). `kyc_upgrade`/`site_approval` «tasmim» mikhan
 * (ta'eed/rad e admin)؛ `support` faghat goft-o-gu ast va baste mishe.
 */
enum TicketType: string
{
    use EnumTrait;

    case KycUpgrade = 'kyc_upgrade';
    case SiteApproval = 'site_approval';
    case Support = 'support';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::KycUpgrade->value => 'ارتقای سطح',
            self::SiteApproval->value => 'تأیید سایت',
            self::Support->value => 'پشتیبانی',
        ];
    }

    /** Ticket-i ke natije esh ta'eed/rad e admin ast (na faghat «baste shod»). */
    public function needsDecision(): bool
    {
        return $this !== self::Support;
    }
}
