<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

use App\Modules\Pricing\Domain\Enums\SpreadMode;
use App\Modules\Pricing\Domain\ValueObjects\Spread;
use App\Modules\Pricing\Domain\ValueObjects\SpreadPair;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpread;

/** Yek radif e form e spread: `(symbol, buy, sell)` + override ha. */
final class SpreadInputDto
{
    public function __construct(
        public string $symbol,
        public SpreadMode $buyMode = SpreadMode::Percent,
        public float $buyValue = 0.0,
        public SpreadMode $sellMode = SpreadMode::Percent,
        public float $sellValue = 0.0,
        public bool $isEnabled = true,
        public ?int $freshnessSeconds = null,
        public ?int $sort = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            symbol: trim((string) ($data['symbol'] ?? '')),
            buyMode: SpreadMode::tryFrom((string) ($data['buy_mode'] ?? $data['buyMode'] ?? 'percent'))
                ?? SpreadMode::Percent,
            buyValue: (float) ($data['buy_value'] ?? $data['buyValue'] ?? 0),
            sellMode: SpreadMode::tryFrom((string) ($data['sell_mode'] ?? $data['sellMode'] ?? 'percent'))
                ?? SpreadMode::Percent,
            sellValue: (float) ($data['sell_value'] ?? $data['sellValue'] ?? 0),
            isEnabled: (bool) ($data['is_enabled'] ?? $data['isEnabled'] ?? true),
            freshnessSeconds: self::nullableInt($data['freshness_seconds'] ?? $data['freshnessSeconds'] ?? null),
            sort: self::nullableInt($data['sort'] ?? null),
        );
    }

    public function isWildcard(): bool
    {
        return $this->symbol === ProviderSpread::WILDCARD;
    }

    public function toPair(): SpreadPair
    {
        return new SpreadPair(
            new Spread($this->buyMode, $this->buyValue),
            new Spread($this->sellMode, $this->sellValue),
        );
    }

    /**
     * Soton haye ghabl e moghayese — asas e tashkhis e «avaz shode».
     *
     * 🔴 `buy_value` ba 4 raghame ashar `round` mishe chun soton e DB
     * `decimal(12,4)` ast: `0.50001` ke zakhire mishe be `0.5000` tabdil
     * mishe، va bedun e round kardan e in taraf، har save ye ba'di un ro
     * «avaz shode» mibine، yek revision e ja'li minevise va `spreadVersion`
     * ro bump mikone — ya'ni kol e cache e chart ro bi-dalil batel mikone.
     *
     * @return array<string, mixed>
     */
    public function comparable(): array
    {
        return [
            'buy_mode' => $this->buyMode->value,
            'buy_value' => round($this->buyValue, 4),
            'sell_mode' => $this->sellMode->value,
            'sell_value' => round($this->sellValue, 4),
            'is_enabled' => $this->isEnabled,
            'freshness_seconds' => $this->freshnessSeconds,
            'sort' => $this->sort,
        ];
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
