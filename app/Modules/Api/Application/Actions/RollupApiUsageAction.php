<?php

declare(strict_types=1);

namespace App\Modules\Api\Application\Actions;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiUsageDaily;
use App\Modules\Api\Infrastructure\Usage\RedisUsageCounter;
use App\Modules\Wallet\Application\Queries\WalletQuery;
use Carbon\CarbonImmutable;

/**
 * Rollup e usage e yek rooz e TEHRAN be `api_usage_daily` (P6-08).
 *
 * ── Do manba'، do naghsh ────────────────────────────────────────────
 * - `calls` az shomarande ye Redis. 🔴 Agar Redis pak shode bashe
 *   (evict/restart) adad e zakhire-shode SEFR NEMISHE: `max(redis, db)` —
 *   shomarande dar tul e rooz faghat bala mire.
 * - `cost_minor` az LEDGER e PG (kasr − refund) — HAR bar az no hesab
 *   mishe chun manba' e haghighat e pul ast، na shomarande ye movazi.
 *
 * Idempotent: upsert ru `(api_client_id, day)`؛ ejra ye dobare hamun adad.
 */
final readonly class RollupApiUsageAction
{
    public function __construct(
        private RedisUsageCounter $counter,
        private WalletQuery $wallets,
    ) {}

    /**
     * @param  string  $day  `Y-m-d` e Tehran
     * @return int tedad e radif haye upsert-shode
     */
    public function forDay(string $day): int
    {
        $from = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $day.' 00:00:00', RedisUsageCounter::TIMEZONE);

        if ($from === null || $from->format('Y-m-d') !== $day) {
            throw new \InvalidArgumentException("rooz e na-motabar: {$day}");
        }

        $calls = $this->counter->forDay($day);
        $costByPublicId = $this->wallets->netApiCostByClient($from, $from->addDay());

        // client_id e omumi -> PK (hazf-shode ha ham — tarikhche).
        $pkByPublicId = $costByPublicId === [] ? [] : ApiClient::query()
            ->withTrashed()
            ->whereIn('client_id', array_keys($costByPublicId))
            ->pluck('id', 'client_id')
            ->all();

        $costs = [];

        foreach ($costByPublicId as $publicId => $net) {
            if (isset($pkByPublicId[$publicId])) {
                $costs[(int) $pkByPublicId[$publicId]] = $net;
            }
        }

        $existing = ApiUsageDaily::query()
            ->whereDate('day', $day)
            ->pluck('calls', 'api_client_id')
            ->all();

        // Faghat client haye mojud (FK) — kelid e Redis e client e
        // sakht-hazf-shode nadide gerefte mishe.
        $candidates = array_unique([...array_keys($calls), ...array_keys($costs), ...array_keys($existing)]);
        $known = ApiClient::query()->withTrashed()->whereKey($candidates)->pluck('id')->all();

        if ($known === []) {
            return 0;
        }

        $now = CarbonImmutable::now();
        $rows = [];

        foreach ($known as $pk) {
            $pk = (int) $pk;
            $rows[] = [
                'api_client_id' => $pk,
                'day' => $day,
                'calls' => max($calls[$pk] ?? 0, (int) ($existing[$pk] ?? 0)),
                'cost_minor' => $costs[$pk] ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ApiUsageDaily::query()->upsert($rows, ['api_client_id', 'day'], ['calls', 'cost_minor', 'updated_at']);

        return count($rows);
    }
}
