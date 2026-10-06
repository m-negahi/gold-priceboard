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
    // Constructor-injection (ghanun e CLAUDE.md؛ `LayerRulesTest` check mikone).
    public function __construct(
        private SeoEditorQuery $query,
        private SaveSeoMetaAction $action,
    ) {}

    public function index(): View
    {
        return view('panel::seo.index', ['pages' => $this->query->pages()]);
    }

    public function update(Request $request, string $page): RedirectResponse
    {
        $this->action->execute($page, $request->only('title', 'description', 'canonical_url', 'robots', 'og_image'));

        return back()->with('success', 'تنظیمات سئو ذخیره شد.');
    }
}
