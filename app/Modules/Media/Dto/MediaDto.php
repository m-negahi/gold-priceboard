<?php

declare(strict_types=1);

namespace App\Modules\Media\Dto;

/**
 * Virayesh-e matn-haye yek rasane (alt/title); null yani dast nazan.
 *
 * ⚠️ `extends Dto` hazf shod — fatal e readonly-extends-non-readonly؛ hich
 * chiz az base masraf nemishod.
 */
final readonly class MediaDto
{
    public function __construct(
        public ?string $alt = null,
        public ?string $title = null,
    ) {}
}
