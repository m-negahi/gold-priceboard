<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Persistence;

use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\Enums\Direction;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceChange;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceSnapshot;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Carbon\CarbonImmutable;

/**
 * Adapter e Postgres baraye `SnapshotStorePort`.
 *
 * ── Chera `upsert()` va na halghe-i az `updateOrCreate()` ────────────
 * Projeh ye marja' (`hg_website`) tu halghe `updateOrCreate` mizad. Ba ~50
 * radif ya'ni 50 round-trip tu HAR tick — 5,760 bar tu rooz = ~288,000
 * round-trip e ezafi ye ruzane, bi-hich fayede.
 * `upsert()` yek dastur e `INSERT … ON CONFLICT DO UPDATE` ast ke khodesh
 * atomic ast va transaction ham lazem nadare.
 *
 * ── Chera map e `key => id` har bar khunde mishe ─────────────────────
 * In class `final readonly` ast (Octane-safe) pas nemitune cache e dakheli
 * dashte bashe. Jadval e `providers` ~6 radif ast; yek query ye kuchik be
 * saretar az yek bug e halat e bayat zire Swoole mi'arze.
 */
final readonly class EloquentSnapshotStore implements SnapshotStorePort
{
    public function all(): array
    {
        // Yek query ba join — `providers.key` mostaghim miad va niazi be
        // query e dovom baraye tarjome ye id nist.
        //
        // 🔴 Filter e `is_active` + `deleted_at` ejbari ast، na ekhtiari.
        // Masir e Redis (`putBoard`) kol e kelid ro BAZNEVISI mikone ba
        // snapshot haye hamun tick، pas provider e khamoosh-shode khod-be-khod
        // az tablo mire. Vali `join` e kham scope e SoftDeletes ro e'mal
        // nemikone، pas bedun e in do shart، masir e FALLBACK e Postgres
        // hamun provider ro BAR MIGARDUND — ya'ni tablo zamani ke Redis
        // ghat' ast provider haye khamoosh ro neshun mide. Do masiri ke
        // bayad eyn e ham bashan، daghighan tu bad tarin lahze vagera
        // mishan.
        //
        // Pipeline ham az hamin method `previous` ro migire va un ham faghat
        // provider haye fa'al ro poll mikone — pas filter unja ham durost ast.
        $rows = PriceSnapshot::query()
            ->join('providers', 'providers.id', '=', 'price_snapshots.provider_id')
            ->where('providers.is_active', true)
            ->whereNull('providers.deleted_at')
            ->select([
                'price_snapshots.*',
                'providers.key as provider_key',
            ])
            ->get();

        $snapshots = [];

        foreach ($rows as $row) {
            $instrument = Instrument::tryFrom((string) $row->symbol);

            // Symbol-i ke dige tu enum nist (hazf shode ya esm esh avaz shode)
            // saket rad mishe — nabayad kol e tablo ro bokoshe.
            if ($instrument === null) {
                continue;
            }

            $dto = $this->toDto($row, $instrument, (string) $row->provider_key);
            $snapshots[$dto->key()] = $dto;
        }

        return $snapshots;
    }

    public function save(array $snapshots): void
    {
        if ($snapshots === []) {
            return;
        }

        $providerIds = $this->providerIds();
        $rows = [];
        $now = CarbonImmutable::now();

        foreach ($snapshots as $snapshot) {
            $providerId = $providerIds[$snapshot->providerKey] ?? null;

            if ($providerId === null) {
                continue;
            }

            $rows[] = [
                'provider_id' => $providerId,
                'symbol' => $snapshot->instrument->value,
                'side' => $snapshot->side->value,
                'quote_currency' => $snapshot->price->quote->value,
                'scale' => $snapshot->price->quote->scale(),
                'raw_minor' => $snapshot->price->minor,
                'prev_minor' => $snapshot->previous?->minor,
                'day_open_minor' => $snapshot->dayOpen?->minor,
                'day_high_minor' => $snapshot->dayHigh?->minor,
                'day_low_minor' => $snapshot->dayLow?->minor,
                'change_pct' => $snapshot->changePercent,
                'direction' => $snapshot->direction->value,
                'pending_outlier_minor' => $snapshot->pendingOutlier?->minor,
                'has_native_sides' => $snapshot->instrument->hasNativeSides(),
                'upstream_at' => $snapshot->upstreamAt,
                'upstream_lag_seconds' => $snapshot->upstreamLagSeconds,
                'quoted_at' => $snapshot->quotedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return;
        }

        PriceSnapshot::query()->upsert(
            $rows,
            ['provider_id', 'symbol', 'side'],
            [
                'raw_minor', 'prev_minor', 'day_open_minor', 'day_high_minor', 'day_low_minor',
                'change_pct', 'direction', 'pending_outlier_minor', 'has_native_sides',
                'quote_currency', 'scale', 'upstream_at', 'upstream_lag_seconds',
                'quoted_at', 'updated_at',
            ],
        );
    }

    public function appendChanges(array $changed): void
    {
        if ($changed === []) {
            return;
        }

        $providerIds = $this->providerIds();
        $rows = [];

        foreach ($changed as $snapshot) {
            $providerId = $providerIds[$snapshot->providerKey] ?? null;

            if ($providerId === null) {
                continue;
            }

            $rows[] = [
                'provider_id' => $providerId,
                'symbol' => $snapshot->instrument->value,
                'side' => $snapshot->side->value,
                'quote_currency' => $snapshot->price->quote->value,
                'scale' => $snapshot->price->quote->scale(),
                'raw_minor' => $snapshot->price->minor,
                'upstream_at' => $snapshot->upstreamAt,
                'observed_at' => $snapshot->quotedAt,
            ];
        }

        if ($rows !== []) {
            PriceChange::query()->insert($rows);
        }
    }

    /**
     * Map e `providers.key => providers.id`.
     *
     * Hazf-shode ha (soft delete) ham miyan: agar yek provider soft-delete
     * beshe vali hanuz tu config e tick bashe, behtare radif esh update beshe
     * ta inke saket gom beshe.
     *
     * @return array<string, int>
     */
    private function providerIds(): array
    {
        return Provider::withTrashed()
            ->pluck('id', 'key')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function toDto(PriceSnapshot $row, Instrument $instrument, string $providerKey): SnapshotDto
    {
        $quote = $row->quote_currency instanceof QuoteCurrency
            ? $row->quote_currency
            : QuoteCurrency::from((string) $row->quote_currency);

        $price = static fn (?int $minor): ?Price => $minor === null ? null : new Price($minor, $quote);

        return new SnapshotDto(
            providerKey: $providerKey,
            instrument: $instrument,
            side: $row->side instanceof Side ? $row->side : Side::from((string) $row->side),
            price: new Price((int) $row->raw_minor, $quote),
            quotedAt: $row->quoted_at ?? CarbonImmutable::now(),
            previous: $price($row->prev_minor),
            dayOpen: $price($row->day_open_minor),
            dayHigh: $price($row->day_high_minor),
            dayLow: $price($row->day_low_minor),
            changePercent: (float) $row->change_pct,
            direction: $row->direction instanceof Direction
                ? $row->direction
                : Direction::from((string) $row->direction),
            upstreamAt: $row->upstream_at,
            upstreamLagSeconds: (int) ($row->upstream_lag_seconds ?? 0),
            pendingOutlier: $price($row->pending_outlier_minor),
        );
    }
}
