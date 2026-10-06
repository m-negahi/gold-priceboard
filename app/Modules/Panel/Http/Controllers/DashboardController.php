<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Panel\Queries\DashboardQuery;
use Illuminate\View\View;

/** Nazok — faghat yek Query (ghanun e laye ha). */
final readonly class DashboardController
{
    public function __construct(private DashboardQuery $dashboard) {}

    public function show(): View
    {
        return view('panel::dashboard', $this->dashboard->overview());
    }
}
