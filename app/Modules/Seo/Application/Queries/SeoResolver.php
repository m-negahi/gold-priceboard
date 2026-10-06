<?php

declare(strict_types=1);

namespace App\Modules\Seo\Application\Queries;

use App\Modules\Seo\Application\Dto\SeoMetaDto;
use App\Modules\Seo\Infrastructure\Persistence\Models\SeoMeta;

/**
 * Pish-farz e code + override e `seo_meta` (P5-05).
 *
 * Har field e override ke khali NIST jaye pish-farz ro migire؛ baghi
 * dast-nakhorde mimunan. Ya'ni admin mitune faghat title ro avaz kone.
 */
final readonly class SeoResolver
{
    public function resolve(string $type, string $id, SeoMetaDto $defaults): SeoMetaDto
    {
        $override = SeoMeta::query()
            ->where('seoable_type', $type)
            ->where('seoable_id', $id)
            ->first();

        if ($override === null) {
            return $defaults;
        }

        return new SeoMetaDto(
            title: $this->pick($override->title, $defaults->title),
            description: $this->pick($override->description, $defaults->description),
            canonical: $this->pick($override->canonical_url, $defaults->canonical),
            robots: $this->pick($override->robots, $defaults->robots),
            ogImage: $this->pick($override->og_image, $defaults->ogImage),
            siteName: $defaults->siteName,
            jsonLd: $defaults->jsonLd,
        );
    }

    /** Override e khali/space-only = pish-farz (na reshte ye khali tu `<title>`). */
    private function pick(?string $value, ?string $default): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? $default : $value;
    }
}
