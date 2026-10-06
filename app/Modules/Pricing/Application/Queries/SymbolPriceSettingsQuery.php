<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Infrastructure\Persistence\Models\SymbolPriceSetting;

final readonly class SymbolPriceSettingsQuery
{
    /** @return array<string, array{manualOnly: bool, manualFallbackEnabled: bool, manualAutoSource: string}> */
    public function map(): array
    {
        $map = [];

        foreach (SymbolPriceSetting::query()->get() as $row) {
            $map[$row->symbol] = [
                'manualOnly' => $row->manual_only,
                'manualFallbackEnabled' => $row->manual_fallback_enabled,
                'manualAutoSource' => $row->manual_auto_source,
            ];
        }

        return $map;
    }

    /** @return array{manualOnly: bool, manualFallbackEnabled: bool, manualAutoSource: string} */
    public static function defaults(): array
    {
        return ['manualOnly' => false, 'manualFallbackEnabled' => true, 'manualAutoSource' => 'none'];
    }
}
