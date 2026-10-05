<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

use App\Modules\Pricing\Domain\Enums\Direction;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\ValueObjects\Price;

/**
 * Yek khune ye tablo — BA'D az e'mal e spread.
 *
 * In chizi-e ke Livewire/API mibine. `raw` ham hamrahe ta panel betune
 * «gheymat e kham vs ba spread» ro neshun bede.
 */
final readonly class BoardRowDto
{
    public function __construct(
        public string $providerKey,
        public Instrument $instrument,
        public Side $side,
        public Price $raw,
        public Price $buy,
        public Price $sell,
        public ?Price $dayOpen,
        public ?Price $dayHigh,
        public ?Price $dayLow,
        public float $changePercent,
        public Direction $direction,
        public int $ageSeconds,
        public bool $isStale,
        /**
         * 🔴 `true` ya'ni kharid/foroosh ARITHMETIC e ma-st ru yek mid price،
         * na nerkh e vaghei ye un maghaze.
         *
         * Ru site-i ke kol e arzesh esh e'temad ast، neshun dadan e in be
         * onvan e «nerkh e folan maghaze» daghighan chizi-e ke shekayat
         * misaze. UI bayad marker + footnote bezare.
         */
        public bool $isDerived,
        public string $quotedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->providerKey,
            'symbol' => $this->instrument->value,
            'label' => $this->instrument->label(),
            'side' => $this->side->value,
            'unit' => $this->instrument->unit()->value,
            'quote' => $this->instrument->quote()->value,
            // `scale` va `displayDivisor` hamrah miran ta frontend baraye
            // tafsir e adad be hich lookup-i niaz nadashte bashe.
            'scale' => $this->instrument->quote()->scale(),
            'displayDivisor' => $this->instrument->quote()->displayDivisor(),
            'precision' => $this->instrument->quote()->displayPrecision(),
            'raw' => $this->raw->minor,
            'buy' => $this->buy->minor,
            'sell' => $this->sell->minor,
            'dayOpen' => $this->dayOpen?->minor,
            'dayHigh' => $this->dayHigh?->minor,
            'dayLow' => $this->dayLow?->minor,
            'changePercent' => $this->changePercent,
            'direction' => $this->direction->value,
            'ageSeconds' => $this->ageSeconds,
            'isStale' => $this->isStale,
            'isDerived' => $this->isDerived,
            'quotedAt' => $this->quotedAt,
        ];
    }
}
