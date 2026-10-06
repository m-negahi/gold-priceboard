<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Services;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use InvalidArgumentException;

/** Decimal e rial/dollar be minor ba bcmath, bedun e float. */
final readonly class DisplayPriceParser
{
    public static function parse(Instrument $instrument, string|int $displayValue): Price
    {
        $factor = $instrument->quote()->displayDivisor() * (10 ** $instrument->quote()->scale());

        $value = (string) $displayValue;

        if (strlen($value) > 100 || preg_match('/^\d+(?:\.\d+)?$/D', $value) !== 1) {
            throw new InvalidArgumentException('gheymat bayad decimal e mosbat bashe.');
        }

        $scaled = bcmul($value, (string) $factor, 1);
        $rounded = bcadd($scaled, '0.5', 0);

        if (bccomp($rounded, '0', 0) <= 0 || bccomp($rounded, (string) PHP_INT_MAX, 0) > 0) {
            throw new InvalidArgumentException('gheymat az mahdude e mojaz birun ast.');
        }

        return new Price((int) $rounded, $instrument->quote());
    }
}
