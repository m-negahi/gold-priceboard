<?php

declare(strict_types=1);

namespace App\Modules\Media\Exceptions;

use App\Modules\Common\Helpers\Jalali;
use RuntimeException;

/** Hazf-e rasane-i ke hanuz jayi estefade shode rad mishe; payam mige chand ja. */
final class MediaInUseException extends RuntimeException
{
    public static function usedIn(int $count): self
    {
        $fa = Jalali::toPersianDigits((string) $count);

        return new self("این تصویر در {$fa} جا استفاده شده و حذف نمی‌شود؛ ابتدا آن را از صفحه‌ها بردارید.");
    }
}
