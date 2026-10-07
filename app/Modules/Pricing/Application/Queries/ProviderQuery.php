<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Application\Dto\ProviderConfigDto;
use App\Modules\Pricing\Domain\Enums\ProviderKind;
use App\Modules\Pricing\Domain\Enums\UpstreamUnit;
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
                isTrustAnchor: (bool) $provider->is_trust_anchor,
            );
        }

        return $configs;
    }

    /** @return list<string> */
    public function trustAnchorKeys(): array
    {
        return Provider::query()->active()->where('is_trust_anchor', true)->where('key', '!=', 'manual')->pluck('key')->all();
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
                isTrustAnchor: (bool) $provider->is_trust_anchor,
            );
        }

        return $configs;
    }

    /**
     * Radif haye safhe ye lista ye panel — hame ye provider ha ye hazf-
     * nashode (khamoosh ham)، faghat soton haye TANZIMI + state.
     *
     * 🔴 Hazf-shode ha AMDAN nemiyan (darkhast e sahebe proje، 2026-10-07:
     * «hazf shode ha ro nabinin») — radif e DB soft-delete mimune ta
     * tarikhche ye Influx yatim nashe، vali UI nadide migire. Bargardundan
     * faghat az DB (restore) ba tasmim e sarih.
     *
     * Khoruji array e sade ast (na Model) ta Blade be Eloquent dast nazane.
     *
     * @return array<int, array<string, mixed>>
     */
    public function panelRows(): array
    {
        $rows = [];

        foreach (Provider::query()->orderBy('sort')->get() as $provider) {
            $rows[] = [
                'key' => $provider->key,
                'name' => $provider->name_fa ?: $provider->name,
                'driver' => $provider->driver,
                'kind' => $provider->kind,
                'website' => $provider->website,
                'logo_media_id' => $provider->logo_media_id,
                'isActive' => (bool) $provider->is_active,
                'isTrustAnchor' => (bool) $provider->is_trust_anchor,
                'inComparison' => (bool) $provider->in_comparison,
                'onBoard' => (bool) $provider->on_board,
                'inChart' => (bool) $provider->in_chart,
                'timeout' => (int) $provider->timeout,
                'pollIntervalSeconds' => (int) $provider->poll_interval_seconds,
                'healthState' => $provider->health_state,
                'sort' => (int) $provider->sort,
            ];
        }

        return $rows;
    }

    /**
     * Model baraye pass dadan be `ProviderAction` (olgu ye CLAUDE.md:
     * controller Query ro seda mizane، Model faghat dast-be-dast mishe).
     */
    public function findByKeyOrFail(string $key): Provider
    {
        return Provider::query()->where('key', $key)->firstOrFail();
    }

    /**
     * Meghdar haye form e virayesh — array، na Model (Blade Eloquent nemibine).
     *
     * @return array<string, mixed>
     */
    public function formValues(string $key): array
    {
        $provider = $this->findByKeyOrFail($key);

        return [
            'key' => $provider->key,
            'driver' => $provider->driver,
            'name' => $provider->name,
            'name_fa' => $provider->name_fa,
            'kind' => $provider->kind instanceof ProviderKind
                ? $provider->kind->value
                : (string) $provider->kind,
            'website' => $provider->website,
            'logo_media_id' => $provider->logo_media_id,
            'is_active' => (bool) $provider->is_active,
            'is_trust_anchor' => (bool) $provider->is_trust_anchor,
            'in_comparison' => (bool) $provider->in_comparison,
            'on_board' => (bool) $provider->on_board,
            'in_chart' => (bool) $provider->in_chart,
            'timeout' => (int) $provider->timeout,
            'poll_interval_seconds' => (int) $provider->poll_interval_seconds,
            'freshness_seconds' => (int) $provider->freshness_seconds,
            'hide_after_seconds' => (int) $provider->hide_after_seconds,
            'upstream_unit' => $provider->upstream_unit instanceof UpstreamUnit
                ? $provider->upstream_unit->value
                : (string) $provider->upstream_unit,
            'sort' => (int) $provider->sort,
        ];
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
