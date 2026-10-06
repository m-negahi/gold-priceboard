<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Pricing\Application\Queries\InstrumentMatrixQuery;
use Illuminate\View\View;

/** Matrix e provider × symbol (P3-10) — nazok. */
final readonly class MatrixController
{
    public function __construct(private InstrumentMatrixQuery $matrix) {}

    public function show(): View
    {
        return view('panel::monitor.matrix', $this->matrix->matrix());
    }
}
