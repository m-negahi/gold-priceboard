<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Panel\Http\Requests\ProviderSaveRequest;
use App\Modules\Pricing\Application\Actions\ProviderAction;
use App\Modules\Pricing\Application\Dto\ProviderInputDto;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Domain\Enums\ProviderKind;
use App\Modules\Pricing\Domain\Enums\UpstreamUnit;
use App\Modules\Pricing\Domain\Exceptions\InvalidProviderException;
use App\Modules\Pricing\Services\SourceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD e provider — controller e NAZOK: validation e shape tu FormRequest،
 * invariant ha tu `ProviderAction` (key ghofl، driver e sabt-shode، band e
 * zamani). Model faghat az Query gerefte mishe va be Action pass mishe
 * (olgu ye CLAUDE.md e module ha).
 */
final readonly class ProviderController
{
    public function __construct(
        private ProviderQuery $providers,
        private ProviderAction $action,
    ) {}

    public function index(): View
    {
        return view('panel::providers.index', [
            'rows' => $this->providers->panelRows(),
        ]);
    }

    public function create(): View
    {
        return view('panel::providers.form', [
            'mode' => 'create',
            'values' => [],
            'drivers' => SourceManager::drivers(),
            'kinds' => ProviderKind::labels(),
            'units' => UpstreamUnit::labels(),
        ]);
    }

    public function store(ProviderSaveRequest $request): RedirectResponse
    {
        try {
            $this->action->create(ProviderInputDto::fromArray($request->validated()));
        } catch (InvalidProviderException $e) {
            return back()->withErrors(['key' => $e->getMessage()])->withInput();
        }

        return redirect()->route('panel.providers.index')->with('toast', [
            'kind' => 'success',
            'title' => 'منبع ساخته شد',
            'message' => 'طبق روال، خاموش ساخته شد؛ بعد از یک tick سالم روشنش کنید.',
        ]);
    }

    public function edit(string $key): View
    {
        return view('panel::providers.form', [
            'mode' => 'edit',
            'values' => $this->providers->formValues($key),
            'drivers' => SourceManager::drivers(),
            'kinds' => ProviderKind::labels(),
            'units' => UpstreamUnit::labels(),
        ]);
    }

    public function update(ProviderSaveRequest $request, string $key): RedirectResponse
    {
        $provider = $this->providers->findByKeyOrFail($key);

        try {
            // `key` az ROUTE miad، na form — form e virayesh key ro readonly
            // neshun mide va Action ham taghir esh ro rad mikone (ghofl).
            $this->action->update($provider, ProviderInputDto::fromArray(
                array_merge($request->validated(), ['key' => $key]),
            ));
        } catch (InvalidProviderException $e) {
            return back()->withErrors(['key' => $e->getMessage()])->withInput();
        }

        return redirect()->route('panel.providers.index')->with('toast', [
            'kind' => 'success',
            'title' => 'ذخیره شد',
            'message' => 'تنظیمات منبع به‌روز شد.',
        ]);
    }

    /** Toggle haye lista — faghat 4 flag e mojaz (`ProviderAction::setFlags`). */
    public function flags(Request $request, string $key): RedirectResponse
    {
        $provider = $this->providers->findByKeyOrFail($key);

        $flags = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'in_comparison' => ['sometimes', 'boolean'],
            'on_board' => ['sometimes', 'boolean'],
            'in_chart' => ['sometimes', 'boolean'],
        ]);

        $this->action->setFlags($provider, array_map(static fn ($v): bool => (bool) $v, $flags));

        return back()->with('toast', [
            'kind' => 'success',
            'title' => 'اعمال شد',
            'message' => 'وضعیت منبع تغییر کرد.',
        ]);
    }
}
