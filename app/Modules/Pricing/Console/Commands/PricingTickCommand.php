<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Console\Commands;

use App\Modules\Pricing\Application\Actions\IngestTickAction;
use App\Modules\Pricing\Application\Dto\ProviderHealthDto;
use App\Modules\Pricing\Application\Dto\SinkResultDto;
use App\Modules\Pricing\Application\Dto\TickReportDto;
use Illuminate\Console\Command;

/**
 * Yek tick e gheymat.
 *
 * ── Command AMDAN nazok ast ─────────────────────────────────────────
 * Hich mantegh-i inja nist — faghat yek Action seda mizane va natije ro
 * chap mikone. Tu projeh ye marja' hamin command 8 mas'uliat dasht (lock،
 * fetch، sync، alert، warm، broadcast) ba try/catch haye parakande، va
 * baraye fahmidan e «agar Influx bimofte chi mishe» bayad kol e file ro
 * mikhundi.
 *
 * ── Chera scheduler nist ────────────────────────────────────────────
 * Container e `price-worker` yek halghe ye grid-aligned mizane:
 *   while :; do php artisan pricing:tick; sleep $((15 - $(date +%s) % 15)); done
 * Har tick yek process e taze (bedun e memory leak)، tartibi (bedun e
 * overlap)، va ru grid e 15 saniye. Scheduler e Laravel kamtarin daghighat
 * esh YEK DAGHIGHE-ast va aslan nemitune in ro bede.
 */
final class PricingTickCommand extends Command
{
    protected $signature = 'pricing:tick
        {--json : khoruji be sorat e JSON (baraye log/monitoring)}
        {--quiet-ok : agar hame chiz salem bud chizi chap nakon}
        {--force : `next_poll_at` ro nadide begir va hame ye provider haye fa\'al ro poll kon}';

    protected $description = 'Gereftan e gheymat az provider ha va zakhire tu Redis/Postgres/Influx.';

    public function handle(IngestTickAction $action): int
    {
        /*
         * 🔴 Chera `--force` lazem bud
         * Yek `pricing:tick` e DASTI ke bala-fasele ba'd az tick e worker
         * ejra she، «hich provider e fa'ali baraye poll nist» mide — chun
         * worker `next_poll_at` ro 15 saniye jolo gozashte. Un payam mesl e
         * yek khata ye CONFIG be nazar mirese («provider fa'al nadarim»)،
         * na mesl e «15 saniye sabr kon».
         *
         * Va daghighan hamun kari-e ke skill e `add-price-provider` mige:
         * «avval `pricing:tick` ro dasti bezan، `TickMonitor` ro check kon».
         * Ba `--force` un ravesh e kar vaghe'an kar mikone.
         *
         * Grid dast nemikhore: `TickTime` hanuz ru 15 saniye floor mishe،
         * pas do ta force tu yek panjare HAMUN timestamp ro minevisan —
         * ya'ni idempotent.
         */
        $report = $action->execute(
            gridSeconds: (int) config('pricing.tick_seconds', 15),
            force: (bool) $this->option('force'),
        );

        if ($this->option('json')) {
            $this->line((string) json_encode($report->toArray(), JSON_UNESCAPED_UNICODE));

            return $this->exitCode($report);
        }

        $this->render($report);

        return $this->exitCode($report);
    }

    private function render(TickReportDto $report): void
    {
        if ($report->skipped) {
            $this->warn("Tick rad shod: {$report->skipReason}");

            return;
        }

        $healthy = $report->failedProviders() === 0 && $report->failedSinks() === 0;

        if ($healthy && $this->option('quiet-ok')) {
            return;
        }

        $this->line(sprintf(
            '<fg=gray>%s</>  <options=bold>%d</> gheymat، <fg=%s>%d</> rad، %dms',
            $report->at->toString(),
            $report->accepted,
            $report->rejectedCount() > 0 ? 'yellow' : 'gray',
            $report->rejectedCount(),
            $report->durationMs,
        ));

        foreach ($report->health as $health) {
            $this->line($this->providerLine($health));
        }

        foreach ($report->sinks as $sink) {
            $this->line($this->sinkLine($sink));
        }

        // Dalil e rad shodan ha group mishe — «chera in symbol ru tablo
        // nist» bayad yek negah bashe، na khundan e log e kham.
        if ($report->rejectedCount() > 0) {
            $groups = [];

            foreach ($report->rejections as $rejection) {
                $groups[$rejection->category()][] = $rejection->instrument->value;
            }

            foreach ($groups as $category => $symbols) {
                $this->line(sprintf(
                    '  <fg=yellow>rad</> %-12s %s',
                    $category,
                    implode('، ', array_slice($symbols, 0, 6)).(count($symbols) > 6 ? ' …' : ''),
                ));
            }
        }

        foreach ($report->unmapped as $provider => $labels) {
            $this->line(sprintf(
                '  <fg=red>map-nashode</> %s: %s',
                $provider,
                implode('، ', array_slice($labels, 0, 5)),
            ));
        }
    }

    private function providerLine(ProviderHealthDto $health): string
    {
        return $health->succeeded
            ? sprintf(
                '  <fg=green>✔</> %-8s %5dms  %2d symbol',
                $health->providerKey,
                $health->latencyMs,
                $health->symbolCount,
            )
            : sprintf(
                '  <fg=red>✘</> %-8s %s',
                $health->providerKey,
                mb_substr((string) $health->error, 0, 90),
            );
    }

    private function sinkLine(SinkResultDto $sink): string
    {
        if ($sink->succeeded) {
            return sprintf('  <fg=green>✔</> %-8s %2d radif  %dms', $sink->name, $sink->rows, $sink->durationMs);
        }

        $spooled = $sink->spooled > 0 ? " ({$sink->spooled} khat spool shod)" : '';

        return sprintf(
            '  <fg=red>✘</> %-8s %s%s',
            $sink->name,
            mb_substr((string) $sink->error, 0, 80),
            $spooled,
        );
    }

    /**
     * Exit code.
     *
     * ⚠️ Shekast e yek provider ya yek sink `FAILURE` NIST: halghe ye worker
     * nabayad be khatere yek ghat'i ye gozara motevaghef beshe. Faghat
     * «tick aslan ejra nashod» shekast hesab mishe.
     */
    private function exitCode(TickReportDto $report): int
    {
        return $report->skipped && $report->skipReason !== null
            && ! str_contains($report->skipReason, 'hanuz dar hale ejra')
            ? self::FAILURE
            : self::SUCCESS;
    }
}
