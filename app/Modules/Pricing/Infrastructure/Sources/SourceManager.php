<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Sources;

use App\Modules\Pricing\Application\Dto\ProviderConfigDto;
use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Domain\Ports\QuoteSourcePort;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ManualPrice;
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
            NerkhQuoteSource::KEY => new NerkhQuoteSource($config->options, $config->timeout, $config->key),
            MilliQuoteSource::KEY => new MilliQuoteSource($config->options, $config->timeout),
            TalaseaQuoteSource::KEY => new TalaseaQuoteSource($config->options, $config->timeout),
            TechnogoldQuoteSource::KEY => new TechnogoldQuoteSource($config->options, $config->timeout),
            TlynQuoteSource::KEY => new TlynQuoteSource($config->options, $config->timeout),
            MockQuoteSource::KEY => new MockQuoteSource($this->clock, $this->seeds($config)),
            // Manual: gheymat ha az DB load mishan va pass dade mishan (source
            // khodesh DB nemibine — pure mimune).
            ManualQuoteSource::KEY => new ManualQuoteSource($this->manualPrices()),
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

    /**
     * Gheymat haye dasti az DB — shekl e `{minor, quote}` be tafkik e symbol.
     *
     * @return array<string, array{minor: int, quote: string}>
     */
    private function manualPrices(): array
    {
        $prices = [];

        foreach (ManualPrice::query()->get(['symbol', 'minor', 'quote_currency']) as $row) {
            $prices[(string) $row->symbol] = [
                'minor' => (int) $row->minor,
                'quote' => (string) $row->quote_currency,
            ];
        }

        return $prices;
    }

    /** @return array<int, string> */
    public static function drivers(): array
    {
        return [
            TgjuQuoteSource::KEY, EstjtQuoteSource::KEY, NerkhQuoteSource::KEY,
            MilliQuoteSource::KEY, TalaseaQuoteSource::KEY, TechnogoldQuoteSource::KEY, TlynQuoteSource::KEY,
            ManualQuoteSource::KEY, MockQuoteSource::KEY,
        ];
    }
}
