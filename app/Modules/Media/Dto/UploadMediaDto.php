<?php

declare(strict_types=1);

namespace App\Modules\Media\Dto;

use App\Modules\Common\Dto\Dto;
use Illuminate\Http\UploadedFile;

/** Voroodi-ye upload: file + alt-e ejbari + title-e ekhtiari + ki upload karde. */
final readonly class UploadMediaDto extends Dto
{
    public function __construct(
        public UploadedFile $file,
        public string $alt,
        public ?string $title = null,
        public ?int $uploadedBy = null,
    ) {}
}
