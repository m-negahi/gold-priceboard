<?php

declare(strict_types=1);

namespace App\Modules\Panel\Queries;

use App\Modules\Pricing\Application\Dto\BoardRowDto;
use App\Modules\Pricing\Application\Queries\BoardQuery;
use App\Modules\Pricing\Application\Queries\ProviderHealthQuery;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Domain\Enums\HealthState;

/**
 * View-model e mizkar — FAGHAT jam'-bandi ye khoruji ye Query haye Pricing.
 *
 * Hich Eloquent، hich mantegh e gheymat: har adad az `BoardQuery`/
 * `ProviderHealthQuery` miad (ghanun e laye ha — Query mitune Query seda
 * bezane). Bazneveshte az sefr baraye IN proje (noskhe ye Desktop be
 * schema ye dige-i vasl bud va hazf shod — P3-00a).
 */
final readonly class DashboardQuery
{
    public function __construct(
        private BoardQuery $board,
        private ProviderQuery $providers,
        private ProviderHealthQuery $health,
    ) {}

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $configs = $this->providers->allActive();
        $board = $this->board->board($configs);
        $health = $this->health->overview();

        $staleRows = array_filter($board->rows, static fn (BoardRowDto $row): bool => $row->isStale);
        $activeHealth = array_filter($health, static fn (array $row): bool => $row['isActive']);
        $healthyCount = count(array_filter(
            $activeHealth,
            static fn (array $row): bool => $row['state'] === HealthState::Healthy,
        ));

        return [
            'stats' => [
                'liveRows' => count($board->rows),
                'staleRows' => count($staleRows),
                'activeProviders' => count($activeHealth),
                'healthyProviders' => $healthyCount,
                'degraded' => $board->degraded,
                'boardVersion' => $board->version,
                'spreadVersion' => $board->spreadVersion,
            ],
            // Darsad e salamat baraye gauge: az provider haye FA'AL.
            'healthPercent' => $activeHealth === []
                ? 0.0
                : round($healthyCount / count($activeHealth) * 100, 1),
            'providers' => $health,
            'table' => $this->table($board->rows),
        ];
    }

    /**
     * Jadval e moghayese: radif = instrument، soton = provider.
     *
     * @param  array<int, BoardRowDto>  $rows
     * @return array{providers: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    private function table(array $rows): array
    {
        $providerKeys = [];
        $byInstrument = [];

        foreach ($rows as $row) {
            // Jofte bid/ask (abshode) do radif e `side`-dar dare؛ baraye
            // jadval e kholase faghat yek khune mikhahim — `ask` (foroosh)
            // namayande ast، `last` baraye baghi.
            if (! in_array($row->side->value, ['last', 'ask'], true)) {
                continue;
            }

            $providerKeys[$row->providerKey] = true;
            $byInstrument[$row->instrument->value]['label'] = $row->instrument->label();
            $byInstrument[$row->instrument->value]['cells'][$row->providerKey] = [
                'sell' => $row->sell->toFloat(),
                'divisor' => $row->instrument->quote()->displayDivisor(),
                'precision' => $row->instrument->quote()->displayPrecision(),
                'quote' => $row->instrument->quote()->value,
                'direction' => $row->direction->value,
                'changePercent' => $row->changePercent,
                'ageSeconds' => $row->ageSeconds,
                'isStale' => $row->isStale,
                'isDerived' => $row->isDerived,
            ];
        }

        return [
            'providers' => array_keys($providerKeys),
            'rows' => array_values($byInstrument),
        ];
    }
}
