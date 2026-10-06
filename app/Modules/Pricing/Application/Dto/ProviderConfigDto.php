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
        /**
         * Chand saniye BA'D az bayat shodan، radif az tablo hazf mishe.
         *
         * 🔴 NESBI ast، na motlagh — va in tasmim ejbari bud:
         *
         * 1. Default haye jadval MA'KUS-and (`freshness_seconds = 900` va
         *    `hide_after_seconds = 600`). Ba ma'ni ye MOTLAGH، radif tu 600s
         *    makhfi mishod GHABL az inke tu 900s «bayat» e'lam she — ya'ni
         *    badge e bayat e'lam kardan HICHVAGHT dide nemishod.
         * 2. Tazegi PER-INSTRUMENT ast: `xau-oz-usd` e tgju 90,000 saniye
         *    dare (un feed faghat RUZANE be-rooz mishe). Yek saghf e motlagh
         *    e 600 saniye ye sath e provider، un symbol ro BARAYE HAMISHE
         *    makhfi mikard.
         *
         * Pas: `hide = effectiveFreshness(instrument) + hideAfterSeconds`.
         * Do adad mostaghel mishan va khunda mishe «ba'd az 15 daghighe
         * bayat e'lam kon، 10 daghighe ba'd az un makhfi kon».
         */
        public int $hideAfterSeconds = 600,
        public bool $isTrustAnchor = false,
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
            hideAfterSeconds: (int) ($row['hide_after_seconds'] ?? 600),
            isTrustAnchor: (bool) ($row['is_trust_anchor'] ?? false),
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
            'hide_after_seconds' => $this->hideAfterSeconds,
            'is_trust_anchor' => $this->isTrustAnchor,
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

    /**
     * Sen-i ke ba'd az un، radif aslan ru tablo neshun dade NEMISHE.
     *
     * Nesbat be `freshnessFor()` hesab mishe — dalil e kamel tu docblock e
     * `$hideAfterSeconds`. `0` ya'ni hargez makhfi nakon.
     */
    public function hideAfterFor(Instrument $instrument): int
    {
        if ($this->hideAfterSeconds <= 0) {
            return PHP_INT_MAX;
        }

        return $this->freshnessFor($instrument) + $this->hideAfterSeconds;
    }
}
