<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

use App\Modules\Pricing\Domain\Enums\Direction;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use Carbon\CarbonImmutable;

/**
 * Akharin gheymat e yek (provider, instrument, side) — ba'd az enrich.
 *
 * In daghighan hamun chizi-e ke tu `price_snapshots` mishine va tu Redis
 * cache mishe. Hanuz **KHAM** ast: spread ru read e'mal mishe, na inja.
 */
final readonly class SnapshotDto
{
    public function __construct(
        public string $providerKey,
        public Instrument $instrument,
        public Side $side,
        public Price $price,
        public CarbonImmutable $quotedAt,
        /** Akharin meghdar e MOTEFAVET — paye ye `direction`. */
        public ?Price $previous = null,
        public ?Price $dayOpen = null,
        public ?Price $dayHigh = null,
        public ?Price $dayLow = null,
        public float $changePercent = 0.0,
        public Direction $direction = Direction::Flat,
        public ?CarbonImmutable $upstreamAt = null,
        public int $upstreamLagSeconds = 0,
        /**
         * Outlier e moallagh: meghdar-i ke `PriceSanity` rad karde vali
         * negah dashte, ta agar tick e ba'di ta'eed esh kone paziresh she.
         * Tu DB mimune (na Redis) ta ba restart e Redis gom nashe.
         */
        public ?Price $pendingOutlier = null,
    ) {}

    public function key(): string
    {
        return self::makeKey($this->providerKey, $this->instrument, $this->side);
    }

    public static function makeKey(string $providerKey, Instrument $instrument, Side $side): string
    {
        return "{$providerKey}:{$instrument->value}:{$side->value}";
    }

    /** Aya in gheymat nesbat be snapshot e dade-shode taghir karde? */
    public function differsFrom(?self $other): bool
    {
        return $other === null || ! $this->price->equals($other->price);
    }

    /**
     * Sen e gheymat be saniye nesbat be yek lahze.
     * Tu UI baraye badge e «آخرین به‌روزرسانی» estefade mishe.
     */
    public function ageSeconds(CarbonImmutable $now): int
    {
        return max(0, $now->getTimestamp() - $this->quotedAt->getTimestamp());
    }

    public function withPrice(Price $price): self
    {
        return new self(
            $this->providerKey, $this->instrument, $this->side, $price, $this->quotedAt,
            $this->previous, $this->dayOpen, $this->dayHigh, $this->dayLow,
            $this->changePercent, $this->direction, $this->upstreamAt,
            $this->upstreamLagSeconds, $this->pendingOutlier,
        );
    }
}
