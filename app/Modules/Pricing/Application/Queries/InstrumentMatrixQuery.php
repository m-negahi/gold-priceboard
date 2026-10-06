<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\ProviderKind;
use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use Throwable;

/**
 * Matrix e `provider × symbol` (P3-10): sen e har khune + marker e derived.
 *
 * ⚠️ AMDAN az `BoardQuery` estefade nemikone: un radif haye `hide_after` ro
 * hazf mikone، dar hali ke MAHFI SHODAN daghighan chizi-e ke in safhe bayad
 * neshun bede («chera in khune ru tablo nist»). Inja hame chiz miad va
 * faghat barchasb mikhore.
 */
final readonly class InstrumentMatrixQuery
{
    public function __construct(
        private HotCachePort $cache,
        private SnapshotStorePort $store,
        private ProviderQuery $providers,
        private SpreadQuery $spreads,
        private ClockPort $clock,
    ) {}

    /**
     * @return array{providers: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    public function matrix(): array
    {
        $snapshots = $this->snapshots();
        $configs = $this->providers->allActive();
        $kinds = $this->kinds();
        $now = $this->clock->now();

        $providerKeys = [];
        $cells = [];

        foreach ($snapshots as $snapshot) {
            $providerKeys[$snapshot->providerKey] = true;

            $age = $snapshot->ageSeconds($now);
            $config = $configs[$snapshot->providerKey] ?? null;
            $freshness = $config?->freshnessFor($snapshot->instrument)
                ?? $snapshot->instrument->freshnessSeconds();
            $hideAfter = $config?->hideAfterFor($snapshot->instrument);

            $symbol = $snapshot->instrument->value;
            $cell = $cells[$symbol][$snapshot->providerKey] ?? [
                'sides' => [],
                'ageSeconds' => null,
                'isStale' => false,
                'isHidden' => false,
                'isDerived' => false,
            ];

            $cell['sides'][] = $snapshot->side->value;
            // Taze tarin side ye khune me'yar e sen e.
            $cell['ageSeconds'] = $cell['ageSeconds'] === null ? $age : min($cell['ageSeconds'], $age);
            $cell['isStale'] = $cell['ageSeconds'] > $freshness;
            $cell['isHidden'] = $hideAfter !== null && $cell['ageSeconds'] > $hideAfter;
            $cell['isDerived'] = ! $snapshot->instrument->hasNativeSides()
                && ! (($kinds[$snapshot->providerKey] ?? ProviderKind::Reference)->hasRealSides());

            $cells[$symbol][$snapshot->providerKey] = $cell;
        }

        $rows = [];

        foreach (Instrument::cases() as $instrument) {
            if (! isset($cells[$instrument->value])) {
                continue;
            }

            $rows[] = [
                'symbol' => $instrument->value,
                'label' => $instrument->label(),
                'cells' => $cells[$instrument->value],
            ];
        }

        return ['providers' => array_keys($providerKeys), 'rows' => $rows];
    }

    /** @return array<string, SnapshotDto> */
    private function snapshots(): array
    {
        try {
            $hot = $this->cache->board();

            if ($hot !== []) {
                return $hot;
            }
        } catch (Throwable) {
            // Redis ghat' — Postgres.
        }

        try {
            return $this->store->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, ProviderKind> */
    private function kinds(): array
    {
        try {
            return $this->spreads->providerKinds();
        } catch (Throwable) {
            return [];
        }
    }
}
