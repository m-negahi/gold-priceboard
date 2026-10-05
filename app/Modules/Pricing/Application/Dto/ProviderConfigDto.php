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
     * @param  array<string, mixed>  $options  UA / base url / har chiz e makhsus e Source
     */
    public function __construct(
        public string $key,
        public int $freshnessSeconds = 900,
        public array $symbolFreshness = [],
        /**
         * Kelas e code-i ke in provider azash mikhune.
         *
         * AMDAN az `key` jodast: ba'dan panj maghaze ba hamun shape e
         * `admin-ajax.php` mishan PANJ provider ru YEK driver.
         */
        public string $driver = '',
        public int $timeout = 10,
        public array $options = [],
        public int $pollIntervalSeconds = 15,
    ) {}

    public function driverKey(): string
    {
        return $this->driver !== '' ? $this->driver : $this->key;
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            key: (string) $row['key'],
            freshnessSeconds: (int) ($row['freshness_seconds'] ?? 900),
            symbolFreshness: (array) ($row['symbol_freshness'] ?? []),
            driver: (string) ($row['driver'] ?? ''),
            timeout: (int) ($row['timeout'] ?? 10),
            options: (array) ($row['options'] ?? []),
            pollIntervalSeconds: (int) ($row['poll_interval_seconds'] ?? 15),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'driver' => $this->driver,
            'timeout' => $this->timeout,
            'options' => $this->options,
            'freshness_seconds' => $this->freshnessSeconds,
            'symbol_freshness' => $this->symbolFreshness,
            'poll_interval_seconds' => $this->pollIntervalSeconds,
        ];
    }

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
