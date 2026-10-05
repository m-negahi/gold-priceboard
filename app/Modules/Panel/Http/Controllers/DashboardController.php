<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Panel\Queries\DashboardQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Mizkar-e admin. Alpine safhe ro dast dare (ADR-0020): `show` hamun snapshot ro inline mikone ke
 * avalin paint be fetch niaz nadashte bashe va `data` hamun ro har 5 sanie be shekl-e JSON mide.
 * Dokme-ye "yek door daryaft" endpoint-e payesh (`panel.api.monitor.tick`) ro mizane — yek masir-e
 * tick baraye kol-e panel. Safhe posht-e auth+admin-e, pas JSON-e sade mojaz-e — kanal-e ramz-shode
 * male tablo-ye omumi-e (ADR-0009).
 */
final readonly class DashboardController
{
    public function __construct(private DashboardQuery $dashboard) {}

    public function show(): View
    {
        return view('panel::dashboard', ['snapshot' => $this->dashboard->snapshot()]);
    }

    public function data(): JsonResponse
    {
        return response()->json($this->dashboard->snapshot());
    }
}
