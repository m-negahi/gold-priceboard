<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Pricing\Application\Queries\ProviderHealthQuery;
use App\Modules\Pricing\Application\Queries\TickReportQuery;
use Illuminate\View\View;

/** Safhe haye monitoring (P3-08/P3-09) — nazok، faghat Query. */
final readonly class MonitorController
{
    public function __construct(
        private ProviderHealthQuery $health,
        private TickReportQuery $ticks,
    ) {}

    /** P3-08 — salamat e kamel e manba' ha. */
    public function health(): View
    {
        return view('panel::monitor.health', [
            'providers' => $this->health->overview(),
        ]);
    }

    /** P3-09 — «chi rad shod va chera» — safhe ye asli ye debug. */
    public function ticks(): View
    {
        return view('panel::monitor.ticks', [
            'reports' => $this->ticks->recent(20),
        ]);
    }
}
