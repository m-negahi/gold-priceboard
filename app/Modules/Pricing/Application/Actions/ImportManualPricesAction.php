<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Actions;

use App\Modules\Pricing\Domain\Enums\Instrument;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ImportManualPricesAction
{
    public function __construct(private ManualPriceAction $prices) {}

    /** @param list<array<string, mixed>> $rows */
    public function confirm(array $rows, ?string $updatedBy): int
    {
        if ($rows === []) {
            throw new InvalidArgumentException('هیچ ردیفی برای ثبت وجود ندارد.');
        }

        return DB::transaction(function () use ($rows, $updatedBy): int {
            foreach ($rows as $row) {
                $instrument = Instrument::tryFrom((string) ($row['symbol'] ?? ''));

                if ($instrument === null || ($row['error'] ?? null) !== null || ($row['unit'] ?? '') !== $instrument->quote()->value) {
                    throw new InvalidArgumentException('پیش‌نمایش دارای خطاست؛ فایل را اصلاح کنید.');
                }

                $this->prices->setFromDisplay($instrument, (string) $row['price'], $updatedBy);
            }

            return count($rows);
        });
    }
}
