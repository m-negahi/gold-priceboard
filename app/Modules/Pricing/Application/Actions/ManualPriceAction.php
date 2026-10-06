<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Actions;

use App\Modules\Pricing\Application\Dto\BoardRowDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Services\DisplayPriceParser;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ManualPrice;
use App\Modules\Pricing\Infrastructure\Persistence\Models\SymbolPriceSetting;
use InvalidArgumentException;

/**
 * Neveshtan e gheymat haye DASTI (fallback) — Phase 4/5.
 *
 * Admin yeki-yeki set mikone ya az zanjire ye olaviat e har symbol
 * gheymat e kham va taze ro copy mikone.
 *
 * 🔴 Vorudi DISPLAY unit ast (rial baraye IRR، dollar baraye USD) — inja
 * be minor tabdil mishe (`displayDivisor × 10^scale`) ta ba baghi ye
 * pipeline yeki bashe.
 */
final readonly class ManualPriceAction
{
    public function setFromDisplay(Instrument $instrument, string|int $displayValue, ?string $updatedBy = null): ManualPrice
    {
        $minor = $this->toMinor($instrument, $displayValue);

        return $this->store($instrument, $minor, 'manual', $updatedBy);
    }

    /**
     * Seed bar asas e OLAVIAT e per-symbol (P5-07): har symbol az avvalin
     * provider e TAZE ye zanjire ye khodesh.
     *
     * Vorudi khoruji ye `EffectivePriceQuery::freshestByPriority()` ast —
     * symbol-i ke manba' e taze nadasht unja NIST، pas gheymat e dasti e
     * ghabli ash ba adad e bayat jaygozin nemishe.
     *
     * 🔴 Gheymat e KHAM (`raw`، ghabl az spread) zakhire mishe;
     * spread ru read e'mal mishe، na ru gheymat e manba'.
     *
     * @param  array<string, BoardRowDto>  $rows  symbol -> radif e entekhab-shode
     * @return int tedad e symbol-i ke seed shod
     */
    public function seedFromRows(array $rows, ?string $updatedBy = null): int
    {
        $seeded = 0;

        $manualOnly = SymbolPriceSetting::query()->where('manual_only', true)->pluck('symbol')->all();

        foreach ($rows as $row) {
            if (in_array($row->instrument->value, $manualOnly, true)) {
                continue;
            }
            $this->store(
                $row->instrument,
                $row->raw->minor,
                $row->providerKey,
                $updatedBy,
                $row->raw->quote->value,
                $row->raw->quote->scale(),
            );

            $seeded++;
        }

        return $seeded;
    }

    public function remove(Instrument $instrument): void
    {
        if (SymbolPriceSetting::query()->where('symbol', $instrument->value)->where('manual_only', true)->exists()) {
            throw new InvalidArgumentException('ابتدا حالت فقط دستی را خاموش کنید.');
        }

        ManualPrice::query()->where('symbol', $instrument->value)->delete();
    }

    private function store(
        Instrument $instrument,
        int $minor,
        string $sourceHint,
        ?string $updatedBy,
        ?string $quote = null,
        ?int $scale = null,
    ): ManualPrice {
        /** @var ManualPrice $row */
        $row = ManualPrice::query()->updateOrCreate(
            ['symbol' => $instrument->value],
            [
                'minor' => $minor,
                'scale' => $scale ?? $instrument->quote()->scale(),
                'quote_currency' => $quote ?? $instrument->quote()->value,
                'source_hint' => $sourceHint,
                'updated_by' => $updatedBy,
            ],
        );

        // `updateOrCreate` vaghti hichi avaz nashe `updated_at` ro dast
        // nemizane — vali «be-rooz kardan e dasti» bayad timestamp ro taze
        // kone (paye ye alarm). Pas sarih touch mikonim.
        $row->touch();

        return $row;
    }

    public function toMinor(Instrument $instrument, string|int $displayValue): int
    {
        return DisplayPriceParser::parse($instrument, $displayValue)->minor;
    }
}
