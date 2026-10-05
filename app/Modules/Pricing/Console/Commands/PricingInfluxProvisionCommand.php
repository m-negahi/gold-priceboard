<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Console\Commands;

use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxAdmin;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sakht e bucket ha، task ha va token haye Influx — IDEMPOTENT.
 *
 * In TANHA rah e sakhtan e in chiz ha-st. Hich click-i tu UI e Influx،
 * vagarna staging va production ba ham fargh mikonan va hich kas nemidune
 * chera.
 *
 * Manba' e haghighat e task ha: `resources/influx/tasks/*.flux`.
 */
final class PricingInfluxProvisionCommand extends Command
{
    protected $signature = 'pricing:influx:provision
        {--tokens : token haye read/write ro ham besaz va chap kon}';

    protected $description = 'Sakht/be-rooz kardan e bucket ha va task haye Influx (idempotent).';

    /**
     * Retention e har bucket be saniye. `0` = bi-nahayat.
     *
     * `ticks` kutah ast (hajm e bala), `candles_1m` 90 rooz ta chart e
     * se-mahe kar kone، va bazeh haye bozorg hamishegi-an (hajm eshun kam ast).
     */
    private const BUCKETS = [
        'ticks' => 30 * 86_400,
        'candles_1m' => 90 * 86_400,
        'candles_5m' => 180 * 86_400,
        'candles_15m' => 400 * 86_400,
        'candles_1h' => 1_095 * 86_400,
        'candles_4h' => 0,
        'candles_1d' => 0,
    ];

    private const TASKS = [
        'candles_1m', 'candles_5m', 'candles_15m',
        'candles_1h', 'candles_4h', 'candles_1d',
    ];

    public function handle(): int
    {
        $config = (array) config('pricing.influx', []);
        $config['admin_token'] = env('INFLUX_ADMIN_TOKEN', '');

        $admin = new InfluxAdmin($config);

        if (! $admin->isAvailable()) {
            $this->error('Influx dar dastres nist. `docker compose up -d influxdb` ro check kon.');

            return self::FAILURE;
        }

        try {
            $orgId = $admin->orgId();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("Org: {$config['org']} ({$orgId})");
        $this->newLine();

        if (! $this->provisionBuckets($admin, $orgId)) {
            return self::FAILURE;
        }

        if (! $this->provisionTasks($admin, $orgId, (string) ($config['org'] ?? 'priceboard'))) {
            return self::FAILURE;
        }

        if ($this->option('tokens')) {
            $this->provisionTokens($admin, $orgId);
        }

        $this->newLine();
        $this->info('Provision tamum shod.');

        return self::SUCCESS;
    }

    private function provisionBuckets(InfluxAdmin $admin, string $orgId): bool
    {
        $this->line('<comment>Bucket ha</comment>');

        foreach (self::BUCKETS as $name => $retention) {
            try {
                $outcome = $admin->ensureBucket($name, $retention, $orgId);
            } catch (Throwable $e) {
                $this->error("  {$name}: {$e->getMessage()}");

                return false;
            }

            $this->line(sprintf(
                '  %-14s %-10s retention: %s',
                $name,
                $this->badge($outcome),
                $retention === 0 ? 'bi-nahayat' : round($retention / 86_400).' rooz',
            ));
        }

        return true;
    }

    private function provisionTasks(InfluxAdmin $admin, string $orgId, string $org): bool
    {
        $this->newLine();
        $this->line('<comment>Task ha</comment>');

        $tzOffset = (string) config('pricing.tz_offset', '3h30m');

        foreach (self::TASKS as $name) {
            $path = resource_path("influx/tasks/{$name}.flux");

            if (! is_file($path)) {
                $this->error("  {$name}: file e flux peyda nashod ({$path}).");

                return false;
            }

            $flux = str_replace(
                ['{{org}}', '{{tz_offset}}'],
                [$org, $tzOffset],
                (string) file_get_contents($path),
            );

            try {
                $outcome = $admin->ensureTask($name, $flux, $orgId);
            } catch (Throwable $e) {
                $this->error("  {$name}: {$e->getMessage()}");

                return false;
            }

            $this->line(sprintf('  %-14s %s', $name, $this->badge($outcome)));
        }

        return true;
    }

    private function provisionTokens(InfluxAdmin $admin, string $orgId): void
    {
        $this->newLine();
        $this->line('<comment>Token ha</comment>');

        $buckets = array_keys(self::BUCKETS);

        $specs = [
            // Worker: ru `ticks` mineviseh، va ru `candles_1m` ham — chun
            // ba'd az drain bayad candle haye un bazeh ro dobare besaze
            // (`rebuildMinuteCandles`).
            //
            // ⚠️ `read` ru HAR DO bucket lazem ast، na faghat `ticks`:
            // Flux baraye resolve kardan e ESM e bucket (hatta bucket e
            // MAGHSAD e `to()`) ejaze ye READ mikhad. Ba faghat `write`،
            // javab `404 could not find bucket "candles_1m"` ast.
            'priceboard-write' => [
                'read' => ['ticks', 'candles_1m'],
                'write' => ['ticks', 'candles_1m'],
            ],
            // Web: faghat mikhune. Lo raftanesh ya'ni kasi mitune bebine,
            // na inke tarikhche ro ja'l kone.
            'priceboard-read' => ['read' => $buckets, 'write' => []],
        ];

        foreach ($specs as $description => $spec) {
            if ($admin->tokenExists($description)) {
                $this->line(sprintf('  %-20s %s', $description, $this->badge('unchanged')));

                continue;
            }

            try {
                $token = $admin->createToken($description, $orgId, $spec['read'], $spec['write']);
            } catch (Throwable $e) {
                $this->error("  {$description}: {$e->getMessage()}");

                continue;
            }

            $this->line(sprintf('  %-20s %s', $description, $this->badge('created')));
            $this->line("    <fg=yellow>{$token}</>");
        }

        $this->newLine();
        $this->warn('Token haye bala ro tu `.env` bezar:');
        $this->line('  PRICING_INFLUX_WRITE_TOKEN=…');
        $this->line('  PRICING_INFLUX_READ_TOKEN=…');
        $this->warn('Ba`d: `docker compose up -d --force-recreate` (taghir e .env ba restart sovar nemishe).');
    }

    private function badge(string $outcome): string
    {
        return match ($outcome) {
            'created' => '<fg=green>sakhte-shod</>',
            'updated' => '<fg=yellow>be-rooz-shod</>',
            default => '<fg=gray>bi-taghir</>',
        };
    }
}
