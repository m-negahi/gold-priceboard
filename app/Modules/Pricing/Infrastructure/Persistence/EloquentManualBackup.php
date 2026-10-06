<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Persistence;

use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Application\Queries\SymbolPriceSettingsQuery;
use App\Modules\Pricing\Application\Queries\SymbolPriorityQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Ports\ManualBackupPort;
use App\Modules\Pricing\Domain\Services\ManualBackupSelector;
use App\Modules\Pricing\Domain\Services\PriorityChain;
use App\Modules\Pricing\Domain\ValueObjects\TickTime;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ManualPrice;
use App\Modules\Pricing\Infrastructure\Persistence\Models\SymbolPriceSetting;
use Illuminate\Support\Facades\DB;

final readonly class EloquentManualBackup implements ManualBackupPort
{
    public function __construct(private ProviderQuery $providers, private SymbolPriorityQuery $priorities) {}

    /** @param array<string, SnapshotDto> $snapshots */
    public function sync(array $snapshots, TickTime $at): int
    {
        $configs = $this->providers->allActive();
        $custom = $this->priorities->customMap();
        $global = array_map('strval', array_keys($configs));
        $count = 0;

        foreach ($this->settingsForEverySymbol() as $setting) {
            if ($setting->manual_only || $setting->manual_auto_source === 'none') {
                continue;
            }

            $keys = ManualBackupSelector::providerKeys($setting->manual_only, $setting->manual_auto_source,
                PriorityChain::order($global, $custom[$setting->symbol] ?? []));

            foreach ($keys as $key) {
                $config = $configs[$key] ?? null;
                $snapshot = $snapshots[$key.':'.$setting->symbol.':last']
                    ?? $snapshots[$key.':'.$setting->symbol.':ask'] ?? null;

                if ($config === null || $snapshot === null) {
                    continue;
                }

                // Upstream e bayat ba quotedAt e taze nabayad backup ro taze jelve bede.
                $observedAt = $snapshot->upstreamAt ?? $snapshot->quotedAt;
                $age = $at->at->getTimestamp() - $observedAt->getTimestamp();

                if ($age < -120 || $age > $config->freshnessFor($snapshot->instrument)) {
                    continue;
                }

                // Row-lock ba settings save race nemikone; manual-only dar lahze ye write ham check mishe.
                $updated = DB::transaction(function () use ($setting, $snapshot): bool {
                    $locked = SymbolPriceSetting::query()->lockForUpdate()->find($setting->id);

                    if ($locked === null || $locked->manual_only || $locked->manual_auto_source !== $setting->manual_auto_source) {
                        return false;
                    }

                    $row = ManualPrice::query()->firstOrNew(['symbol' => $setting->symbol]);
                    $observedAt = $snapshot->upstreamAt ?? $snapshot->quotedAt;

                    // Haman snapshot ro pey-dar-pey touch nakon: sen e backup bayad sadegh bemune.
                    if ($row->exists && $row->source_hint === $snapshot->providerKey
                        && $row->updated_at !== null && $row->updated_at->greaterThanOrEqualTo($observedAt)) {
                        return false;
                    }

                    $row->fill([
                        'minor' => $snapshot->price->minor,
                        'scale' => $snapshot->price->quote->scale(),
                        'quote_currency' => $snapshot->price->quote->value,
                        'source_hint' => $snapshot->providerKey,
                        'updated_by' => 'auto',
                    ]);
                    $row->updated_at = $observedAt;
                    $row->save(['timestamps' => false]);

                    return true;
                });
                $count += (int) $updated;
                break;
            }
        }

        return $count;
    }

    /**
     * Yek radif e tanzim baraye HAR symbol.
     *
     * 🔴 Ghablan faghat radif haye mojud e `symbol_price_settings` gardesh
     * mishodan؛ symbol-i ke admin hich vaght tanzim nakarde bud (ya'ni
     * taghriban hame) HICH VAGHT backup nemigereft — va feed e moshtari ru
     * gheymat e dasti ye kohne mioftad. Radif e nabude ba default e DB
     * (`manual_auto_source = priority`) sakhte mishe ta lock e ba'di ham
     * chizi baraye gereftan dashte bashe.
     *
     * @return list<SymbolPriceSetting>
     */
    private function settingsForEverySymbol(): array
    {
        $existing = SymbolPriceSetting::query()->get()->keyBy('symbol');
        $defaults = SymbolPriceSettingsQuery::defaults();
        $rows = [];

        foreach (Instrument::cases() as $instrument) {
            // Default ha SARIH pass mishan: `firstOrCreate` ba default e DB
            // model-i bar migardune ke attribute hash tu hafeze null-and.
            $rows[] = $existing[$instrument->value]
                ?? SymbolPriceSetting::query()->firstOrCreate(['symbol' => $instrument->value], [
                    'manual_only' => $defaults['manualOnly'],
                    'manual_fallback_enabled' => $defaults['manualFallbackEnabled'],
                    'manual_auto_source' => $defaults['manualAutoSource'],
                ]);
        }

        return $rows;
    }
}
