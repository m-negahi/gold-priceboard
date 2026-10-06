<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exceptions;

use RuntimeException;

/**
 * Khata haye modiriat e karbar tu panel (Phase 6) — payam ha FARSI-and
 * chun mostaghim be ADMIN tu form neshun dade mishan. `field` = kodum
 * field e form khata ro migire.
 */
final class UserAdminException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'phone')
    {
        parent::__construct($message);
    }

    public static function invalidPhone(): self
    {
        return new self('شمارهٔ موبایل معتبر نیست.', 'phone');
    }

    public static function duplicatePhone(): self
    {
        return new self('کاربری با این شمارهٔ موبایل از قبل وجود دارد.', 'phone');
    }

    public static function selfDemotion(): self
    {
        return new self('نمی‌توانید نقش ادمین را از خودتان بگیرید.', 'role');
    }

    public static function lastAdmin(): self
    {
        return new self('حداقل یک ادمین فعال باید باقی بماند.', 'role');
    }

    public static function selfDisable(): self
    {
        return new self('نمی‌توانید حساب خودتان را غیرفعال کنید.', 'reason');
    }
}
