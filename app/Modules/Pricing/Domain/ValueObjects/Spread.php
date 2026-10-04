<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\ValueObjects;

use App\Modules\Pricing\Domain\Enums\SpreadMode;

/**
 * Tanzim e spread e yek samt (kharid ya foroosh).
 *
 * `value` ELAMATDAR ast: manfi kam mikone. Soton e jodagane baraye jahat
 * nadarim chun `+`/`-` tu form khanatar az yek select ast.
 *
 * `amount` tu vahed e ASLI ye arz ast (rial baraye IRR, dollar baraye USD) —
 * na minor. Admin `50000` type mikone, na `50000` e minor.
 */
final readonly class Spread
{
    public function __construct(
        public SpreadMode $mode,
        public float $value,
    ) {}

    public static function none(): self
    {
        return new self(SpreadMode::Percent, 0.0);
    }

    public function isNeutral(): bool
    {
        return $this->value === 0.0;
    }

    /** @return array{mode: string, value: float} */
    public function toArray(): array
    {
        return ['mode' => $this->mode->value, 'value' => $this->value];
    }

    /** @param array{mode?: string, value?: float|string} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            SpreadMode::tryFrom((string) ($data['mode'] ?? 'percent')) ?? SpreadMode::Percent,
            (float) ($data['value'] ?? 0),
        );
    }
}
