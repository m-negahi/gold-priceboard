<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Common\Helpers\StringHelper;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Services\DisplayPriceParser;
use App\Modules\Pricing\Infrastructure\Support\ManualPriceWorkbook;
use InvalidArgumentException;

final readonly class ManualPriceImportQuery
{
    public function __construct(private ManualPriceWorkbook $workbook) {}

    /** @return array{rows: list<array<string, mixed>>, hasErrors: bool, count: int} */
    public function preview(string $path): array
    {
        $rows = $this->workbook->read($path);
        $seen = [];
        $hasErrors = false;

        foreach ($rows as &$row) {
            $instrument = Instrument::tryFrom($row['symbol']);
            $unit = strtoupper($row['unit']);
            $row['unit'] = $unit;

            if ($instrument === null) {
                $row['error'] ??= 'نماد ناشناخته است.';
            } elseif (isset($seen[$row['symbol']])) {
                $row['error'] ??= 'نماد تکراری است.';
            } elseif ($unit !== $instrument->quote()->value) {
                $row['error'] ??= $instrument->quote()->value === 'IRR' ? 'واحد این نماد باید IRR (ریال) باشد.' : 'واحد این نماد باید USD (دلار) باشد.';
            } else {
                try {
                    $row['minor'] = DisplayPriceParser::parse($instrument, $row['price'])->minor;
                } catch (InvalidArgumentException) {
                    $row['error'] ??= 'قیمت باید عدد مثبت، بدون جداکننده و در محدوده مجاز باشد.';
                }
            }

            $seen[$row['symbol']] = true;
            $row['label'] = StringHelper::numbersToEnglish($instrument?->label() ?? $row['label']);
            $row['unitLabel'] = $instrument?->quote()->displayLabel() ?? $row['unit'];
            $hasErrors = $hasErrors || $row['error'] !== null;
        }
        unset($row);

        return ['rows' => $rows, 'hasErrors' => $hasErrors, 'count' => count($rows)];
    }
}
