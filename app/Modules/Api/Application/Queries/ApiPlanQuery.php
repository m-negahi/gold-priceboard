<?php

declare(strict_types=1);

namespace App\Modules\Api\Application\Queries;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiPlan;
use App\Modules\Common\Helpers\Money;

final readonly class ApiPlanQuery
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return ApiPlan::query()->orderBy('sort')->orderBy('id')->get()
            ->map(static fn (ApiPlan $plan): array => self::row($plan))->all();
    }

    /** @return array<string, mixed> */
    public function findOrFail(int $id): array
    {
        /** @var ApiPlan $plan */
        $plan = ApiPlan::query()->findOrFail($id);

        return self::row($plan);
    }

    /**
     * Model e plan baraye Action hayi ke khod e Model ro mikhan
     * (`PurchasePlanAction`). `findOrFail()` radif e namayeshi mide.
     */
    public function modelOrFail(int $id): ApiPlan
    {
        /** @var ApiPlan $plan */
        $plan = ApiPlan::query()->findOrFail($id);

        return $plan;
    }

    /** @return array<string, mixed> */
    private static function row(ApiPlan $plan): array
    {
        return [
            'id' => $plan->id, 'key' => $plan->key, 'name' => $plan->name,
            'durationDays' => $plan->duration_days,
            'callsIncluded' => $plan->calls_included,
            'callsPerDay' => $plan->calls_per_day,
            'priceMinor' => $plan->price_minor,
            'priceRial' => Money::rialDisplay($plan->price_minor),
            'costPerCallMinor' => $plan->cost_per_call_minor,
            'costPerCallRial' => Money::rialDisplay($plan->cost_per_call_minor),
            'isActive' => $plan->is_active,
            'sort' => $plan->sort,
        ];
    }
}
