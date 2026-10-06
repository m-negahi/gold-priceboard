<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Vaziat e ticket (P7-01) + gozar haye MOJAZ.
 *
 * `approved`/`rejected` «tasmim» hastan: faghat ru noe e tasmim-dar
 * (`TicketType::needsDecision`) va faghat az masir e ta'eed/rad (P7-06) —
 * taghir e vaziat e omumi (`ChangeTicketStatusAction`) un ha ro rad mikone
 * ta level e karbar bedun e masir e ta'eed avaz nashe va natije ye tasmim
 * bedun e audit e kamel sabt nashe.
 *
 * Tasmim gereft-e shode bargasht-pazir nist (approved/rejected → faghat
 * closed): darkhast e dobare = ticket e jadid، ta tarikhche ye har tasmim
 * dast-nakhorde bemune.
 */
enum TicketStatus: string
{
    use EnumTrait;

    case Open = 'open';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Closed = 'closed';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Open->value => 'باز',
            self::InReview->value => 'در حال بررسی',
            self::Approved->value => 'تأیید شد',
            self::Rejected->value => 'رد شد',
            self::Closed->value => 'بسته',
        ];
    }

    /** @return list<self> */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Open => [self::InReview, self::Approved, self::Rejected, self::Closed],
            self::InReview => [self::Open, self::Approved, self::Rejected, self::Closed],
            self::Approved, self::Rejected => [self::Closed],
            self::Closed => [self::Open],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->allowedTargets(), true);
    }

    public function isDecision(): bool
    {
        return $this === self::Approved || $this === self::Rejected;
    }

    /** Hanuz montazer e tasmim/kar e admin. */
    public function isPending(): bool
    {
        return $this === self::Open || $this === self::InReview;
    }

    /** Tone e pill e panel (`x-panel.pill`). */
    public function tone(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::InReview => 'warn',
            self::Approved => 'ok',
            self::Rejected => 'down',
            self::Closed => 'flat',
        };
    }
}
