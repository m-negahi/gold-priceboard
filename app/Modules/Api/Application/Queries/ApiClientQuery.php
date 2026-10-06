<?php

declare(strict_types=1);

namespace App\Modules\Api\Application\Queries;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Common\Queries\BaseQuery;
use Illuminate\Database\Eloquent\Collection;

/**
 * Khundan e client haye API (site ha) — faghat KHUNDAN.
 *
 * @extends BaseQuery<ApiClient>
 */
final readonly class ApiClientQuery extends BaseQuery
{
    protected function model(): string
    {
        return ApiClient::class;
    }

    /**
     * Site haye YEK karbar (hatta hazf-shode ha — baraye tarikhche ye
     * panel). `secret_encrypted` tu `$hidden` ast va inja ham select nemishe.
     *
     * @return Collection<int, ApiClient>
     */
    public function forUser(int $userId): Collection
    {
        return $this->query()
            ->withTrashed()
            ->with('plan')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get(['id', 'user_id', 'plan_id', 'name', 'website_url', 'client_id', 'is_active', 'last_used_at', 'created_at', 'deleted_at']);
    }
}
