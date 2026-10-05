<?php

declare(strict_types=1);

namespace App\Modules\Media\Dto;

use App\Modules\Common\Dto\Dto;

/** Virayesh-e matn-haye yek rasane (alt/title); null yani dast nazan. */
final readonly class MediaDto extends Dto
{
    public function __construct(
        public ?string $alt = null,
        public ?string $title = null,
    ) {}
}
