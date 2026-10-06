<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Actions;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Infrastructure\Support\ManualPriceWorkbook;

final readonly class ExportManualPricesAction
{
    public function __construct(private ManualPriceWorkbook $workbook) {}

    public function execute(string $path): void
    {
        $rows = [];

        foreach (Instrument::cases() as $instrument) {
            $rows[] = ['symbol' => $instrument->value, 'label' => $instrument->label(), 'price' => '', 'unit' => $instrument->quote()->value];
        }

        $this->workbook->write($path, $rows);
    }
}
