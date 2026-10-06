<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Api\Infrastructure\Monitoring\ApiRequestLog;
use App\Modules\Pricing\Application\Queries\ProviderHealthQuery;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Application\Queries\TickReportQuery;
use App\Modules\Pricing\Application\Queries\UpstreamCallQuery;
use Illuminate\View\View;

/** Safhe haye monitoring (P3-08/P3-09/P4-11/P4-14) — nazok، faghat Query. */
final readonly class MonitorController
{
    public function __construct(
        private ProviderHealthQuery $health,
        private TickReportQuery $ticks,
        private ProviderQuery $providers,
        private ApiRequestLog $apiLog,
        private UpstreamCallQuery $upstreamCalls,
    ) {}

    /** P3-08 — salamat e kamel e manba' ha. */
    public function health(): View
    {
        return view('panel::monitor.health', [
            'providers' => $this->health->overview(),
        ]);
    }

    /** P3-09/P4-12 — «chi rad shod va chera» — safhe ye asli ye debug. */
    public function ticks(): View
    {
        return view('panel::monitor.ticks', [
            'reports' => $this->ticks->recent(20),
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

    /** P4-14 — call haye MA be provider ha (upstream inspector). */
    public function upstream(): View
    {
        return view('panel::monitor.upstream', [
            'calls' => $this->upstreamCalls->recent(100),
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
