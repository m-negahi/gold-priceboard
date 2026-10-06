<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Actions;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ManualPrice;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceSnapshot;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;

/**
 * Neveshtan e gheymat haye DASTI (fallback) — Phase 4/5.
 *
 * Admin ya yeki-yeki set mikone، ya az yek provider SEED mikone (akharin
 * gheymat haye un ro mirize tu manual، ba'd dasti tweak mikone).
 *
 * 🔴 Vorudi DISPLAY unit ast (toman baraye IRR، dollar baraye USD) — inja
 * be minor tabdil mishe (`displayDivisor × 10^scale`) ta ba baghi ye
 * pipeline yeki bashe.
 */
final readonly class ManualPriceAction
{
    public function setFromDisplay(Instrument $instrument, float $displayValue, ?string $updatedBy = null): ManualPrice
    {
        $minor = $this->toMinor($instrument, $displayValue);

        return $this->store($instrument, $minor, 'manual', $updatedBy);
    }

    /**
     * Seed az yek provider: akharin gheymat haye un ro mirize tu manual.
     *
     * @return int tedad e symbol-i ke seed shod
     */
    public function seedFromProvider(string $providerKey, ?string $updatedBy = null): int
    {
        $provider = Provider::query()->where('key', $providerKey)->first();

        if ($provider === null) {
            return 0;
        }

        $snapshots = PriceSnapshot::query()
            ->where('provider_id', $provider->id)
            ->whereIn('side', ['last', 'ask'])
            ->get(['symbol', 'side', 'raw_minor', 'quote_currency', 'scale']);

        $seeded = 0;
        $seen = [];

        foreach ($snapshots as $snapshot) {
            $instrument = Instrument::tryFrom((string) $snapshot->symbol);

            if ($instrument === null) {
                continue;
            }

            // `last` bar `ask` moghaddam (abshode do radif dare).
            if (isset($seen[$snapshot->symbol]) && $snapshot->side !== 'last') {
                continue;
            }

            $quote = $snapshot->quote_currency instanceof QuoteCurrency
                ? $snapshot->quote_currency->value
                : (string) $snapshot->quote_currency;

            $this->store(
                $instrument,
                (int) $snapshot->raw_minor,
                $providerKey,
                $updatedBy,
                $quote,
                (int) $snapshot->scale,
            );

            $seen[$snapshot->symbol] = true;
            $seeded++;
        }

        return $seeded;
    }

    public function remove(Instrument $instrument): void
    {
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

    private function toMinor(Instrument $instrument, float $displayValue): int
    {
        $factor = $instrument->quote()->displayDivisor() * (10 ** $instrument->quote()->scale());

        return (int) round($displayValue * $factor);
    }
}
