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

    /**
     * Default e symbol e bedun e radif.
     *
     * 🔴 `manualAutoSource = priority` (2026-10-06): backup e dasti bayad
     * bedun e dast e admin por bemune — «akharin gheymat e taze be tartib e
     * olaviat e HAMUN symbol». `none` faghat entekhab e sarih e admin ast.
     *
     * @return array{manualOnly: bool, manualFallbackEnabled: bool, manualAutoSource: string}
     */
    public static function defaults(): array
    {
        return ['manualOnly' => false, 'manualFallbackEnabled' => true, 'manualAutoSource' => 'priority'];
    }
}
