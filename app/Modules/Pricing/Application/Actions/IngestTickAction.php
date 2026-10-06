<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Actions;

use App\Modules\Pricing\Application\Dto\ProviderConfigDto;
use App\Modules\Pricing\Application\Dto\TickReportDto;
use App\Modules\Pricing\Application\Pipeline\TickContext;
use App\Modules\Pricing\Application\Pipeline\TickPipeline;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Domain\Ports\QuoteSourcePort;
use App\Modules\Pricing\Domain\ValueObjects\TickTime;
use App\Modules\Pricing\Infrastructure\Sources\SourceManager;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Yek tick: gereftan e provider ha -> ejra ye pipeline -> sabt e salamat.
 *
 * Command faghat in ro seda mizane. Hich mantegh-i tu command nist.
 */
final readonly class IngestTickAction
{
    private const LOCK_KEY = 'pricing:tick';

    public function __construct(
        private TickPipeline $pipeline,
        private ProviderQuery $providers,
        private SourceManager $sources,
        private HotCachePort $cache,
        private ClockPort $clock,
        private RecordProviderHealthAction $health,
        private ?LoggerInterface $logger = null,
    ) {}

    public function execute(int $gridSeconds = 15, bool $force = false): TickReportDto
    {
        $at = $this->clock->tick($gridSeconds);

        // ── Lock ────────────────────────────────────────────────
        // 🔴 In yek guard e PERFORMANCE ast، na correctness: hame ye sink ha
        // idempotent-and (upsert e Postgres، akharin-write e Redis، va
        // timestamp e floor-shode ye Influx). Pas agar Redis — ke backend e
        // lock ast — ghat' bashe، BEDUN e lock edame midim. Motevaghef
        // kardan e feed be dalil e nabudan e yek guard e ekhtiari، yek
        // ghat'i ye farei ro be ghat'i ye kamel tabdil mikone.
        $lock = $this->acquireLock($gridSeconds);

        if ($lock === false) {
            return new TickReportDto(
                at: $at,
                skipped: true,
                skipReason: 'tick e ghabli hanuz dar hale ejra-st.',
            );
        }

        try {
            $report = $this->tick($at, $force);
        } finally {
            $this->release($lock);
        }

        // Daftarche ye `TickMonitor` (P3-09) — izole: monitoring hargez
        // nabayad tick ro bokoshe.
        $this->recordReport($report);

        return $report;
    }

    private function recordReport(TickReportDto $report): void
    {
        try {
            $this->cache->putTickReport($report->toArray());
        } catch (Throwable) {
            // Redis ghat' — hamun ghat'i ghablan tu sink ha sabt shode.
        }
    }

    private function tick(TickTime $at, bool $force): TickReportDto
    {
        $configs = $this->loadProviders($at, $force);

        if ($configs === []) {
            return new TickReportDto(
                at: $at,
                skipped: true,
                skipReason: 'hich provider e fa\'ali baraye poll nist.',
            );
        }

        [$sources, $usable] = $this->buildSources($configs);

        if ($sources === []) {
            return new TickReportDto(
                at: $at,
                skipped: true,
                skipReason: 'hich Source e ghabl e sakht nabud (driver e nashenas?).',
            );
        }

        $anchors = array_keys(array_filter($usable, static fn (ProviderConfigDto $config): bool => $config->isTrustAnchor));

        try {
            $anchors = $this->providers->trustAnchorKeys();
        } catch (Throwable) {
            // Dar ghat'i ye DB az config e akharin tick estefade mishe.
        }

        $report = $this->pipeline->run(new TickContext($at, $sources, $usable, $anchors));

        $this->recordHealth($report, $usable);

        return $report;
    }

    /**
     * Lista ye provider ha — ba fallback baraye ghat'i ye Postgres.
     *
     * 🔴 Agar Postgres bimofte، tick bayad EDAME bede: akharin lista ye
     * salem az store e dagh khunde mishe (bedun e TTL). Bedun e in، yek
     * ghat'i ye DB tabdil mishe be yek ghat'i ye kamel e feed — dar hali ke
     * Redis va Influx salem-and va mitunestan kar konan.
     *
     * `$force` -> `next_poll_at` nadide gerefte mishe (flag e `--force`).
     * Faghat yek darvaze ye ZAMANBANDI ro rad mikone؛ hich mohafez e
     * dige-i (tazegi، `PriceSanity`، grid) dast nemikhore.
     *
     * @return array<string, ProviderConfigDto>
     */
    private function loadProviders(TickTime $at, bool $force = false): array
    {
        try {
            $configs = $force
                ? $this->providers->allActive()
                : $this->providers->dueForPoll($at->at);

            // Faghat vaghti chizi hast cache mikonim — vagarna yek jadval e
            // khali ye movaghat، fallback ro baraye hamishe khali mikone.
            if ($configs !== []) {
                $this->cacheProviders($configs);
            }

            return $configs;
        } catch (Throwable $e) {
            $this->logger?->warning('pricing.providers_unavailable', ['error' => $e->getMessage()]);

            return $this->cachedProviders();
        }
    }

    /** @param array<string, ProviderConfigDto> $configs */
    private function cacheProviders(array $configs): void
    {
        try {
            $this->cache->putProviders(array_map(
                static fn (ProviderConfigDto $config): array => $config->toArray(),
                $configs,
            ));
        } catch (Throwable) {
            // Redis ghat' ast — tu `PersistStage` sabt mishe.
        }
    }

    /** @return array<string, ProviderConfigDto> */
    private function cachedProviders(): array
    {
        try {
            $rows = $this->cache->providers();
        } catch (Throwable) {
            return [];
        }

        if ($rows === null) {
            return [];
        }

        $configs = [];

        foreach ($rows as $key => $row) {
            $configs[(string) $key] = ProviderConfigDto::fromRow($row);
        }

        return $configs;
    }

    /**
     * @param  array<string, ProviderConfigDto>  $configs
     * @return array{0: array<int, QuoteSourcePort>, 1: array<string, ProviderConfigDto>}
     */
    private function buildSources(array $configs): array
    {
        $sources = [];
        $usable = [];

        foreach ($configs as $key => $config) {
            try {
                $sources[] = $this->sources->make($config);
                $usable[$key] = $config;
            } catch (Throwable $e) {
                // Driver e nashenas = eshtebah e config، na ghat'i. Baghi ye
                // provider ha nabayad zarar bebinan.
                $this->logger?->warning('pricing.unknown_driver', [
                    'provider' => $key,
                    'driver' => $config->driverKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [$sources, $usable];
    }

    /** @param array<string, ProviderConfigDto> $configs */
    private function recordHealth(TickReportDto $report, array $configs): void
    {
        if ($report->health === []) {
            return;
        }

        try {
            $intervals = array_map(
                static fn (ProviderConfigDto $config): int => $config->pollIntervalSeconds,
                $configs,
            );

            $this->health->execute($report->health, $intervals, $this->clock->now());
        } catch (Throwable $e) {
            // Postgres ghat' ast — gheymat ha ghablan neshastan (ya tu Redis
            // ya tu spool). Salamat e provider kam-ahamiyat tarin chiz ast.
            $this->logger?->warning('pricing.health_write_failed', ['error' => $e->getMessage()]);
        }
    }

    private function acquireLock(int $gridSeconds): mixed
    {
        try {
            $store = Cache::getStore();

            if (! $store instanceof LockProvider) {
                return null;
            }

            // Kamtar az fasele ye tick: agar yek tick gir kone، tick e ba'di
            // hadaksar yek dore sabr mikone، na abadiat.
            $lock = $store->lock(self::LOCK_KEY, max(1, $gridSeconds - 1));

            return $lock->get() ? $lock : false;
        } catch (Throwable $e) {
            // Backend e lock ghat' ast -> bedun e lock edame.
            $this->logger?->warning('pricing.lock_unavailable', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function release(mixed $lock): void
    {
        if ($lock === null || $lock === false) {
            return;
        }

        try {
            $lock->release();
        } catch (Throwable) {
            // Lock khodesh ba TTL mimire.
        }
    }
}
