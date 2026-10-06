<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exceptions;

use RuntimeException;

/** File-i ke fileinfo un ro tasvir-e mojaz nemidoone — pasvand-e ghalat kafi nist. */
final class UnsupportedMediaException extends RuntimeException
{
    public static function mime(string $mime): self
    {
        return new self("نوع فایل «{$mime}» پشتیبانی نمی‌شود؛ فقط JPG، PNG، WebP و SVG.");
    }
}
