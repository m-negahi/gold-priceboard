<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Media\Application\Queries\MediaQuery;
use App\Modules\Media\Http\Resources\MediaRow;
use App\Modules\Panel\Http\Requests\ProviderSaveRequest;
use App\Modules\Pricing\Application\Actions\ProviderAction;
use App\Modules\Pricing\Application\Dto\ProviderInputDto;
use App\Modules\Pricing\Application\Queries\ProviderPanelQuery;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Domain\Enums\ProviderKind;
use App\Modules\Pricing\Domain\Enums\UpstreamUnit;
use App\Modules\Pricing\Domain\Exceptions\InvalidProviderException;
use App\Modules\Pricing\Infrastructure\Sources\SourceManager;
use Illuminate\Http\JsonResponse;
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
        private MediaQuery $media,
        private ProviderPanelQuery $panel,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->expectsJson()) {
            return new JsonResponse(['health' => $this->panel->liveRows()]);
        }

        return view('panel::providers.index', [
            'rows' => $this->panel->rows(),
            'hasTrustAnchor' => $this->panel->hasTrustAnchor(),
            'liveHealth' => $this->panel->liveRows(),
        ]);
    }

    public function create(): View
    {
        return view('panel::providers.form', [
            'mode' => 'create',
            'values' => [],
            'logo' => $this->logoRow(),
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
        $values = $this->providers->formValues($key);

        return view('panel::providers.form', [
            'mode' => 'edit',
            'values' => $values,
            'logo' => $this->logoRow($values['logo_media_id']),
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

    /**
     * Toggle haye lista — faghat 4 flag e mojaz (`ProviderAction::setFlags`).
     *
     * JSON (darkhast e `panelRequest` e Alpine) ham javab mide ta roshan/
     * khamoosh bedun e reload e safhe anjam she (hamun estesna ye sabt-shode
     * ye safhe haye AJAX e panel — darkhast e sahebe proje، 2026-10-07).
     */
    public function flags(Request $request, string $key): RedirectResponse|JsonResponse
    {
        $provider = $this->providers->findByKeyOrFail($key);

        $flags = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'in_comparison' => ['sometimes', 'boolean'],
            'on_board' => ['sometimes', 'boolean'],
            'in_chart' => ['sometimes', 'boolean'],
            'is_trust_anchor' => ['sometimes', 'boolean'],
        ]);

        $toast = ['kind' => 'success', 'title' => 'اعمال شد', 'message' => 'وضعیت منبع تغییر کرد.'];

        try {
            $this->action->setFlags($provider, array_map(static fn ($v): bool => (bool) $v, $flags));
        } catch (InvalidProviderException $exception) {
            if ($request->expectsJson()) {
                return new JsonResponse([
                    'message' => $exception->getMessage(),
                    'errors' => ['is_trust_anchor' => [$exception->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['is_trust_anchor' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return new JsonResponse(['is_active' => (bool) $provider->refresh()->is_active, 'toast' => $toast]);
        }

        return back()->with('toast', $toast);
    }

    /** @return array<string, mixed>|null */
    private function logoRow(?int $default = null): ?array
    {
        $id = (int) old('logo_media_id', $default);
        $media = $id > 0 ? $this->media->find($id) : null;

        return $media === null ? null : MediaRow::from($media);
    }
}
