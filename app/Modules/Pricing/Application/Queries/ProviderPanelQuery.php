<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Common\Helpers\Jalali;
use Carbon\CarbonImmutable;

final readonly class ProviderPanelQuery
{
    public function __construct(private ProviderQuery $providers, private ProviderHealthQuery $health) {}

    public function hasTrustAnchor(): bool
    {
        return $this->providers->trustAnchorKeys() !== [];
    }

    /** @return list<array<string, mixed>> */
    public function rows(): array
    {
        $health = array_column($this->health->overview(), null, 'key');
        $rows = [];

        foreach ($this->providers->panelRows() as $row) {
            $live = $health[$row['key']] ?? [];
            $row['health'] = $live;
            $row['lastSuccessDisplay'] = isset($live['lastSuccessAt'])
                ? Jalali::format(CarbonImmutable::parse($live['lastSuccessAt']), 'Y/m/d H:i:s') : '—';
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array<string, array<string, mixed>> */
    public function liveRows(): array
    {
        $rows = [];

        foreach ($this->health->overview() as $row) {
            $row['stateValue'] = $row['state']->value;
            $row['stateLabel'] = $row['state']->label();
            $row['lastSuccessDisplay'] = isset($row['lastSuccessAt'])
                ? Jalali::format(CarbonImmutable::parse($row['lastSuccessAt']), 'Y/m/d H:i:s') : '—';
            unset($row['state']);
            $rows[$row['key']] = $row;
        }

        return $rows;
    }
}
