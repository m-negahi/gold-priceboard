<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Pricing\Application\Actions\ManualPriceAction;
use App\Modules\Pricing\Application\Queries\EffectivePriceQuery;
use App\Modules\Pricing\Application\Queries\ManualPriceQuery;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Services\SourceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gheymat haye DASTI (fallback) — panel e admin (Phase 4/5).
 *
 * Admin gheymat ha ro dasti migzare ya az yek provider seed mikone؛ feed e
 * mo'asser (olaviat + fallback) ham inja neshun dade mishe ba alarm.
 */
final readonly class ManualPriceController
{
    public function __construct(
        private ManualPriceQuery $manual,
        private ManualPriceAction $action,
        private ProviderQuery $providers,
        private EffectivePriceQuery $effective,
    ) {}

    public function index(): View
    {
        // Source haye-i ke mishe azashun seed kard (gheyr e khod e manual).
        $seedSources = [];

        foreach ($this->providers->panelRows() as $row) {
            if ($row['key'] !== 'manual' && ! $row['deleted']) {
                $seedSources[$row['key']] = $row['name'];
            }
        }

        return view('panel::manual.index', [
            'rows' => $this->manual->rows(),
            'seedSources' => $seedSources,
            'effective' => $this->effective->feed(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'value' => ['required', 'numeric', 'min:0'],
        ]);

        $instrument = Instrument::tryFrom((string) $data['symbol']);

        if ($instrument === null) {
            return back()->withErrors(['symbol' => 'نماد ناشناخته است.']);
        }

        $this->action->setFromDisplay($instrument, (float) $data['value'], $this->editor($request));

        return back()->with('toast', [
            'kind' => 'success',
            'title' => 'ثبت شد',
            'message' => $instrument->label().' به‌روز شد.',
        ]);
    }

    public function seed(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:32'],
        ]);

        if (! in_array($data['provider'], SourceManager::drivers(), true)
            && $this->providers->panelRows() === []) {
            return back()->withErrors(['provider' => 'منبع نامعتبر است.']);
        }

        $count = $this->action->seedFromProvider((string) $data['provider'], $this->editor($request));

        return back()->with('toast', [
            'kind' => $count > 0 ? 'success' : 'warning',
            'title' => $count > 0 ? 'پر شد' : 'چیزی پر نشد',
            'message' => $count > 0
                ? "{$count} نماد از منبع انتخابی کپی شد. حالا می‌توانید دستی اصلاح کنید."
                : 'آن منبع هنوز قیمتی ندارد.',
        ]);
    }

    public function remove(Request $request): RedirectResponse
    {
        $instrument = Instrument::tryFrom((string) $request->string('symbol'));

        if ($instrument !== null) {
            $this->action->remove($instrument);
        }

        return back()->with('toast', ['kind' => 'success', 'title' => 'حذف شد', 'message' => 'قیمت دستی برداشته شد.']);
    }

    private function editor(Request $request): ?string
    {
        $user = $request->user();

        return $user?->name ?: $user?->phone;
    }
}
