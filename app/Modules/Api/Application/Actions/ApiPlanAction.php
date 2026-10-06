<?php

declare(strict_types=1);

namespace App\Modules\Api\Application\Actions;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiPlan;

final readonly class ApiPlanAction
{
    /** @param array<string, mixed> $data */
    public function create(array $data): ApiPlan
    {
        return ApiPlan::query()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): ApiPlan
    {
        /** @var ApiPlan $plan */
        $plan = ApiPlan::query()->findOrFail($id);
        $plan->fill($data)->save();

        return $plan;
    }

    public function archive(int $id): ApiPlan
    {
        /** @var ApiPlan $plan */
        $plan = ApiPlan::query()->findOrFail($id);
        $plan->forceFill(['is_active' => false])->save();

        return $plan;
    }
}
