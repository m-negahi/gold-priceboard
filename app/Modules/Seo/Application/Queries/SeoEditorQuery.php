<?php

declare(strict_types=1);

namespace App\Modules\Seo\Application\Queries;

use App\Modules\Seo\Infrastructure\Persistence\Models\SeoMeta;

final readonly class SeoEditorQuery
{
    public function pages(): array
    {
        $overrides = SeoMeta::query()->where('seoable_type', 'page')->get()->keyBy('seoable_id');

        return array_map(static fn (array $page): array => [
            'key' => $page['route'], 'label' => $page['route'] === 'home' ? 'صفحه اصلی' : $page['route'],
            'values' => $overrides->get($page['route'])?->only(['title', 'description', 'canonical_url', 'robots', 'og_image']) ?? [],
        ], config('seo.pages'));
    }
}
