<?php

declare(strict_types=1);

namespace App\Modules\Seo\Application\Dto;

use App\Modules\Seo\Domain\Services\JsonLd;

/**
 * Hame ye chizi ke `<head>` e yek safhe ye omumi lazem dare (P5-05).
 *
 * `jsonLd` node haye schema.org-e (bedun e `@context`)؛ layout ba
 * `JsonLd::encode()` yek `<script>` misaze.
 */
final readonly class SeoMetaDto
{
    /** @param  list<array<string, mixed>>  $jsonLd */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public string $robots = 'index, follow',
        public ?string $ogImage = null,
        public string $siteName = '',
        public array $jsonLd = [],
    ) {}

    public function isIndexable(): bool
    {
        return ! str_contains(strtolower($this->robots), 'noindex');
    }

    /** Matn e amn e dakhel e `<script type="application/ld+json">` (bedun e escape e Blade). */
    public function jsonLdScript(): string
    {
        return JsonLd::encode($this->jsonLd);
    }

    /** @param  list<array<string, mixed>>  $jsonLd */
    public function withJsonLd(array $jsonLd): self
    {
        return new self($this->title, $this->description, $this->canonical, $this->robots, $this->ogImage, $this->siteName, $jsonLd);
    }
}
