<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Api\Application\Actions\ApiPlanAction;
use App\Modules\Api\Application\Queries\ApiPlanQuery;
use App\Modules\Common\Helpers\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final readonly class ApiPlanController
{
    public function __construct(private ApiPlanQuery $plans, private ApiPlanAction $actions) {}

    public function index(): View
    {
        return view('panel::plans.index', ['plans' => $this->plans->all()]);
    }

    public function create(): View
    {
        return view('panel::plans.form', ['plan' => null]);
    }

    public function edit(int $plan): View
    {
        return view('panel::plans.form', ['plan' => $this->plans->findOrFail($plan)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->actions->create($data);

        return redirect()->route('panel.plans.index');
    }

    public function update(Request $request, int $plan): RedirectResponse
    {
        $data = $this->validated($request, $plan);
        $this->actions->update($plan, $data);

        return redirect()->route('panel.plans.index');
    }

    public function archive(int $plan): RedirectResponse
    {
        $this->actions->archive($plan);

        return redirect()->route('panel.plans.index');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9-]+$/', Rule::unique('api_plans', 'key')->ignore($id)],
            'name' => ['required', 'string', 'max:120'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'calls_included' => ['required', 'integer', 'min:0'],
            'calls_per_day' => ['required', 'integer', 'min:0'],
            'price_rial' => ['required', 'string', 'max:32'],
            'cost_per_call_rial' => ['required', 'string', 'max:32'],
            'sort' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $price = Money::rialFromInput($data['price_rial']);
        $overage = Money::rialFromInput($data['cost_per_call_rial']);
        if ($price === null || $overage === null) {
            throw ValidationException::withMessages([
                'price_rial' => 'مبلغ‌ها باید عدد صحیح ریالی باشند.',
            ]);
        }
        unset($data['price_rial'], $data['cost_per_call_rial']);
        $data['price_minor'] = $price;
        $data['cost_per_call_minor'] = $overage;
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}
