<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use Carbon\CarbonImmutable;

/**
 * Khoruji e yek `QuoteSource` — gheymat e KHAM e yek instrument.
 *
 * «Kham» ya'ni: ba arz e marja' yeksan shode (Toman -> Rial) va be minor
 * tabdil shode, vali **hich spread e'mal nashode** va hich enrich (dayOpen,
 * direction, changePercent) anjam nashode.
 *
 * Source hich chiz e digar-i nemidune: na provider id, na snapshot e ghabli,
 * na tick time. Unha ro pipeline mizare.
 */
final readonly class RawQuoteDto
{
    public function __construct(
        public string $providerKey,
        public Instrument $instrument,
        public Side $side,
        public Price $price,
        /** Timestamp e khod e upstream. `null` = provider timestamp nemide (estjt). */
        public ?CarbonImmutable $upstreamAt = null,
        /** Band e rooz agar upstream bede (tgju `h`/`l`). */
        public ?Price $dayHigh = null,
        public ?Price $dayLow = null,
        /** Darsad e taghir agar upstream bede (tgju `dp`). */
        public ?float $upstreamChangePercent = null,
    ) {}

    /** Kelid e yekta ye in gheymat tu kol e system. */
    public function key(): string
    {
        return "{$this->providerKey}:{$this->instrument->value}:{$this->side->value}";
    }
}
