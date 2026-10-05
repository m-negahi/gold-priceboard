<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Application\Dto\BoardDto;
use App\Modules\Pricing\Application\Dto\BoardRowDto;
use App\Modules\Pricing\Application\Dto\ProviderConfigDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\ProviderKind;
use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use App\Modules\Pricing\Domain\Services\SpreadPricer;
use App\Modules\Pricing\Domain\ValueObjects\SpreadPair;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Khundan e tablo: store e dagh -> fallback e Postgres.
 *
 * ── Masir ───────────────────────────────────────────────────────────
 *   1. `redis-prices` (yek GET — kol e tablo tu yek kelid)
 *   2. agar khali ya ghat' bud -> Postgres (~50 radif e index-dar)
 *   3. spread ru khoruji e'mal mishe (hargez ru storage)
 *
 * ── Chera fallback ──────────────────────────────────────────────────
 * Redis store e DAGH ast، na manba' e haghighat. Agar bimofte (ya kelid ha
 * evict shan — ke ba `allkeys-lru` SAKET ettefagh mioftad)، site bayad
 * zende bemune. Postgres ~50 radif e index-dar dare: 5-15ms، faghat
 * cache-nashode.
 */
final readonly class BoardQuery
{
    public function __construct(
        private HotCachePort $cache,
        private SnapshotStorePort $store,
        private SpreadQuery $spreads,
        private ClockPort $clock,
    ) {}

    /**
     * @param  array<string, ProviderConfigDto>  $providers  baraye saghf e tazegi
     */
    public function board(array $providers = []): BoardDto
    {
        [$snapshots, $degraded] = $this->snapshots();

        if ($snapshots === []) {
            return new BoardDto([], degraded: $degraded);
        }

        $map = $this->spreadMap();
        $kinds = $this->providerKinds();
        $now = $this->clock->now();

        $rows = [];

        foreach ($snapshots as $snapshot) {
            $rows[] = $this->toRow($snapshot, $map, $kinds, $providers, $now);
        }

        // Tartib e namayesh = tartib e `Instrument::cases()`، ba'd provider.
        usort($rows, static function (BoardRowDto $a, BoardRowDto $b): int {
            $order = array_flip(array_column(Instrument::cases(), 'value'));

            return [$order[$a->instrument->value] ?? 99, $a->providerKey, $a->side->value]
                <=> [$order[$b->instrument->value] ?? 99, $b->providerKey, $b->side->value];
        });

        return new BoardDto(
            rows: $rows,
            version: $this->version(),
            spreadVersion: $this->spreadVersion(),
            degraded: $degraded,
        );
    }

    /**
     * @return array{0: array<string, SnapshotDto>, 1: bool}
     */
    private function snapshots(): array
    {
        try {
            $hot = $this->cache->board();

            if ($hot !== []) {
                return [$hot, false];
            }
        } catch (Throwable) {
            // Store e dagh ghat' ast — mirim soraghe Postgres.
        }

        try {
            // `degraded: true` — tablo durost ast vali az masir e kond miad.
            // UI mitune ba in yek neshane bede.
            return [$this->store->all(), true];
        } catch (Throwable) {
            return [[], true];
        }
    }

    /**
     * @param  array<string, array<string, SpreadPair>>  $map
     * @param  array<string, ProviderKind>  $kinds
     * @param  array<string, ProviderConfigDto>  $providers
     */
    private function toRow(
        SnapshotDto $snapshot,
        array $map,
        array $kinds,
        array $providers,
        CarbonImmutable $now,
    ): BoardRowDto {
        $spread = SpreadQuery::resolve($map, $snapshot->providerKey, $snapshot->instrument);

        $pair = SpreadPricer::applyPair($snapshot->price, $spread);

        $band = SpreadPricer::applyDayBand(
            high: $snapshot->dayHigh,
            low: $snapshot->dayLow,
            raw: $snapshot->price,
            buy: $pair['buy'],
            sell: $pair['sell'],
        );

        $age = $snapshot->ageSeconds($now);
        $limit = ($providers[$snapshot->providerKey] ?? null)?->freshnessFor($snapshot->instrument)
            ?? $snapshot->instrument->freshnessSeconds();

        return new BoardRowDto(
            providerKey: $snapshot->providerKey,
            instrument: $snapshot->instrument,
            side: $snapshot->side,
            raw: $snapshot->price,
            buy: $pair['buy'],
            sell: $pair['sell'],
            dayOpen: $snapshot->dayOpen,
            dayHigh: $band['high'],
            dayLow: $band['low'],
            // Invariant: spread e darsadi ru darsad e taghir neutral ast،
            // pas `changePercent` dast nemikhore.
            changePercent: $snapshot->changePercent,
            direction: $snapshot->direction,
            ageSeconds: $age,
            isStale: $age > $limit,
            isDerived: $this->isDerived($snapshot, $kinds),
            quotedAt: $snapshot->quotedAt->toIso8601String(),
        );
    }

    /**
     * Aya kharid/foroosh e in radif sakhtegi-e?
     *
     * Do halat e vaghei:
     *   - instrument khodesh jofte native dare (`xau-melted-g-irr` e tgju)
     *   - provider yek `platform` e vaghei-e (wallgold va …) ke nerkh e
     *     khodesh ro mide
     * Har chiz e digar arithmetic e MA-st va bayad barchasb bokhore.
     *
     * @param  array<string, ProviderKind>  $kinds
     */
    private function isDerived(SnapshotDto $snapshot, array $kinds): bool
    {
        if ($snapshot->instrument->hasNativeSides()) {
            return false;
        }

        // Default e `Reference` amdi-e: agar `kind` ro nadunim، bayad
        // MOHAFEZEKAR bashim va radif ro «moshtagh» e'lam konim. Eshtebah tu
        // in jahat yek footnote e ezafi-e؛ eshtebah tu jahat e dige
        // arithmetic e ma ro be onvan e nerkh e vaghei ye yek maghaze ja
        // mizane.
        return ! ($kinds[$snapshot->providerKey] ?? ProviderKind::Reference)->hasRealSides();
    }

    /** @return array<string, array<string, SpreadPair>> */
    private function spreadMap(): array
    {
        try {
            return $this->spreads->map();
        } catch (Throwable) {
            // Bedun e spread، gheymat e KHAM neshun dade mishe — ghalat tar
            // az un ast ke hichi neshun nadim.
            return [];
        }
    }

    /** @return array<string, ProviderKind> */
    private function providerKinds(): array
    {
        try {
            return $this->spreads->providerKinds();
        } catch (Throwable) {
            return [];
        }
    }

    private function version(): int
    {
        try {
            return $this->cache->boardVersion();
        } catch (Throwable) {
            return 0;
        }
    }

    private function spreadVersion(): int
    {
        try {
            return $this->cache->spreadVersion();
        } catch (Throwable) {
            return 0;
        }
    }
}
