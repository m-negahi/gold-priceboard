<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Domain\Exceptions;

use RuntimeException;

/**
 * Khata haye ticket — PAYAM e Farsi baraye UI (form error)، chun har kodum
 * mostaghim be karbar/admin neshun dade mishe. `code` noe ro moshakhas mikone.
 */
final class TicketException extends RuntimeException
{
    public const CLOSED = 1;

    public const TRANSITION = 2;

    public const DECISION_ROUTE = 3;

    public const SAME_STATUS = 4;

    public const NOT_DECIDABLE = 5;

    public const PENDING_EXISTS = 6;

    public const FILE_TYPE = 7;

    public static function fileType(): self
    {
        return new self('نوع فایل مجاز نیست (فقط PDF، JPG، PNG، WEBP).', self::FILE_TYPE);
    }

    public static function closed(): self
    {
        return new self('این تیکت بسته است؛ برای ادامه تیکت را دوباره باز کنید.', self::CLOSED);
    }

    public static function transition(string $from, string $to): self
    {
        return new self("تغییر وضعیت از «{$from}» به «{$to}» مجاز نیست.", self::TRANSITION);
    }

    public static function decisionRoute(): self
    {
        return new self('تأیید یا رد فقط از بخش تصمیم همین تیکت ممکن است.', self::DECISION_ROUTE);
    }

    public static function sameStatus(): self
    {
        return new self('تیکت از قبل در همین وضعیت است.', self::SAME_STATUS);
    }

    public static function notDecidable(): self
    {
        return new self('این نوع تیکت تأیید/رد ندارد.', self::NOT_DECIDABLE);
    }

    public static function pendingExists(): self
    {
        return new self('یک درخواست ارتقای سطح در حال بررسی دارید.', self::PENDING_EXISTS);
    }
}
