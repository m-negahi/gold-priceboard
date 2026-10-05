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
        /**
         * Agar zarabān e nevisande az in ghadim tar bashe، store e dagh
         * GHABL E E'TEMAD nist. `0` ya'ni khamoosh.
         *
         * Dalil e kamel tu `snapshots()`.
         */
        private int $freezeAfterSeconds = 120,
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
            $row = $this->toRow($snapshot, $map, $kinds, $providers, $now);

            // 🔴 `hide_after_seconds` INJA e'mal mishe، na ru write.
            //
            // Sen har saniye avaz mishe، pas natije ash nabayad bake beshe —
            // hamun mantegh e spread. Alave bar un، admin ke `hide_after` ro
            // tu panel avaz kone bayad FORI asar bebine، na tick e ba'd.
            if ($row === null) {
                continue;
            }

            $rows[] = $row;
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

            if ($hot !== [] && $this->writerIsAlive()) {
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
    ): ?BoardRowDto {
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
        $config = $providers[$snapshot->providerKey] ?? null;
        $limit = $config?->freshnessFor($snapshot->instrument)
            ?? $snapshot->instrument->freshnessSeconds();

        // Onghadr ghadimi ke dige neshun dadanesh ghalat ast.
        //
        // ⚠️ Bedun e config e provider، hazf NEMIKONIM: nadunestan e tanzim
        // nabayad be hazf e saket e radif ha tarjome beshe.
        if ($config !== null && $age > $config->hideAfterFor($snapshot->instrument)) {
            return null;
        }

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
     * 🔴 Aya nevisande zende ast?
     *
     * ── Chera in check vojud dare ───────────────────────────────────
     * Bad tarin nou' e ghat'i e `redis-prices` un ast ke SALEM be nazar
     * mirese. Vaghti memory por she (`--maxmemory-policy noeviction`)، Redis
     * write ha ro ba `OOM command not allowed` rad mikone **vali read ha ro
     * kamel javab mide** va `PING` ham salem ast.
     *
     * Natije ye ghabli: `isAvailable()` `true` mid، `board()` data mid، va
     * `BoardQuery` `degraded=false` e'lam mikard — pas masir e fallback e
     * Postgres HICHVAGHT roshan nemishod، dar hali ke Postgres data ye TAZE
     * dasht. Tablo ru akharin gheymat e neveshte-shode YAKH mizad va tanha
     * neshane `ageSeconds` bud ke bala mirafet.
     *
     * Zarabān khodesh yek WRITE ast، pas tu halat e OOM be-rooz nemishe.
     * Ghadimi budan esh = «hich kas tablo ro nemineviseh» — che Redis write
     * ro rad kone، che khod e `price-worker` morde bashe. Har do bayad be
     * Postgres fallback konan va `degraded` e'lam shan.
     *
     * ⚠️ Chera sen e RADIF ha kafi nist: shab ha (bazaar e baste) hame ye
     * radif ha be-haghghe ghadimi-and va Postgres ham hamun data ro dare.
     * «Hame bayat-an» ba «hich kas nemineviseh» yeki NIST.
     */
    private function writerIsAlive(): bool
    {
        if ($this->freezeAfterSeconds <= 0) {
            return true;
        }

        try {
            $heartbeat = $this->cache->heartbeatAt();
        } catch (Throwable) {
            return false;
        }

        if ($heartbeat === null) {
            // Hanuz hich tick-i nanveshte (ya kelid pak shode). Masir e
            // Postgres ham hamun data ro mide، pas hazine ash nazdik e sefr
            // ast va `degraded` sadeghane-ast.
            return false;
        }

        return $this->clock->now()->timestamp - $heartbeat <= $this->freezeAfterSeconds;
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
