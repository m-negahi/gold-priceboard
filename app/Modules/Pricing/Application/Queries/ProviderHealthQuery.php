<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Application\Dto\ProviderHealthDto;
use App\Modules\Pricing\Domain\Enums\HealthState;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Throwable;

/**
 * Salamat e provider ha baraye UI (dashboard/ProviderHealth e panel).
 *
 * ── Do manba'، yek javab ─────────────────────────────────────────────
 * Postgres (soton haye `providers`) DAMPENED ast — faghat ru taghir ya har
 * 60s neveshte mishe (dalil tu `RecordProviderHealthAction`). Redis e hot
 * har tick be-rooz mishe. Panel bayad TAZE tarin ro bebine، pas radif e
 * zende ye Redis ru radif e DB overlay mishe؛ Redis ghat' bashe، DB be
 * tanha-i javab mide (kohne tar vali dorost — hamun falsafe ye BoardQuery).
 */
final readonly class ProviderHealthQuery
{
    public function __construct(private HotCachePort $cache) {}

    /**
     * Yek radif be ezaye HAR provider (hatta khamoosh — panel bayad bebine).
     *
     * @return array<int, array<string, mixed>>
     */
    public function overview(): array
    {
        $live = $this->liveHealth();
        $rows = [];

        foreach (Provider::query()->orderBy('sort')->get() as $provider) {
            $hot = $live[$provider->key] ?? null;

            /*
             * ⚠️ `state` va `consecutiveFailures` az DB، na az hot:
             * `FetchStage` DTO ro PER-TICK misaze va `consecutiveFailures`
             * esh hamishe 0 ast — `state()` e un hamishe «healthy» dar
             * miumad hatta vasat e ghat'i. Shomaresh e vaghei tu
             * `RecordProviderHealthAction` ast ke TAGHIR e state ro FORI be
             * DB minevise (dampening faghat radif e bi-taghir ro rad
             * mikone)، pas DB baraye in do field hamishe be-rooz ast.
             */
            $state = $provider->health_state instanceof HealthState
                ? $provider->health_state
                : HealthState::Unknown;

            $rows[] = [
                'key' => $provider->key,
                'name' => $provider->name_fa ?: $provider->name,
                'isActive' => (bool) $provider->is_active,
                'state' => $state,
                'latencyMs' => $hot?->latencyMs ?? (int) ($provider->last_latency_ms ?? 0),
                'symbolCount' => $hot?->symbolCount ?? (int) ($provider->last_symbol_count ?? 0),
                'rejectedCount' => $hot?->rejectedCount ?? (int) ($provider->last_rejected_count ?? 0),
                'consecutiveFailures' => (int) $provider->consecutive_failures,
                'retried' => ($hot?->attempts ?? 1) > 1,
                'error' => $hot !== null ? $hot->error : $provider->last_error,
                'lastSuccessAt' => $provider->last_success_at?->toIso8601String(),
                'attemptedAt' => $hot?->attemptedAt->toIso8601String()
                    ?? $provider->last_attempt_at?->toIso8601String(),
            ];
        }

        return $rows;
    }

    /** @return array<string, ProviderHealthDto> */
    private function liveHealth(): array
    {
        try {
            return $this->cache->health();
        } catch (Throwable) {
            // Redis ghat' — DB be tanha-i kafi-e (kohne tar vali salem).
            return [];
        }
    }
}
