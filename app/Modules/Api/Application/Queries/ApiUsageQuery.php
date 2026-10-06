<?php

declare(strict_types=1);

namespace App\Modules\Api\Application\Queries;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiUsageDaily;
use App\Modules\Api\Infrastructure\Usage\RedisUsageCounter;
use Carbon\CarbonImmutable;

/**
 * Khundan e usage e paydar (P6-08) — chart e safhe ye karbar.
 *
 * 🔴 Faghat client haye HAMIN karbar (join ru `api_clients.user_id`) —
 * hazf-shode ha ham (tarikhche)، vali hich vaght client e karbar e digar.
 */
final readonly class ApiUsageQuery
{
    /**
     * Panjere ye `$days` rooz e Tehran ta EMRUZ (ghadimi -> jadid)، rooz e
     * bedun e radif = sefr.
     *
     * @return array{days: list<array{day: string, calls: int, costMinor: int}>, totalCalls: int, totalCostMinor: int}
     */
    public function dailyForUser(int $userId, int $days = 30): array
    {
        $days = max(1, min(366, $days));
        $today = CarbonImmutable::now(RedisUsageCounter::TIMEZONE)->startOfDay();
        $first = $today->subDays($days - 1);

        $rows = ApiUsageDaily::query()
            ->join('api_clients', 'api_clients.id', '=', 'api_usage_daily.api_client_id')
            ->where('api_clients.user_id', $userId)
            ->whereDate('api_usage_daily.day', '>=', $first->format('Y-m-d'))
            ->whereDate('api_usage_daily.day', '<=', $today->format('Y-m-d'))
            ->groupBy('api_usage_daily.day')
            ->selectRaw('api_usage_daily.day as day, sum(api_usage_daily.calls) as calls, sum(api_usage_daily.cost_minor) as cost')
            ->toBase()
            ->get();

        $byDay = [];

        foreach ($rows as $row) {
            // PG `date` -> 'Y-m-d'؛ SQLite momkene time ham dashte bashe.
            $byDay[substr((string) $row->day, 0, 10)] = ['calls' => (int) $row->calls, 'cost' => (int) $row->cost];
        }

        $series = [];
        $totalCalls = 0;
        $totalCost = 0;

        for ($i = 0; $i < $days; $i++) {
            $day = $first->addDays($i)->format('Y-m-d');
            $calls = $byDay[$day]['calls'] ?? 0;
            $cost = $byDay[$day]['cost'] ?? 0;
            $series[] = ['day' => $day, 'calls' => $calls, 'costMinor' => $cost];
            $totalCalls += $calls;
            $totalCost += $cost;
        }

        return ['days' => $series, 'totalCalls' => $totalCalls, 'totalCostMinor' => $totalCost];
    }
}
