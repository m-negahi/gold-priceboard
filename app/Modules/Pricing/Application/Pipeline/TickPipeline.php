<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Pipeline;

use App\Modules\Pricing\Application\Dto\TickReportDto;
use App\Modules\Pricing\Domain\Enums\StagePolicy;
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Ejra ye marhale haye yek tick va e'mal e policy e har kodum.
 *
 * ── Jadval e «chi mitune tick ro bokoshe» ───────────────────────────
 *   Critical -> shekast = tick motevaghef mishe
 *   Isolated -> shekast = log + edame
 *
 * Faghat `LoadPrevious` critical ast: bedun e snapshot haye ghabli،
 * `PriceSanity` nemitune enheraf ro besanje va `QuoteEnricher` nemitune
 * band e rooz ro besaze — ya'ni har gheymat e kharab-i rad mishe va har
 * `dayHigh` reset mishe. Edame dadan badtar az motevaghef shodan ast.
 *
 * Hame chiz e digar izole-st. Makhsusan HICH CHIZ tu `Persist` critical
 * nist: Influx ghat' bashe va tablo salem — in yek halat e salem ast.
 *
 * ── Chera `warning` va na `report()` ────────────────────────────────
 * Yek ghat'i ye yek-sa'ate = 240 tick. Ba `report()` Sentry 240 event e
 * YEKSAN migire va quota misuze baraye yek chiz-i ke ma ghablan tu
 * `TickReportDto` sabt kardim.
 */
final readonly class TickPipeline
{
    /** @param array<int, Stage> $stages */
    public function __construct(
        private SnapshotStorePort $store,
        private array $stages,
        private ?LoggerInterface $logger = null,
    ) {}

    public function run(TickContext $context): TickReportDto
    {
        $startedAt = microtime(true);

        // ── marhale ye critical: gheymat haye ghabli ─────────────
        try {
            $context->previous = $this->store->all();
        } catch (Throwable $e) {
            // Postgres ghat' ast. Edame bedim ya'ni har snapshot be onvan e
            // «avvalin tick» didе mishe: band e rooz reset mishe va check e
            // enheraf khamoosh — ya'ni daghighan vaghti DB kharab ast،
            // mohafez haye ma ham khamoosh mishan.
            $this->warn('pricing.previous_unavailable', ['error' => $e->getMessage()]);

            return new TickReportDto(
                at: $context->at,
                durationMs: $this->ms($startedAt),
                skipped: true,
                skipReason: 'snapshot haye ghabli khunde nashodan: '.$e->getMessage(),
            );
        }

        foreach ($this->stages as $stage) {
            try {
                $stage->handle($context);
            } catch (Throwable $e) {
                if ($stage->policy() === StagePolicy::Critical) {
                    $this->warn('pricing.stage_failed', [
                        'stage' => $stage->name(),
                        'policy' => 'critical',
                        'error' => $e->getMessage(),
                    ]);

                    return new TickReportDto(
                        at: $context->at,
                        health: $context->health,
                        rejections: $context->rejections,
                        sinks: $context->sinks,
                        durationMs: $this->ms($startedAt),
                        skipped: true,
                        skipReason: "marhale ye «{$stage->name()}» shekast khord: {$e->getMessage()}",
                    );
                }

                $this->warn('pricing.stage_failed', [
                    'stage' => $stage->name(),
                    'policy' => 'isolated',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $report = new TickReportDto(
            at: $context->at,
            accepted: count($context->snapshots),
            health: $context->health,
            rejections: $context->rejections,
            sinks: $context->sinks,
            unmapped: $context->unmapped,
            durationMs: $this->ms($startedAt),
        );

        $this->audit($report);

        return $report;
    }

    /**
     * Rad e momayezi: «kodum provider che gheymat-i dad va chi neshast».
     * Yek JSON dar har khat — amade baraye Filebeat/Kibana.
     */
    private function audit(TickReportDto $report): void
    {
        $this->logger?->info('pricing.tick', $report->toArray());

        foreach ($report->unmapped as $provider => $labels) {
            // Label e map-nashode ya'ni markup/kelid e upstream avaz shode.
            // Bayad tu YEK tick ma'lum beshe، na ba'd az chand hafte.
            $this->logger?->warning('pricing.unmapped', [
                'provider' => $provider,
                'labels' => $labels,
            ]);
        }
    }

    /** @param array<string, mixed> $context */
    private function warn(string $message, array $context): void
    {
        $this->logger?->warning($message, $context);
    }

    private function ms(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
