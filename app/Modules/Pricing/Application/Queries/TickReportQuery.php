<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Domain\Ports\HotCachePort;
use Throwable;

/**
 * Akharin gozaresh haye tick baraye `TickMonitor` (P3-09/P4-18).
 *
 * Manba': daftarche ye gozara ye hot cache (50 ta ye akhar). Redis ghat'
 * bashe lista khali-e — monitor khodesh neshane ye ghat'i ro az hamin
 * khali budan mifahme.
 *
 * `summarize` / `rejectionRows` / `filterMeta` mantegh e MAHZ ru array-and
 * (bedun e IO) — data-shaping inja-st، na tu Blade.
 */
final readonly class TickReportQuery
{
    public function __construct(private HotCachePort $cache) {}

    /** @return array<int, array<string, mixed>> jadid tarin aval */
    public function recent(int $limit = 20): array
    {
        try {
            return $this->cache->tickReports($limit);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Kholase ye per-provider ru tick haye EJRA-shode.
     *
     * Tick e «بدون اجرا» (skipped) hesab nemishe — provider-i nobat nadasht،
     * pas na movafagh bude na nakam.
     *
     * @param  array<int, array<string, mixed>>  $reports  jadid tarin aval
     * @return array<string, array<string, mixed>> key e provider -> amar
     */
    public function summarize(array $reports): array
    {
        $stats = [];

        foreach ($reports as $report) {
            if ((bool) ($report['skipped'] ?? false)) {
                continue;
            }

            foreach ((array) ($report['providers'] ?? []) as $key => $provider) {
                $provider = (array) $provider;
                $s = $stats[$key] ??= [
                    'ticks' => 0, 'succeeded' => 0, 'failed' => 0, 'retried' => 0,
                    'latency_sum' => 0, 'latency_n' => 0, 'max_latency_ms' => 0,
                    'rejections' => 0, 'by_category' => [],
                    'last_error' => null, 'last_error_at' => null,
                ];

                $s['ticks']++;

                if ((bool) ($provider['succeeded'] ?? false)) {
                    $s['succeeded']++;
                    $latency = (int) ($provider['latency_ms'] ?? 0);

                    // Talash e shekaste latency e vaghei nadare (0) — miangin
                    // ro dorugh-ki paeen mikeshe.
                    if ($latency > 0) {
                        $s['latency_sum'] += $latency;
                        $s['latency_n']++;
                        $s['max_latency_ms'] = max($s['max_latency_ms'], $latency);
                    }
                } else {
                    $s['failed']++;

                    // Jadid tarin aval — avvalin khata ye dide-shode akharin-e.
                    if ($s['last_error'] === null) {
                        $s['last_error'] = $provider['error'] ?? null;
                        $s['last_error_at'] = $report['at'] ?? null;
                    }
                }

                if ((bool) ($provider['retried'] ?? false)) {
                    $s['retried']++;
                }

                $stats[$key] = $s;
            }

            foreach ((array) ($report['rejections'] ?? []) as $rejection) {
                $key = (string) ($rejection['provider'] ?? '');
                $category = (string) ($rejection['category'] ?? '');

                if (! isset($stats[$key])) {
                    continue;
                }

                $stats[$key]['rejections']++;
                $stats[$key]['by_category'][$category] = ($stats[$key]['by_category'][$category] ?? 0) + 1;
            }
        }

        $summary = [];

        foreach ($stats as $key => $s) {
            $summary[$key] = [
                'ticks' => $s['ticks'],
                'succeeded' => $s['succeeded'],
                'failed' => $s['failed'],
                'success_rate' => $s['ticks'] > 0 ? (int) round($s['succeeded'] * 100 / $s['ticks']) : 0,
                'avg_latency_ms' => $s['latency_n'] > 0 ? (int) round($s['latency_sum'] / $s['latency_n']) : 0,
                'max_latency_ms' => $s['max_latency_ms'],
                'retried' => $s['retried'],
                'rejections' => $s['rejections'],
                'by_category' => $s['by_category'],
                'last_error' => $s['last_error'],
                'last_error_at' => $s['last_error_at'],
            ];
        }

        return $summary;
    }

    /**
     * Hame ye rad ha ye hame ye tick ha، saf — baraye nama ye «جدول ردها».
     *
     * @param  array<int, array<string, mixed>>  $reports  jadid tarin aval
     * @return list<array<string, mixed>>
     */
    public function rejectionRows(array $reports): array
    {
        $rows = [];

        foreach ($reports as $report) {
            foreach ((array) ($report['rejections'] ?? []) as $rejection) {
                $rows[] = ['at' => $report['at'] ?? null, ...(array) $rejection];
            }
        }

        return $rows;
    }

    /**
     * Kelid haye filter e YEK tick — Alpine faghat ba in ha show/hide mikone.
     *
     * `problem` = har chizi ke admin bayad bebine: provider e nakam، rad، ya
     * label e na-shenakhte (markup e upstream avaz shode).
     *
     * @param  array<string, mixed>  $report
     * @return array{providers: list<string>, failed: list<string>, symbols: list<string>, categories: list<string>, problem: bool, skipped: bool}
     */
    public function filterMeta(array $report): array
    {
        $providers = [];
        $failed = [];

        foreach ((array) ($report['providers'] ?? []) as $key => $provider) {
            $providers[] = (string) $key;

            if (! (bool) (((array) $provider)['succeeded'] ?? false)) {
                $failed[] = (string) $key;
            }
        }

        $rejections = (array) ($report['rejections'] ?? []);

        foreach ($rejections as $rejection) {
            // Provider-i ke faghat rad dare (bedun e entry e health) ham bayad
            // ba filter e provider peyda she.
            $providers[] = (string) ($rejection['provider'] ?? '');
        }

        $symbols = array_values(array_unique(array_map(static fn ($r): string => (string) ($r['symbol'] ?? ''), $rejections)));
        $categories = array_values(array_unique(array_map(static fn ($r): string => (string) ($r['category'] ?? ''), $rejections)));

        return [
            'providers' => array_values(array_unique(array_filter($providers, static fn (string $p): bool => $p !== ''))),
            'failed' => $failed,
            'symbols' => $symbols,
            'categories' => $categories,
            'problem' => $failed !== [] || $rejections !== [] || (array) ($report['unmapped'] ?? []) !== [],
            'skipped' => (bool) ($report['skipped'] ?? false),
        ];
    }
}
