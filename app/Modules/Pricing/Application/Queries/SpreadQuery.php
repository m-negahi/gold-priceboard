<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Application\Dto\SpreadInputDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\ProviderKind;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use App\Modules\Pricing\Domain\Services\SpreadPricer;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Domain\ValueObjects\SpreadPair;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceSnapshot;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpread;
use Carbon\CarbonImmutable;
use Throwable;

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
    public function __construct(
        private ?HotCachePort $cache = null,
        private ?SnapshotStorePort $store = null,
    ) {}

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

    /**
     * Radif haye form e spread e yek provider (P3-07): wildcard + HAR
     * `Instrument::cases()` — na faghat save-shode ha (ghanun e PLAN §3).
     *
     * @return array<int, array<string, mixed>>
     */
    public function formRows(string $providerKey): array
    {
        /** @var array<string, ProviderSpread> $existing */
        $existing = ProviderSpread::query()
            ->join('providers', 'providers.id', '=', 'provider_spreads.provider_id')
            ->where('providers.key', $providerKey)
            ->select('provider_spreads.*')
            ->get()
            ->keyBy('symbol')
            ->all();

        $snapshots = $this->snapshotsFor($providerKey);

        $rows = [];
        $symbols = [ProviderSpread::WILDCARD => 'پیش‌فرض (همه نمادها)'];

        foreach (Instrument::cases() as $instrument) {
            $symbols[$instrument->value] = $instrument->label();
        }

        foreach ($symbols as $symbol => $label) {
            $row = $existing[$symbol] ?? null;
            $raw = $snapshots[$symbol] ?? null;

            // Preview e avvaliye ba meghdar haye SAVE-shode — hamun
            // `SpreadPricer` e board، server-side.
            $initialPair = $raw !== null && $row !== null
                ? SpreadPricer::applyPair($raw->price, $row->toPair())
                : null;

            $rows[] = [
                'symbol' => (string) $symbol,
                'label' => $label,
                'exists' => $row !== null,
                'buy_mode' => $row?->buy_mode->value ?? 'percent',
                'buy_value' => $row !== null ? round((float) $row->buy_value, 4) : 0.0,
                'sell_mode' => $row?->sell_mode->value ?? 'percent',
                'sell_value' => $row !== null ? round((float) $row->sell_value, 4) : 0.0,
                'is_enabled' => $row !== null ? (bool) $row->is_enabled : true,
                'freshness_seconds' => $row?->freshness_seconds,
                // Paye ye preview: akharin gheymat e KHAM e in provider.
                'raw' => $raw?->price->toFloat(),
                'buy' => $initialPair !== null ? $initialPair['buy']->toFloat() : $raw?->price->toFloat(),
                'sell' => $initialPair !== null ? $initialPair['sell']->toFloat() : $raw?->price->toFloat(),
                'divisor' => $symbol === ProviderSpread::WILDCARD
                    ? null
                    : Instrument::from((string) $symbol)->quote()->displayDivisor(),
                'precision' => $symbol === ProviderSpread::WILDCARD
                    ? null
                    : Instrument::from((string) $symbol)->quote()->displayPrecision(),
            ];
        }

        return $rows;
    }

    /**
     * Preview e server-side ba HAMUN `SpreadPricer` e board/API (PLAN:
     * «na copy e JS ke saket vagera she»).
     *
     * @return array{raw: float, buy: float, sell: float}|null null = gheymat-i baraye in (provider, symbol) nist
     */
    public function preview(string $providerKey, SpreadInputDto $input): ?array
    {
        if ($input->isWildcard()) {
            return null;
        }

        $snapshot = $this->snapshotsFor($providerKey)[$input->symbol] ?? null;

        if ($snapshot === null) {
            return null;
        }

        $pair = SpreadPricer::applyPair($snapshot->price, $input->toPair());

        return [
            'raw' => $snapshot->price->toFloat(),
            'buy' => $pair['buy']->toFloat(),
            'sell' => $pair['sell']->toFloat(),
        ];
    }

    /**
     * Akharin snapshot e har symbol e yek provider — hot cache، fallback e
     * khundan e MOSTAGHIM az `price_snapshots`.
     *
     * ⚠️ Fallback `SnapshotStorePort::all()` NIST — un gharardadesh «faghat
     * provider e fa'al» ast (hamgami ye do masir e tablo). Vali flow e
     * tanzim e spread daghighan BAR'AKS e: admin spread ro GHABL az roshan
     * kardan e provider minevise (skill e add-price-provider)، pas preview
     * bayad gheymat e provider e KHAMOOSH ro ham bebine. Query mitune Model
     * ro read-only bekhune (jadval e dastresi ye module).
     *
     * Side e namayande: `last`، vagarna `ask` (abshode jofte vaghei dare).
     *
     * @return array<string, SnapshotDto> symbol => snapshot
     */
    private function snapshotsFor(string $providerKey): array
    {
        $map = [];

        try {
            foreach ($this->cache?->board() ?? [] as $snapshot) {
                if ($snapshot->providerKey === $providerKey) {
                    $this->keepRepresentative($map, $snapshot);
                }
            }
        } catch (Throwable) {
            // Redis ghat' — mirim soraghe Postgres.
        }

        if ($map !== []) {
            return $map;
        }

        try {
            $rows = PriceSnapshot::query()
                ->join('providers', 'providers.id', '=', 'price_snapshots.provider_id')
                ->where('providers.key', $providerKey)
                ->select(['price_snapshots.symbol', 'price_snapshots.side', 'price_snapshots.quote_currency', 'price_snapshots.raw_minor', 'price_snapshots.quoted_at'])
                ->get();
        } catch (Throwable) {
            return [];
        }

        foreach ($rows as $row) {
            $instrument = Instrument::tryFrom((string) $row->symbol);

            if ($instrument === null) {
                continue;
            }

            $this->keepRepresentative($map, new SnapshotDto(
                providerKey: $providerKey,
                instrument: $instrument,
                side: $row->side instanceof Side
                    ? $row->side
                    : Side::from((string) $row->side),
                price: new Price(
                    (int) $row->raw_minor,
                    $row->quote_currency instanceof QuoteCurrency
                        ? $row->quote_currency
                        : QuoteCurrency::from((string) $row->quote_currency),
                ),
                quotedAt: $row->quoted_at ?? CarbonImmutable::now(),
            ));
        }

        return $map;
    }

    /** @param array<string, SnapshotDto> $map */
    private function keepRepresentative(array &$map, SnapshotDto $snapshot): void
    {
        $symbol = $snapshot->instrument->value;
        $current = $map[$symbol] ?? null;

        if ($current === null
            || $snapshot->side->value === 'last'
            || ($snapshot->side->value === 'ask' && $current->side->value === 'bid')) {
            $map[$symbol] = $snapshot;
        }
    }

    /**
     * `providerKey => ProviderKind`.
     *
     * ⚠️ `pluck()` estefade NEMISHE: pluck az masir e query builder mire va
     * cast e Eloquent ro rad mikone، pas string e kham bar migardund va
     * caller majbur bud ba `!== 'platform'` moghayese kone — yek typo tu DB
     * saket hame ro «reference» mikard.
     *
     * @return array<string, ProviderKind>
     */
    public function providerKinds(): array
    {
        $kinds = [];

        foreach (Provider::query()->get(['key', 'kind']) as $provider) {
            $kinds[$provider->key] = $provider->kind;
        }

        return $kinds;
    }
}
