<?php

declare(strict_types=1);

namespace App\Modules\Media\Dto;

use Illuminate\Http\UploadedFile;

/**
 * Voroodi-ye upload: file + alt-e ejbari + title-e ekhtiari + ki upload karde.
 *
 * ⚠️ `extends Dto` hazf shod — fatal e readonly-extends-non-readonly؛ hich
 * chiz az base masraf nemishod.
 */
final readonly class UploadMediaDto
{
    public function __construct(
        public UploadedFile $file,
        public string $alt,
        public ?string $title = null,
        public ?int $uploadedBy = null,
    ) {}
}
