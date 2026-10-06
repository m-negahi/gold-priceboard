<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Panel\Http\Requests\SpreadSaveRequest;
use App\Modules\Pricing\Application\Actions\ProviderSpreadAction;
use App\Modules\Pricing\Application\Dto\SpreadInputDto;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Application\Queries\SpreadQuery;
use App\Modules\Pricing\Domain\Exceptions\InvalidSpreadException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Form e spread e yek provider (P3-07) — nazok: invariant ha tu
 * `ProviderSpreadAction` (band e darsad، symbol e nashenas، revision e
 * faghat-taghir) va preview ba HAMUN `SpreadPricer` e board (server-side،
 * na copy e JS).
 */
final readonly class ProviderSpreadController
{
    public function __construct(
        private ProviderQuery $providers,
        private SpreadQuery $spreads,
        private ProviderSpreadAction $action,
    ) {}

    public function edit(string $key): View
    {
        $values = $this->providers->formValues($key);

        return view('panel::providers.spreads', [
            'providerKey' => $key,
            'providerName' => $values['name_fa'] ?: $values['name'],
            'rows' => $this->spreads->formRows($key),
        ]);
    }

    public function update(SpreadSaveRequest $request, string $key): RedirectResponse
    {
        $provider = $this->providers->findByKeyOrFail($key);

        $rows = array_map(
            static fn (array $row): SpreadInputDto => SpreadInputDto::fromArray($row),
            $request->rows(),
        );

        try {
            $result = $this->action->sync($provider, $rows, changedBy: $this->editor($request));
        } catch (InvalidSpreadException $e) {
            return back()->withErrors(['rows' => $e->getMessage()])->withInput();
        }

        $changed = count($result->created) + count($result->updated);

        return redirect()->route('panel.providers.spreads', $key)->with('toast', [
            'kind' => 'success',
            'title' => 'ذخیره شد',
            'message' => $changed > 0
                ? "{$changed} ردیف تغییر کرد و در دفترچه ثبت شد."
                : 'چیزی تغییر نکرده بود؛ نسخه‌ای هم ثبت نشد.',
        ]);
    }

    /** Hazf e yek override — faghat ba dastur e sarih (sync hazf NEMIKONE). */
    public function remove(Request $request, string $key, string $symbol): RedirectResponse
    {
        $provider = $this->providers->findByKeyOrFail($key);

        try {
            $this->action->remove($provider, $symbol, changedBy: $this->editor($request));
        } catch (InvalidSpreadException $e) {
            return back()->withErrors(['rows' => $e->getMessage()]);
        }

        return back()->with('toast', [
            'kind' => 'success',
            'title' => 'حذف شد',
            'message' => 'override برداشته شد؛ این نماد به پیش‌فرض برمی‌گردد.',
        ]);
    }

    /** Preview e zende — hamun `SpreadPricer`، az rooye akharin gheymat e kham. */
    public function preview(Request $request, string $key): JsonResponse
    {
        $data = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'buy_mode' => ['required', 'in:percent,amount'],
            'buy_value' => ['required', 'numeric'],
            'sell_mode' => ['required', 'in:percent,amount'],
            'sell_value' => ['required', 'numeric'],
        ]);

        // Vojud e provider check mishe ta kelid e ghalat 404 bede، na preview e khali.
        $this->providers->findByKeyOrFail($key);

        return new JsonResponse([
            'preview' => $this->spreads->preview($key, SpreadInputDto::fromArray($data)),
        ]);
    }

    private function editor(Request $request): ?string
    {
        $user = $request->user();

        return $user?->name ?: $user?->phone;
    }
}
