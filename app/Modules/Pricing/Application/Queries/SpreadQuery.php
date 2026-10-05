<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\ValueObjects\SpreadPair;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpread;

/**
 * Map e spread: `providerKey => symbol => SpreadPair`.
 *
 * Tartib e resolve: `(provider, symbol)` -> `(provider, '*')` -> sefr.
 *
 * ⚠️ Spread hichvaght zakhire nemishe — faghat ru READ e'mal mishe. Dalil e
 * kamel tu `SpreadPricer` ast (kholase: spread e bake-shode yek pele ye
 * ja'li tu chart mizare va har indicator un ro signal e vaghei mikhune).
 */
final readonly class SpreadQuery
{
    /**
     * @return array<string, array<string, SpreadPair>>
     */
    public function map(): array
    {
        $rows = ProviderSpread::query()
            ->join('providers', 'providers.id', '=', 'provider_spreads.provider_id')
            ->where('provider_spreads.is_enabled', true)
            ->select(['provider_spreads.*', 'providers.key as provider_key'])
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $map[(string) $row->provider_key][(string) $row->symbol] = $row->toPair();
        }

        return $map;
    }

    /**
     * Spread e yek (provider, instrument) — ba fallback e wildcard.
     *
     * @param  array<string, array<string, SpreadPair>>  $map
     */
    public static function resolve(array $map, string $providerKey, Instrument $instrument): SpreadPair
    {
        $forProvider = $map[$providerKey] ?? [];

        return $forProvider[$instrument->value]
            ?? $forProvider[ProviderSpread::WILDCARD]
            ?? SpreadPair::none();
    }

    /** @return array<string, string> providerKey => kind */
    public function providerKinds(): array
    {
        return Provider::query()
            ->pluck('kind', 'key')
            ->map(static fn ($kind): string => (string) $kind)
            ->all();
    }
}
