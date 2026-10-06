<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Api\Infrastructure\Monitoring\ApiRequestLog;
use App\Modules\Pricing\Application\Queries\ProviderHealthQuery;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Application\Queries\TickReportQuery;
use Illuminate\View\View;

/** Safhe haye monitoring (P3-08/P3-09/P4-11) — nazok، faghat Query. */
final readonly class MonitorController
{
    public function __construct(
        private ProviderHealthQuery $health,
        private TickReportQuery $ticks,
        private ProviderQuery $providers,
        private ApiRequestLog $apiLog,
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
        // key -> name/driver baraye shafafiat e card ha (P4-12).
        $meta = [];

        foreach ($this->providers->panelRows() as $row) {
            $meta[$row['key']] = ['name' => $row['name'], 'driver' => $row['driver']];
        }

        return view('panel::monitor.ticks', [
            'reports' => $this->ticks->recent(20),
            'providerMeta' => $meta,
        ]);
    }

    /** P4-11 — inspector e call haye API (mini-Telescope). */
    public function api(): View
    {
        return view('panel::monitor.api', [
            'calls' => $this->apiLog->recent(50),
        ]);
    }
}
