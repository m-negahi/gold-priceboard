<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Domain\Ports\UpstreamLogPort;
use Throwable;

/**
 * Call haye MA be provider ha baraye safhe haye monitor (P4-14/P4-18/P4-20).
 *
 * Khoruji array e view-ready ast (hamun olgu ye `TickReportQuery`) —
 * Redis ghat' bashe lista khali-e va monitor khodesh az khali budan
 * ghat'i ro mifahme. `summarize`/`topErrors` mantegh e MAHZ ru array-and.
 */
final readonly class UpstreamCallQuery
{
    public function __construct(private UpstreamLogPort $log) {}

    /** @return array<int, array<string, mixed>> jadid tarin aval */
    public function recent(int $limit = 100): array
    {
        try {
            $calls = $this->log->recent($limit);
        } catch (Throwable) {
            return [];
        }

        return array_map(static fn ($call): array => $call->toArray(), $calls);
    }

    /** @return array<string, mixed>|null `null` = az daftarche birun rafte */
    public function find(string $id): ?array
    {
        try {
            return $this->log->find($id)?->toArray();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Kholase ye per-provider.
     *
     * @param  array<int, array<string, mixed>>  $calls  jadid tarin aval
     * @param  int  $slowMs  call e kond tar az in «کند» shomorde mishe
     * @return array<string, array<string, mixed>>
     */
    public function summarize(array $calls, int $slowMs): array
    {
        $stats = [];

        foreach ($calls as $call) {
            $key = (string) ($call['provider'] ?? '');
            $s = $stats[$key] ??= [
                'calls' => 0, 'ok' => 0, 'failed' => 0, 'retries' => 0, 'slow' => 0,
                'latency_sum' => 0, 'latency_n' => 0, 'max_latency_ms' => 0,
                'last_at' => null, 'last_status' => null,
                'last_error' => null, 'last_error_at' => null,
            ];

            $s['calls']++;
            $latency = (int) ($call['latency_ms'] ?? 0);

            // Jadid tarin aval — avvalin dide-shode akharin-e.
            if ($s['last_at'] === null) {
                $s['last_at'] = $call['at'] ?? null;
                $s['last_status'] = $call['status'] ?? null;
            }

            if ((bool) ($call['succeeded'] ?? false)) {
                $s['ok']++;
            } else {
                $s['failed']++;

                if ($s['last_error'] === null) {
                    $s['last_error'] = $call['error'] ?? null;
                    $s['last_error_at'] = $call['at'] ?? null;
                }
            }

            // Talash e bedun e pasokh (0) miangin ro dorugh-ki paeen mikeshe.
            if ($latency > 0) {
                $s['latency_sum'] += $latency;
                $s['latency_n']++;
                $s['max_latency_ms'] = max($s['max_latency_ms'], $latency);
            }

            if ($latency > $slowMs) {
                $s['slow']++;
            }

            if ((int) ($call['attempt'] ?? 1) > 1) {
                $s['retries']++;
            }

            $stats[$key] = $s;
        }

        $summary = [];

        foreach ($stats as $key => $s) {
            $summary[$key] = [
                'calls' => $s['calls'],
                'ok' => $s['ok'],
                'failed' => $s['failed'],
                'success_rate' => (int) round($s['ok'] * 100 / max(1, $s['calls'])),
                'avg_latency_ms' => $s['latency_n'] > 0 ? (int) round($s['latency_sum'] / $s['latency_n']) : 0,
                'max_latency_ms' => $s['max_latency_ms'],
                'retries' => $s['retries'],
                'slow' => $s['slow'],
                'last_at' => $s['last_at'],
                'last_status' => $s['last_status'],
                'last_error' => $s['last_error'],
                'last_error_at' => $s['last_error_at'],
            ];
        }

        return $summary;
    }

    /**
     * Kelid haye filter e YEK call — Alpine faghat ba in ha show/hide mikone.
     *
     * @param  array<string, mixed>  $call
     * @return array{p: string, ok: bool, retry: bool, slow: bool, text: string}
     */
    public function filterMeta(array $call, int $slowMs): array
    {
        return [
            'p' => (string) ($call['provider'] ?? ''),
            'ok' => (bool) ($call['succeeded'] ?? false),
            'retry' => (int) ($call['attempt'] ?? 1) > 1,
            'slow' => (int) ($call['latency_ms'] ?? 0) > $slowMs,
            // Matn e search: URL + khata + status («قطع» baraye bedun e pasokh).
            'text' => mb_strtolower(trim(implode(' ', [
                (string) ($call['url'] ?? ''),
                (string) ($call['error'] ?? ''),
                isset($call['status']) ? (string) $call['status'] : 'قطع',
            ]))),
        ];
    }

    /**
     * Khata haye por-tekrar، group-shode.
     *
     * Adad haye 4+ raghami (ms، byte) `N` mishan ta «timed out after 20001
     * ms» va «… 20004 ms» YEK khata shomorde shan. Status e 3-raghami (503)
     * AMDAN dast nemikhore — ma'ni dare.
     *
     * @param  array<int, array<string, mixed>>  $calls
     * @return list<array{provider: string, message: string, count: int, last_at: ?string, last_id: string}>
     */
    public function topErrors(array $calls, int $limit = 5): array
    {
        $groups = [];

        foreach ($calls as $call) {
            $error = $call['error'] ?? null;

            if ((bool) ($call['succeeded'] ?? false) || ! is_string($error) || $error === '') {
                continue;
            }

            $provider = (string) ($call['provider'] ?? '');
            $message = (string) preg_replace('/\d{4,}/', 'N', $error);
            $key = $provider."\0".$message;

            // Jadid tarin aval — avvalin dide-shode = akharin bar.
            $groups[$key] ??= [
                'provider' => $provider,
                'message' => $message,
                'count' => 0,
                'last_at' => $call['at'] ?? null,
                'last_id' => (string) ($call['id'] ?? ''),
            ];
            $groups[$key]['count']++;
        }

        $list = array_values($groups);
        usort($list, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($list, 0, $limit);
    }
}
