<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Services;

use App\Modules\Pricing\Application\Dto\ProviderConfigDto;
use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Domain\Ports\QuoteSourcePort;
use App\Modules\Pricing\Infrastructure\Sources\EstjtQuoteSource;
use App\Modules\Pricing\Infrastructure\Sources\MockQuoteSource;
use App\Modules\Pricing\Infrastructure\Sources\TgjuQuoteSource;
use InvalidArgumentException;

/**
 * Switcher e Source: `driver` -> class.
 *
 * `final readonly` va bi-halat (Octane-safe) — har bar instance e taze
 * misaze، hich chiz cache nemishe.
 *
 * Ezafe kardan e provider e jadid = yek `case` inja + yek radif tu
 * `providers`. (Marahel e kamel tu skill e `add-price-provider`.)
 */
final readonly class SourceManager
{
    public function __construct(private ClockPort $clock) {}

    public function make(ProviderConfigDto $config): QuoteSourcePort
    {
        return match ($config->driverKey()) {
            TgjuQuoteSource::KEY => new TgjuQuoteSource($config->options, $config->timeout),
            EstjtQuoteSource::KEY => new EstjtQuoteSource($config->options, $config->timeout),
            MockQuoteSource::KEY => new MockQuoteSource($this->clock, $this->seeds($config)),
            default => throw new InvalidArgumentException(
                "driver e nashenas: «{$config->driverKey()}» (provider: {$config->key})",
            ),
        };
    }

    /** @return array<string, int> */
    private function seeds(ProviderConfigDto $config): array
    {
        /** @var array<string, int> $seeds */
        $seeds = (array) ($config->options['seeds'] ?? []);

        return $seeds;
    }

    /** @return array<int, string> */
    public static function drivers(): array
    {
        return [TgjuQuoteSource::KEY, EstjtQuoteSource::KEY, MockQuoteSource::KEY];
    }
}
