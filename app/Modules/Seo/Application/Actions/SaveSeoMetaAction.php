<?php

declare(strict_types=1);

namespace App\Modules\Seo\Application\Actions;

use App\Modules\Seo\Infrastructure\Persistence\Models\SeoMeta;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class SaveSeoMetaAction
{
    public function execute(string $page, array $input): void
    {
        abort_unless(in_array($page, array_column(config('seo.pages'), 'route'), true), 404);
        $data = Validator::make($input, [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url:http,https', 'max:2048'],
            'robots' => ['nullable', Rule::in(['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'])],
            'og_image' => ['nullable', 'url:http,https', 'max:2048'],
        ], ['canonical_url.url' => 'نشانی canonical باید با http یا https شروع شود.', 'og_image.url' => 'نشانی تصویر باید با http یا https شروع شود.', 'robots.in' => 'وضعیت ایندکس نامعتبر است.'])->validate();
        SeoMeta::query()->updateOrCreate(['seoable_type' => 'page', 'seoable_id' => $page], $data);
    }
}
