<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

use App\Modules\Pricing\Domain\Enums\Instrument;

/**
 * Tanzimat e yek provider baraye yek tick.
 *
 * Az `providers` + `provider_spreads` sakhte mishe (`ProviderQuery` tu
 * `P1-18`). Pipeline hichvaght mostaghim DB nemibine.
 */
final readonly class ProviderConfigDto
{
    /**
     * @param  array<string, int>  $symbolFreshness  symbol => saniye (override)
     */
    public function __construct(
        public string $key,
        public int $freshnessSeconds = 900,
        public array $symbolFreshness = [],
    ) {}

    /**
     * Chand saniye ba'd az `upstreamAt`, gheymat e in instrument bayat ast.
     *
     * Tartib: override e (provider, symbol) -> default e instrument ->
     * default e provider.
     *
     * 🔴 Chera se tabaghe: `ons` e tgju faghat RUZANE be-rooz mishe dar hali
     * ke `Instrument::freshnessSeconds()` baraye jahani 180s mide. Bedun e
     * override e sath e provider, un symbol har tick rad mishe.
     */
    public function freshnessFor(Instrument $instrument): int
    {
        return $this->symbolFreshness[$instrument->value]
            ?? $instrument->freshnessSeconds();
    }
}
