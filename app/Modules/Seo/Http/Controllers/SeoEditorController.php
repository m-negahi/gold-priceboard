<?php

declare(strict_types=1);

namespace App\Modules\Seo\Http\Controllers;

use App\Modules\Seo\Application\Actions\SaveSeoMetaAction;
use App\Modules\Seo\Application\Queries\SeoEditorQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class SeoEditorController
{
    public function index(SeoEditorQuery $query): View
    {
        return view('panel::seo.index', ['pages' => $query->pages()]);
    }

    public function update(Request $request, string $page, SaveSeoMetaAction $action): RedirectResponse
    {
        $action->execute($page, $request->only('title', 'description', 'canonical_url', 'robots', 'og_image'));

        return back()->with('success', 'تنظیمات سئو ذخیره شد.');
    }
}
