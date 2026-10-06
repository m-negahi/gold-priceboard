<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\ProviderKind;
use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use App\Modules\Pricing\Domain\Services\SpreadPricer;
use App\Modules\Pricing\Domain\ValueObjects\SpreadPair;
use Throwable;

/**
 * Matrix e `provider × symbol` (P3-10/P4-matrix): gheymat + sen + taghir +
 * moghayese ye beyn-provider dar har khune.
 *
 * ⚠️ AMDAN az `BoardQuery` estefade nemikone: un radif haye `hide_after` ro
 * hazf mikone، dar hali ke MAHFI SHODAN daghighan chizi-e ke in safhe bayad
 * neshun bede («chera in khune ru tablo nist»). Inja hame chiz miad va
 * faghat barchasb mikhore. Vali gheymat ba HAMUN `SpreadPricer` e board
 * mohasebe mishe ta adad ha yeki bashan.
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
        $map = $this->spreadMap();
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

            // Gheymat e foroosh ba HAMUN spread e board (ta adad ha yeki bashan).
            $spread = SpreadQuery::resolve($map, $snapshot->providerKey, $snapshot->instrument);
            $sell = SpreadPricer::apply($snapshot->price, $spread->sell);

            $symbol = $snapshot->instrument->value;
            $cell = $cells[$symbol][$snapshot->providerKey] ?? [
                'sides' => [],
                'ageSeconds' => null,
                'sell' => null,
                'changePercent' => 0.0,
                'direction' => 'flat',
                'isStale' => false,
                'isHidden' => false,
                'isDerived' => false,
            ];

            $cell['sides'][] = $snapshot->side->value;
            // Side e namayande (last > ask) gheymat/taghir ro ta'yin mikone.
            if ($cell['sell'] === null || $snapshot->side->value === 'last') {
                $cell['sell'] = $sell->toFloat();
                $cell['changePercent'] = $snapshot->changePercent;
                $cell['direction'] = $snapshot->direction->value;
            }
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

            $rowCells = $cells[$instrument->value];

            // ── Moghayese ye beyn-provider: arzun/geran tarin + ekhtelaf ──
            $prices = [];

            foreach ($rowCells as $providerKey => $cell) {
                if ($cell['sell'] !== null && $cell['sell'] > 0) {
                    $prices[$providerKey] = $cell['sell'];
                }
            }

            $cheapest = $prices === [] ? null : array_search(min($prices), $prices, true);
            $dearest = $prices === [] ? null : array_search(max($prices), $prices, true);
            // Ekhtelaf e darsadi beyn e geran o arzun (hess e «cross-provider»).
            $spreadPct = count($prices) >= 2 && min($prices) > 0
                ? round((max($prices) - min($prices)) / min($prices) * 100, 2)
                : null;

            $rows[] = [
                'symbol' => $instrument->value,
                'label' => $instrument->label(),
                'quote' => $instrument->quote()->value,
                'divisor' => $instrument->quote()->displayDivisor(),
                'precision' => $instrument->quote()->displayPrecision(),
                'cells' => $rowCells,
                'cheapest' => count($prices) >= 2 ? $cheapest : null,
                'dearest' => count($prices) >= 2 ? $dearest : null,
                'spreadPct' => $spreadPct,
            ];
        }

        return ['providers' => array_keys($providerKeys), 'rows' => $rows];
    }

    /** @return array<string, array<string, SpreadPair>> */
    private function spreadMap(): array
    {
        try {
            return $this->spreads->map();
        } catch (Throwable) {
            return [];
        }
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
