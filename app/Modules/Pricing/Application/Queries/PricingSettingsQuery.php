<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Common\Helpers\StringHelper;

final readonly class PricingSettingsQuery
{
    public function __construct(
        private SymbolPriorityQuery $priorities,
        private ManualPriceQuery $manual,
        private SymbolPriceSettingsQuery $settings,
        private EffectivePriceQuery $effective,
        private ProviderQuery $providers,
    ) {}

    /** @return array<string, mixed> */
    public function present(): array
    {
        $manual = array_column($this->manual->rows(), null, 'symbol');
        $settings = $this->settings->map();
        $feed = $this->effective->feed();
        $chosen = array_column($feed['symbols'], null, 'symbol');
        $rows = [];

        foreach ($this->priorities->panelRows() as $row) {
            $row = $row + ($manual[$row['symbol']] ?? []) + ($settings[$row['symbol']] ?? SymbolPriceSettingsQuery::defaults());
            $row['label'] = StringHelper::numbersToEnglish($row['label']);
            $row['selectedProvider'] = $chosen[$row['symbol']]['provider'] ?? null;
            $row['usingFallback'] = $chosen[$row['symbol']]['usingFallback'] ?? false;
            $rows[] = $row;
        }

        $names = $this->priorities->providerNames();

        $autoNames = [];

        foreach ($this->providers->panelRows() as $provider) {
            if (! $provider['deleted'] && $provider['key'] !== 'manual') {
                $autoNames[$provider['key']] = $provider['name'];
            }
        }

        return ['rows' => $rows, 'names' => $names, 'autoNames' => $autoNames, 'manualActive' => isset($names['manual']),
            'effective' => ['alarm' => $feed['alarm'], 'fallbackCount' => $feed['fallbackCount']]];
    }
}
