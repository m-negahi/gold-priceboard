<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Api\Infrastructure\Monitoring\ApiRequestLog;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Application\Queries\TickReportQuery;
use App\Modules\Pricing\Application\Queries\UpstreamCallQuery;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Safhe haye monitoring (P3-08/P3-09/P4-11/P4-14) — nazok، faghat Query. */
final readonly class MonitorController
{
    public function __construct(
        private TickReportQuery $ticks,
        private ProviderQuery $providers,
        private ApiRequestLog $apiLog,
        private UpstreamCallQuery $upstreamCalls,
    ) {}

    /** P3-09/P4-12/P4-18 — «chi rad shod va chera» — safhe ye asli ye debug. */
    public function ticks(): View
    {
        // Hame ye daftarche (50) — kholase ba 20 tick nemayande nabud.
        $reports = $this->ticks->recent(50);
        $rejections = $this->ticks->rejectionRows($reports);

        return view('panel::monitor.ticks', [
            'reports' => $reports,
            'metas' => array_map(fn (array $r): array => $this->ticks->filterMeta($r), $reports),
            'summary' => $this->ticks->summarize($reports),
            'rejections' => $rejections,
            'providerMeta' => $this->providerMeta(),
        ]);
    }

    /** P4-11 — inspector e call haye API (mini-Telescope). */
    public function api(): View
    {
        return view('panel::monitor.api', [
            'calls' => $this->apiLog->recent(50),
        ]);
    }

    /** P4-14/P4-18 — call haye MA be provider ha (upstream inspector). */
    public function upstream(): View
    {
        $calls = $this->upstreamCalls->recent(300);
        // «Kond» = bishtar az 80% e budget e tick — yek gam ta az grid rad kardan.
        $slowMs = (int) ((int) config('pricing.tick_seconds', 15) * 1_000 * 0.8);

        return view('panel::monitor.upstream', [
            'calls' => $calls,
            'summary' => $this->upstreamCalls->summarize($calls, $slowMs),
            'topErrors' => $this->upstreamCalls->topErrors($calls),
            'metas' => array_map(fn (array $c): array => $this->upstreamCalls->filterMeta($c, $slowMs), $calls),
            'slowMs' => $slowMs,
            'providerMeta' => $this->providerMeta(),
        ]);
    }

    /** P4-20 — jozyat e yek call (Telescope-vaar). */
    public function upstreamShow(string $id): View|Response
    {
        $call = $this->upstreamCalls->find($id);

        if ($call === null) {
            // 404 e SADEGH: daftarche halghe-ist va call e ghadimi trim mishe.
            return response()->view('panel::monitor.upstream-missing', ['id' => $id], 404);
        }

        return view('panel::monitor.upstream-show', [
            'call' => $call,
            'providerMeta' => $this->providerMeta(),
        ]);
    }

    /**
     * key -> name/driver baraye shafafiat e card/jadval ha (P4-12).
     *
     * @return array<string, array{name: string, driver: string}>
     */
    private function providerMeta(): array
    {
        $meta = [];

        foreach ($this->providers->panelRows() as $row) {
            $meta[$row['key']] = ['name' => $row['name'], 'driver' => $row['driver']];
        }

        return $meta;
    }
}
