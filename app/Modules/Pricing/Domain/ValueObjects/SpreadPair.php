<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\ValueObjects;

/** Spread e kharid va foroosh baraye yek (provider, symbol). */
final readonly class SpreadPair
{
    public function __construct(
        public Spread $buy,
        public Spread $sell,
    ) {}

    public static function none(): self
    {
        return new self(Spread::none(), Spread::none());
    }

    public function isNeutral(): bool
    {
        return $this->buy->isNeutral() && $this->sell->isNeutral();
    }

    /** @return array{buy: array{mode: string, value: float}, sell: array{mode: string, value: float}} */
    public function toArray(): array
    {
        return ['buy' => $this->buy->toArray(), 'sell' => $this->sell->toArray()];
    }

    /** @param array{buy?: array<string, mixed>, sell?: array<string, mixed>} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Spread::fromArray((array) ($data['buy'] ?? [])),
            Spread::fromArray((array) ($data['sell'] ?? [])),
        );
    }
}
