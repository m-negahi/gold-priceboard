<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Actions;

use App\Modules\Pricing\Application\Dto\ProviderHealthDto;
use App\Modules\Pricing\Application\Dto\RejectionDto;
use App\Modules\Pricing\Domain\Enums\HealthState;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Carbon\CarbonImmutable;

/**
 * Sabt e salamat e provider ha tu Postgres + tanzim e `next_poll_at`.
 *
 * ── 🔴 Chera «dampened» ─────────────────────────────────────────────
 * Redis mirror e ZENDE ast (har tick be-rooz mishe). Postgres faghat vaghti
 * dast mikhore ke chizi VAGHE'AN avaz shode bashe.
 *
 * Bedun e in، har provider ruz-i 5,760 bar UPDATE mikhore — 23,000 UPDATE e
 * ruzane baraye data-i ke aksar e vaght eyn e ghabli-e. Un ham ru jadvali ke
 * panel e admin modam azash mikhune.
 *
 * `consecutive_failures` ham inja negah dashte mishe — hamun chizi ke
 * `HealthState` azash mohasebe mishe.
 */
final readonly class RecordProviderHealthAction
{
    /** Hadaghal fasele beyn e do neveshtan، vaghti hich chiz avaz nashode. */
    private const MIN_WRITE_INTERVAL_SECONDS = 60;

    /**
     * @param  array<string, ProviderHealthDto>  $health
     * @param  array<string, int>  $pollIntervals  providerKey => saniye
     */
    public function execute(array $health, array $pollIntervals, CarbonImmutable $at): void
    {
        if ($health === []) {
            return;
        }

        $providers = Provider::query()
            ->whereIn('key', array_keys($health))
            ->get()
            ->keyBy('key');

        foreach ($health as $key => $result) {
            $provider = $providers->get($key);

            if ($provider === null) {
                continue;
            }

            $failures = $result->succeeded ? 0 : $provider->consecutive_failures + 1;
            $state = HealthState::fromFailures($failures);

            $interval = $pollIntervals[$key] ?? $provider->poll_interval_seconds;

            $changed = $state !== $provider->health_state
                || $result->succeeded !== ($provider->last_failure_at < $provider->last_success_at)
                || $provider->last_attempt_at === null
                || $provider->last_attempt_at->diffInSeconds($at) >= self::MIN_WRITE_INTERVAL_SECONDS;

            if (! $changed) {
                // Faghat `next_poll_at` — yek soton، bedun e tuch kardan e
                // baghi ye radif.
                $provider->forceFill(['next_poll_at' => $at->addSeconds($interval)])->saveQuietly();

                continue;
            }

            $provider->forceFill(array_filter([
                'last_attempt_at' => $at,
                'last_success_at' => $result->succeeded ? $at : $provider->last_success_at,
                'last_failure_at' => $result->succeeded ? $provider->last_failure_at : $at,
                'last_error' => $result->succeeded ? null : $result->error,
                'last_latency_ms' => $result->latencyMs,
                'last_symbol_count' => $result->symbolCount,
                'last_rejected_count' => $result->rejectedCount,
                'consecutive_failures' => $failures,
                'health_state' => $state->value,
                'last_payload_hash' => $result->payloadHash,
                'next_poll_at' => $at->addSeconds($interval),
            ], static fn ($value): bool => $value !== null))->saveQuietly();

            // `last_error` bayad betune `null` beshe — `array_filter` un ro
            // mindaze، pas jodagane set mishe.
            if ($result->succeeded && $provider->last_error !== null) {
                $provider->forceFill(['last_error' => null])->saveQuietly();
            }
        }
    }

    /**
     * Shomaresh e radd shode ha be tafkik e provider.
     *
     * @param  array<int, RejectionDto>  $rejections
     * @return array<string, int>
     */
    public static function countRejections(array $rejections): array
    {
        $counts = [];

        foreach ($rejections as $rejection) {
            $counts[$rejection->providerKey] = ($counts[$rejection->providerKey] ?? 0) + 1;
        }

        return $counts;
    }
}
