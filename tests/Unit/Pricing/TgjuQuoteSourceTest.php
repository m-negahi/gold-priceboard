<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Infrastructure\Sources\TgjuQuoteSource;
use PHPUnit\Framework\TestCase;

/**
 * Ru payload e VAGHEI ye `tests/Fixtures/tgju-ajax.json`.
 *
 * `parse()` hich facade seda nemizane, pas in yek unit test e kamel ast —
 * bedun e Laravel, bedun e shabake.
 */
final class TgjuQuoteSourceTest extends TestCase
{
    /** @return array<string, mixed> */
    private function payload(): array
    {
        $json = (string) file_get_contents(__DIR__.'/../../Fixtures/tgju-ajax.json');

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, RawQuoteDto> */
    private function quotes(): array
    {
        $result = (new TgjuQuoteSource)->parse($this->payload());

        $byKey = [];
        foreach ($result->quotes as $quote) {
            $byKey[$quote->instrument->value.':'.$quote->side->value] = $quote;
        }

        return $byKey;
    }

    public function test_parses_every_mapped_instrument(): void
    {
        $quotes = $this->quotes();

        $expected = [
            'xau-18k-g-irr:last', 'xau-24k-g-irr:last',
            'xau-melted-g-irr:ask', 'xau-melted-g-irr:bid',
            'xau-mesghal-irr:last', 'xau-coin-emami-irr:last', 'xau-coin-bahar-irr:last',
            'xau-coin-half-irr:last', 'xau-coin-quarter-irr:last', 'xau-coin-gerami-irr:last',
            'xag-999-g-irr:last', 'xag-925-g-irr:last',
            'xau-oz-usd:last', 'xag-oz-usd:last', 'xcu-lb-usd:last',
            'usd-irr:last',
        ];

        foreach ($expected as $key) {
            self::assertArrayHasKey($key, $quotes, "«{$key}» parse nashod.");
        }

        self::assertCount(count($expected), $quotes);
    }

    /**
     * 🔴 Meghdar e DAGHIGH e minor baraye har instrument.
     * Inja daghighan jayi-e ke yek khata ye 10x khodesh ro neshun mide.
     */
    public function test_parses_exact_minor_values(): void
    {
        $quotes = $this->quotes();

        // IRR — scale 0, pas minor hamun rial ast.
        self::assertSame(266_400_000, $quotes['xau-18k-g-irr:last']->price->minor);
        self::assertSame(355_196_000, $quotes['xau-24k-g-irr:last']->price->minor);
        self::assertSame(1_154_030_000, $quotes['xau-mesghal-irr:last']->price->minor);
        self::assertSame(2_753_400_000, $quotes['xau-coin-emami-irr:last']->price->minor);
        self::assertSame(2_619_450_000, $quotes['xau-coin-bahar-irr:last']->price->minor);
        self::assertSame(1_429_600_000, $quotes['xau-coin-half-irr:last']->price->minor);
        self::assertSame(789_900_000, $quotes['xau-coin-quarter-irr:last']->price->minor);
        self::assertSame(5_534_900, $quotes['xag-999-g-irr:last']->price->minor);
        self::assertSame(2_711_600, $quotes['usd-irr:last']->price->minor);

        // USD — scale 4, pas minor = dollar x 10,000.
        self::assertSame(41_401_900, $quotes['xau-oz-usd:last']->price->minor);
        self::assertSame(603_700, $quotes['xag-oz-usd:last']->price->minor);
        self::assertSame(64_920, $quotes['xcu-lb-usd:last']->price->minor);
    }

    public function test_assigns_the_right_quote_currency(): void
    {
        $quotes = $this->quotes();

        self::assertSame(QuoteCurrency::IRR, $quotes['xau-18k-g-irr:last']->price->quote);
        self::assertSame(QuoteCurrency::USD, $quotes['xau-oz-usd:last']->price->quote);
        self::assertSame(QuoteCurrency::USD, $quotes['xcu-lb-usd:last']->price->quote);
    }

    /** `ask` bayad az `bid` balatar bashe — vagarna do kelid ja be ja shodan. */
    public function test_melted_gold_bid_and_ask_are_the_right_way_round(): void
    {
        $quotes = $this->quotes();

        $bid = $quotes['xau-melted-g-irr:bid']->price;
        $ask = $quotes['xau-melted-g-irr:ask']->price;

        self::assertSame(263_175_000, $bid->minor);
        self::assertSame(264_921_000, $ask->minor);
        self::assertTrue($ask->isGreaterThan($bid), 'foroosh bayad az kharid balatar bashe.');
    }

    /** `"\t8520000"` nabayad parse ro beshkane (`mesghal_buy` vaghe'an injuri-e). */
    public function test_tolerates_values_with_leading_whitespace(): void
    {
        $payload = $this->payload();
        $payload['current']['geram18']['p'] = "\t266400000";

        $result = (new TgjuQuoteSource)->parse($payload);
        $gold18 = array_values(array_filter(
            $result->quotes,
            static fn (RawQuoteDto $q): bool => $q->instrument === Instrument::Gold18Gram,
        ))[0];

        self::assertSame(266_400_000, $gold18->price->minor);
    }

    // ── zaman ────────────────────────────────────────────────────

    /**
     * 🔴 `ts` e tgju bedun e offset ast va be vaght e TEHRAN.
     *
     * `"2026-10-04 12:21:30"` e Tehran = `09:51:30Z`. Agar be onvan e UTC
     * khunde beshe, gheymat 3.5 saat «az ayande» be nazar mirese va har
     * check e tazegi gij mishe.
     */
    public function test_parses_upstream_timestamp_as_tehran_time(): void
    {
        $quotes = $this->quotes();

        self::assertSame(
            '2026-10-04T08:51:30+00:00',
            $quotes['xau-18k-g-irr:last']->upstreamAt->toIso8601String(),
        );
    }

    /**
     * 🔴 Sabt e yek vaghe'iat e feed: `ons`, `silver`, `copperp` va `gerami`
     * tu in feed faghat RUZANE be-rooz mishan, na zende.
     *
     * Ya'ni `freshness_seconds` e inha baraye tgju bayad override beshe
     * (~1 rooz), vagarna policy e default (180s baraye jahani) har tick
     * radeshun mikone.
     */
    public function test_global_metals_are_only_updated_daily_in_this_feed(): void
    {
        $quotes = $this->quotes();

        foreach (['xau-oz-usd:last', 'xag-oz-usd:last', 'xcu-lb-usd:last', 'xau-coin-gerami-irr:last'] as $key) {
            self::assertSame(
                '00:00:00',
                $quotes[$key]->upstreamAt->setTimezone('Asia/Tehran')->format('H:i:s'),
                "{$key}: entezar darim yek meghdar e ruzane bashe (nime-shab).",
            );
        }

        // ...dar hali ke tala ye dakheli har daghighe harekat mikone.
        self::assertNotSame(
            '00:00:00',
            $quotes['xau-18k-g-irr:last']->upstreamAt->setTimezone('Asia/Tehran')->format('H:i:s'),
        );
    }

    // ── band e rooz va meta ──────────────────────────────────────

    public function test_attaches_the_upstream_day_band(): void
    {
        $gold18 = $this->quotes()['xau-18k-g-irr:last'];

        self::assertSame(266_760_000, $gold18->dayHigh->minor);
        self::assertSame(262_237_000, $gold18->dayLow->minor);
    }

    public function test_attaches_the_upstream_change_percent(): void
    {
        self::assertSame(1.58, $this->quotes()['xau-18k-g-irr:last']->upstreamChangePercent);
    }

    public function test_every_quote_is_tagged_with_the_provider_key(): void
    {
        foreach ($this->quotes() as $quote) {
            self::assertSame('tgju', $quote->providerKey);
        }
    }

    // ── taab-avari ───────────────────────────────────────────────

    /** Kelid e gom-shode = tgju esm esh ro avaz karde — bayad gozaresh beshe. */
    public function test_reports_mapped_keys_that_vanished_from_the_payload(): void
    {
        $payload = $this->payload();
        unset($payload['current']['geram18'], $payload['current']['sekee']);

        $result = (new TgjuQuoteSource)->parse($payload);

        self::assertContains('geram18', $result->unmappedLabels);
        self::assertContains('sekee', $result->unmappedLabels);
        self::assertSame(14, $result->count(), 'baghi bayad salem parse shan.');
    }

    public function test_skips_unparsable_prices_without_failing(): void
    {
        $payload = $this->payload();
        $payload['current']['geram18']['p'] = '—';

        $result = (new TgjuQuoteSource)->parse($payload);

        $symbols = array_map(static fn (RawQuoteDto $q): string => $q->instrument->value, $result->quotes);

        self::assertNotContains('xau-18k-g-irr', $symbols);
        self::assertSame(15, $result->count());
    }

    /** Gheymat e sefr rad mishe — `0` yek gheymat nist, yek feed e kharab ast. */
    public function test_skips_zero_prices(): void
    {
        $payload = $this->payload();
        $payload['current']['geram18']['p'] = '0';

        $result = (new TgjuQuoteSource)->parse($payload);

        self::assertSame(15, $result->count());
    }

    /**
     * `current` e khali ya'ni hame ye kelid ha gom shodan — na yek shekast.
     * Provider javab dade، faghat chizi ke mikhastim toosh nabud.
     */
    public function test_handles_an_empty_current_block(): void
    {
        $result = (new TgjuQuoteSource)->parse(['current' => []]);

        self::assertTrue($result->isEmpty());
        self::assertCount(16, $result->unmappedLabels);
    }

    /**
     * 🔴 Nabud e khod e `current` fargh dare: ya'ni SHEKL e payload avaz
     * shode. Un yek SHEKAST ast، na «hich gheymati nabud» — vagarna
     * pipeline provider ro salem hesab mikone dar hali ke feed kharab ast.
     */
    public function test_a_payload_without_current_is_a_failure(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('current');

        (new TgjuQuoteSource)->parse([]);
    }

    public function test_exposes_its_key(): void
    {
        self::assertSame('tgju', (new TgjuQuoteSource)->key());
    }

    /** Har kelid e map bayad be yek `Instrument` e motabar eshare kone. */
    public function test_map_only_references_known_instruments(): void
    {
        $quotes = $this->quotes();

        foreach ($quotes as $quote) {
            self::assertInstanceOf(Instrument::class, $quote->instrument);
            self::assertInstanceOf(Side::class, $quote->side);
        }
    }
}
