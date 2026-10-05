<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Console\Commands;

use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxAdmin;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxSpool;
use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxTimeSeries;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Salamat e laye ye time-series.
 *
 * ── Chera vojud dare ────────────────────────────────────────────────
 * Task e Influx mitune SAKET bemire: yek type mismatch (`acc.count + 1`
 * jaye `1.0`)، OOM، ya taghir e nasakhe. Neveshtan e tick edame mide،
 * chart e `m1` ham kar mikone (chun mostaghim az `ticks` mikhune)، vali
 * `m5`–`d1` yavash yavash saf mishan.
 *
 * Bedun e in check، avvalin kasi ke mifahme yek KARBAR ast — hafte ha
 * ba'd، vaghti un panjere dige tu retention e tick e kham nist va
 * ghabl e jobran ast.
 *
 * Har 5 daghighe tavasot e scheduler ejra mishe.
 */
final class PricingInfluxHealthCommand extends Command
{
    protected $signature = 'pricing:influx:health {--json : khoruji be sorat e JSON}';

    protected $description = 'Check e salamat e Influx: bucket ha، task ha va tazegi ye candle ha.';

    /** Akharin bar e `candles_1m` nabayad bishtar az in ghadimi bashe. */
    private const MAX_CANDLE_AGE_SECONDS = 180;

    public function handle(): int
    {
        $config = (array) config('pricing.influx', []);
        $config['admin_token'] = env('INFLUX_ADMIN_TOKEN', '');

        $series = new InfluxTimeSeries($config);
        $admin = new InfluxAdmin($config);

        $checks = [];

        $checks['reachable'] = $series->isAvailable()
            ? ['ok' => true, 'detail' => 'Influx javab mide.']
            : ['ok' => false, 'detail' => 'Influx dar dastres nist.'];

        if ($checks['reachable']['ok']) {
            $checks['candles_1m'] = $this->checkFreshness($series, 'candles_1m', self::MAX_CANDLE_AGE_SECONDS);
            $checks['candles_1d'] = $this->checkDailyBar($series);
            $checks['tasks'] = $this->checkTasks($admin);
        }

        $checks['spool'] = $this->checkSpool($config);

        $healthy = ! in_array(false, array_column($checks, 'ok'), true);

        if ($this->option('json')) {
            $this->line((string) json_encode(
                ['healthy' => $healthy, 'checks' => $checks],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            ));
        } else {
            foreach ($checks as $name => $check) {
                $this->line(sprintf(
                    '  %s %-14s %s',
                    $check['ok'] ? '<fg=green>✔</>' : '<fg=red>✘</>',
                    $name,
                    $check['detail'],
                ));
            }
        }

        if (! $healthy) {
            Log::channel($this->channel())->warning('pricing.influx_unhealthy', ['checks' => $checks]);
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{ok: bool, detail: string} */
    private function checkFreshness(InfluxTimeSeries $series, string $bucket, int $maxAge): array
    {
        try {
            $latest = $series->latestCandleAt($bucket);
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => "query shekast khord: {$e->getMessage()}"];
        }

        if ($latest === null) {
            return ['ok' => false, 'detail' => "hich candle-i tu «{$bucket}» nist — task ejra nashode?"];
        }

        $age = CarbonImmutable::now()->getTimestamp() - $latest->getTimestamp();

        return [
            'ok' => $age <= $maxAge,
            'detail' => sprintf('akharin bar %d saniye pish (saghf %ds).', $age, $maxAge),
        ];
    }

    /**
     * Candle e ruzane bayad mal e EMRUZ be vaght e Tehran bashe.
     *
     * In hamun test-i-e ke `timezone.fixed(3h30m)` ro migire: bedun esh bar
     * e ruzane be nime-shab e UTC (= 03:30 Tehran) align mishe.
     *
     * @return array{ok: bool, detail: string}
     */
    private function checkDailyBar(InfluxTimeSeries $series): array
    {
        try {
            $latest = $series->latestCandleAt('candles_1d');
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => "query shekast khord: {$e->getMessage()}"];
        }

        if ($latest === null) {
            return ['ok' => false, 'detail' => 'hich candle ye ruzane-i nist.'];
        }

        $tehran = $latest->setTimezone('Asia/Tehran');
        $today = CarbonImmutable::now('Asia/Tehran')->startOfDay();

        return [
            'ok' => $tehran->startOfDay()->equalTo($today),
            'detail' => 'akharin bar e ruzane: '.$tehran->format('Y-m-d H:i').' (Tehran).',
        ];
    }

    /** @return array{ok: bool, detail: string} */
    private function checkTasks(InfluxAdmin $admin): array
    {
        try {
            $tasks = $admin->tasks();
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => "khundan e task ha shekast khord: {$e->getMessage()}"];
        }

        if ($tasks === []) {
            return ['ok' => false, 'detail' => 'hich task-i nist — `pricing:influx:provision` ro bezan.'];
        }

        $broken = [];

        foreach ($tasks as $name => $task) {
            if ($task['status'] !== 'active') {
                $broken[] = "{$name} (gheyr e fa'al)";

                continue;
            }

            $runs = $admin->taskRuns($task['id'], 1);

            if ($runs !== [] && $runs[0]['status'] === 'failed') {
                $broken[] = "{$name} (akharin ejra shekast khord)";
            }
        }

        return $broken === []
            ? ['ok' => true, 'detail' => count($tasks).' task، hame salem.']
            : ['ok' => false, 'detail' => 'moshkel: '.implode('، ', $broken)];
    }

    /**
     * Spool bayad khali bashe. Por budanesh ya'ni Influx ghat' bude va
     * `drain` hanuz jobran nakarde.
     *
     * @param  array<string, mixed>  $config
     * @return array{ok: bool, detail: string}
     */
    private function checkSpool(array $config): array
    {
        $size = (new InfluxSpool((int) ($config['spool_max_lines'] ?? 40_000)))->size();

        return [
            'ok' => $size === 0,
            'detail' => $size === 0 ? 'khali.' : "{$size} khat montazer e replay.",
        ];
    }

    private function channel(): string
    {
        return (string) config('pricing.log.channel', 'stack');
    }
}
