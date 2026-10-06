<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\ValueObjects\Price;

/**
 * Kamtarin/bishtarin gheymat e yek symbol beyn e manba' haye tablo — paye
 * ye `AggregateOffer` e JSON-LD (P5-05). `Price`، na int e kham.
 */
final readonly class SymbolOfferDto
{
    public function __construct(
        public Instrument $instrument,
        public Price $low,
        public Price $high,
        public int $count,
    ) {}
}
