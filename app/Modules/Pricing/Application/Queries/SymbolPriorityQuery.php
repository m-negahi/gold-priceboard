<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Services\PriorityChain;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Persistence\Models\SymbolProviderPriority;

/**
 * Khundan e olaviat e per-symbol (P5-07).
 *
 * `customMap` faghat chizi ke admin sarih tanzim karde؛ tartib e NAHAYI
 * hamishe az `PriorityChain` miad (ba provider haye fa'al e lahze).
 */
final readonly class SymbolPriorityQuery
{
    /**
     * @return array<string, list<string>> symbol -> kelid e provider ha be tartib
     */
    public function customMap(): array
    {
        // Join (na relation) ta provider e soft-delete-shode khodkar birun
        // bioftad — `whereNull('deleted_at')` e sarih.
        $rows = SymbolProviderPriority::query()
            ->join('providers', 'providers.id', '=', 'symbol_provider_priorities.provider_id')
            ->whereNull('providers.deleted_at')
            ->orderBy('symbol_provider_priorities.symbol')
            ->orderBy('symbol_provider_priorities.position')
            ->get(['symbol_provider_priorities.symbol', 'providers.key']);

        $map = [];

        foreach ($rows as $row) {
            $map[(string) $row->symbol][] = (string) $row->key;
        }

        return $map;
    }

    /**
     * Provider haye FA'AL be tartib e global (`sort`).
     *
     * @return list<string>
     */
    public function globalOrder(): array
    {
        return Provider::query()->active()->orderBy('sort')->pluck('key')->map(static fn ($k): string => (string) $k)->values()->all();
    }

    /**
     * Esm e namayeshi e provider haye FA'AL (ba `manual`) — baraye chip ha.
     *
     * @return array<string, string> key -> esm
     */
    public function providerNames(): array
    {
        $names = [];

        foreach (Provider::query()->active()->orderBy('sort')->get(['key', 'name', 'name_fa']) as $provider) {
            $names[(string) $provider->key] = (string) ($provider->name_fa ?: $provider->name);
        }

        return $names;
    }

    /**
     * Har instrument + zanjire ye nahayi — baraye safhe ye panel.
     *
     * @return list<array{symbol: string, label: string, custom: list<string>, chain: list<string>, isCustom: bool}>
     */
    public function panelRows(): array
    {
        $global = $this->globalOrder();
        $custom = $this->customMap();
        $rows = [];

        foreach (Instrument::cases() as $instrument) {
            $own = $custom[$instrument->value] ?? [];

            $rows[] = [
                'symbol' => $instrument->value,
                'label' => $instrument->label(),
                'custom' => $own,
                'chain' => PriorityChain::order($global, $own),
                'isCustom' => $own !== [],
            ];
        }

        return $rows;
    }
}
