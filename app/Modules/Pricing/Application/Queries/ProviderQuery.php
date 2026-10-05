<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Application\Dto\ProviderConfigDto;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpread;
use DateTimeInterface;

/**
 * Khundan e provider ha + spread haye eshun.
 *
 * Faghat khundan — hich side effect.
 */
final readonly class ProviderQuery
{
    /**
     * Provider hayi ke alan bayad poll shan.
     *
     * `symbol_freshness` az `provider_spreads` miad: har radif mitune
     * `freshness_seconds` e khodesh ro override kone.
     *
     * 🔴 Chera lazem ast: `ons` e tgju faghat RUZANE be-rooz mishe dar hali
     * ke `Instrument::freshnessSeconds()` baraye jahani 180s mide. Bedun e
     * override، un symbol har tick rad mishe.
     *
     * @return array<string, ProviderConfigDto>
     */
    public function dueForPoll(DateTimeInterface $now): array
    {
        $providers = Provider::query()
            ->dueForPoll($now)
            ->orderBy('sort')
            ->get();

        if ($providers->isEmpty()) {
            return [];
        }

        $freshness = $this->symbolFreshness($providers->pluck('id')->all());

        $configs = [];

        foreach ($providers as $provider) {
            $configs[$provider->key] = new ProviderConfigDto(
                key: $provider->key,
                freshnessSeconds: $provider->freshness_seconds,
                symbolFreshness: $freshness[$provider->id] ?? [],
                driver: $provider->driver,
                timeout: $provider->timeout,
                options: $provider->options ?? [],
                pollIntervalSeconds: $provider->poll_interval_seconds,
                hideAfterSeconds: $provider->hide_after_seconds,
            );
        }

        return $configs;
    }

    /** @return array<string, ProviderConfigDto> */
    public function allActive(): array
    {
        $providers = Provider::query()->active()->orderBy('sort')->get();
        $freshness = $this->symbolFreshness($providers->pluck('id')->all());

        $configs = [];

        foreach ($providers as $provider) {
            $configs[$provider->key] = new ProviderConfigDto(
                key: $provider->key,
                freshnessSeconds: $provider->freshness_seconds,
                symbolFreshness: $freshness[$provider->id] ?? [],
                driver: $provider->driver,
                timeout: $provider->timeout,
                options: $provider->options ?? [],
                pollIntervalSeconds: $provider->poll_interval_seconds,
                hideAfterSeconds: $provider->hide_after_seconds,
            );
        }

        return $configs;
    }

    /**
     * Override haye `freshness_seconds` be tafkik e provider.
     *
     * @param  array<int, int>  $providerIds
     * @return array<int, array<string, int>>
     */
    private function symbolFreshness(array $providerIds): array
    {
        if ($providerIds === []) {
            return [];
        }

        $rows = ProviderSpread::query()
            ->whereIn('provider_id', $providerIds)
            ->whereNotNull('freshness_seconds')
            ->where('symbol', '!=', ProviderSpread::WILDCARD)
            ->get(['provider_id', 'symbol', 'freshness_seconds']);

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->provider_id][(string) $row->symbol] = (int) $row->freshness_seconds;
        }

        return $map;
    }
}
