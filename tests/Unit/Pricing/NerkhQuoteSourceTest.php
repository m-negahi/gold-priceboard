<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Infrastructure\Sources\NerkhQuoteSource;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Ru payload e VAGHEI ye `tests/Fixtures/nerkh-prices.json` (2026-10-06).
 *
 * `parse()` hich facade seda nemizane، pas in yek unit test e kamel ast —
 * bedun e Laravel، bedun e shabake.
 */
final class NerkhQuoteSourceTest extends TestCase
{
    /** @return array<string, mixed> */
    private function payload(): array
    {
        $json = (string) file_get_contents(__DIR__.'/../../Fixtures/nerkh-prices.json');

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, RawQuoteDto> */
    private function quotes(?array $payload = null): array
    {
        $result = (new NerkhQuoteSource)->parse($payload ?? $this->payload());

        $byKey = [];
        foreach ($result->quotes as $quote) {
            $byKey[$quote->instrument->value] = $quote;
        }

        return $byKey;
    }

    public function test_parses_every_mapped_instrument(): void
    {
        $quotes = $this->quotes();

        $expected = [
            'xau-18k-g-irr', 'xau-24k-g-irr', 'xau-mesghal-irr',
            'xau-coin-emami-irr', 'xau-coin-bahar-irr', 'xau-coin-half-irr',
            'xau-coin-quarter-irr', 'xau-coin-gerami-irr',
            'xau-oz-usd', 'xag-oz-usd', 'usd-irr',
        ];

        foreach ($expected as $symbol) {
            self::assertArrayHasKey($symbol, $quotes, "«{$symbol}» parse nashod.");
        }

        self::assertCount(11, $quotes);
    }

    /**
     * 🔴 Mohem tarin test e in file.
     *
     * nerkh TOMAN mide: `"26704500"` bayad beshe 267,045,000 RIAL. Agar kasi
     * `×10` ro bardare، in test fail mikone — na production.
     *
     * ⚠️ Jadval e KAMEL (har 11 instrument) tu `SourceFixtureMatrixTest` ast.
     */
    public function test_converts_toman_to_rial(): void
    {
        $quotes = $this->quotes();

        self::assertSame(267_045_000, $quotes['xau-18k-g-irr']->price->minor);
        self::assertSame(26_704_500 * 10, $quotes['xau-18k-g-irr']->price->minor);
        self::assertSame(QuoteCurrency::IRR, $quotes['xau-18k-g-irr']->price->quote);
    }

    /** `OUNCE`/`OUNCE_SILVER` DOLLAR-and va NABAYAD ×10 beshan. */
    public function test_ounces_are_dollars_without_the_toman_multiplier(): void
    {
        $quotes = $this->quotes();

        self::assertSame(QuoteCurrency::USD, $quotes['xau-oz-usd']->price->quote);
        // "4164" -> scale 4 -> 41,640,000
        self::assertSame(41_640_000, $quotes['xau-oz-usd']->price->minor);
        self::assertSame(4164.0, $quotes['xau-oz-usd']->price->toFloat());
        self::assertNotSame(416_400_000, $quotes['xau-oz-usd']->price->minor);

        self::assertSame(QuoteCurrency::USD, $quotes['xag-oz-usd']->price->quote);
        self::assertSame(610_000, $quotes['xag-oz-usd']->price->minor);
    }

    /** Dollar e bazaar: `"269100"` toman -> 2,691,000 rial. */
    public function test_usd_rate_is_toman_converted_to_rial(): void
    {
        self::assertSame(2_691_000, $this->quotes()['usd-irr']->price->minor);
    }

    // ── timestamp ────────────────────────────────────────────────

    /**
     * 🔴 `update` JALALI ast va vaght e Tehran: `"1405-07-14 20:23:11"`
     * = 2026-10-06 16:53:11 UTC. Agar be onvan e UTC ya miladi khunde she،
     * ya parse nemishe ya 3.5 saat «az ayande» mishe.
     */
    public function test_converts_the_jalali_update_time_to_utc(): void
    {
        $at = $this->quotes()['xau-18k-g-irr']->upstreamAt;

        self::assertNotNull($at);
        self::assertSame('2026-10-06T16:53:11+00:00', $at->toIso8601String());
        self::assertSame('UTC', $at->timezoneName);
    }

    /** `update` e kharab → fallback be `timestamp` e sath e bala (epoch ms). */
    public function test_falls_back_to_the_top_level_timestamp_when_update_is_unparsable(): void
    {
        $payload = $this->payload();
        $payload['gold']['GOLD18K']['update'] = '1405-13-45 99:99:99';

        $at = $this->quotes($payload)['xau-18k-g-irr']->upstreamAt;

        self::assertNotNull($at);
        // 1791305682000 ms = 2026-10-06 16:54:42 UTC
        self::assertSame('2026-10-06T16:54:42+00:00', $at->toIso8601String());
    }

    public function test_has_no_timestamp_when_neither_field_is_usable(): void
    {
        $payload = $this->payload();
        unset($payload['gold']['GOLD18K']['update'], $payload['timestamp']);

        self::assertNull($this->quotes($payload)['xau-18k-g-irr']->upstreamAt);
    }

    // ── meta ─────────────────────────────────────────────────────

    public function test_maps_the_24h_band_and_change_percent(): void
    {
        $gold = $this->quotes()['xau-18k-g-irr'];

        self::assertSame(269_075_000, $gold->dayHigh?->minor);
        self::assertSame(265_845_000, $gold->dayLow?->minor);
        self::assertSame(0.4, $gold->upstreamChangePercent);
    }

    public function test_all_quotes_use_the_last_side_and_the_provider_key(): void
    {
        foreach ($this->quotes() as $quote) {
            self::assertSame(Side::Last, $quote->side);
            self::assertSame('nerkh', $quote->providerKey);
        }
    }

    // ── taab-avari ───────────────────────────────────────────────

    /** Kelid e map-shode ke dige nist bayad gozaresh beshe، na saket bofte. */
    public function test_reports_missing_mapped_keys(): void
    {
        $payload = $this->payload();
        unset($payload['coin']['SEKE_EMAMI']);

        $result = (new NerkhQuoteSource)->parse($payload);

        self::assertSame(['coin.SEKE_EMAMI'], $result->unmappedLabels);
        self::assertSame(10, $result->count());
    }

    /** Kelid haye map-NASHODE (crypto، platform ha) hich chizi gozaresh nemikonan. */
    public function test_ignores_unmapped_upstream_keys_silently(): void
    {
        $result = (new NerkhQuoteSource)->parse($this->payload());

        self::assertSame([], $result->unmappedLabels);
    }

    public function test_tolerates_a_missing_section(): void
    {
        $payload = $this->payload();
        unset($payload['coin'], $payload['currency']);

        $result = (new NerkhQuoteSource)->parse($payload);

        self::assertSame(5, $result->count(), 'faghat radif haye bakhsh e tala.');
        self::assertCount(6, $result->unmappedLabels);
    }

    public function test_skips_rows_with_unparsable_or_zero_prices(): void
    {
        $payload = $this->payload();
        $payload['gold']['GOLD18K']['current'] = '—';
        $payload['gold']['GOLD24K']['current'] = '0';

        $quotes = $this->quotes($payload);

        self::assertArrayNotHasKey('xau-18k-g-irr', $quotes);
        self::assertArrayNotHasKey('xau-24k-g-irr', $quotes);
        self::assertCount(9, $quotes);
    }

    /** Payload e bi-shekl shekast ast، na «hich gheymati nabud». */
    public function test_rejects_a_payload_without_any_known_section(): void
    {
        $this->expectException(RuntimeException::class);

        (new NerkhQuoteSource)->parse(['crypto' => [], 'timestamp' => 1]);
    }

    public function test_describes_a_get_request_to_the_live_endpoint(): void
    {
        $request = (new NerkhQuoteSource(timeout: 7))->request();

        self::assertSame('GET', $request->method);
        self::assertSame('https://live.nerkh.io/api/prices', $request->url);
        self::assertSame(7, $request->timeout);
        self::assertFalse($request->isPost());
    }

    public function test_exposes_its_key(): void
    {
        self::assertSame('nerkh', (new NerkhQuoteSource)->key());
    }

    /**
     * `item` faghat kelid haye MAP ro ghabul mikone (item haye platform —
     * `GOLD18K_STOCK`، … — 2026-10-07 hazf shodan).
     */
    public function test_an_item_option_reads_only_its_quote_with_a_distinct_provider_key(): void
    {
        $source = new NerkhQuoteSource(options: ['item' => 'GOLD18K'], providerKey: 'nerkh-gold18');
        $result = $source->parse($this->payload());

        self::assertSame('nerkh-gold18', $source->key());
        self::assertCount(1, $result->quotes);
        self::assertSame([], $result->unmappedLabels);
        self::assertSame('nerkh-gold18', $result->quotes[0]->providerKey);
        self::assertSame('xau-18k-g-irr', $result->quotes[0]->instrument->value);
        self::assertSame(QuoteCurrency::IRR, $result->quotes[0]->price->quote);
        self::assertSame(Side::Last, $result->quotes[0]->side);
    }

    public function test_unknown_item_fallback_does_not_silently_return_the_general_feed(): void
    {
        $this->expectException(RuntimeException::class);

        (new NerkhQuoteSource(options: ['item' => 'UNKNOWN']))->parse($this->payload());
    }

    /** Item e platform e hazf-shode dige ghabul nemishe. */
    public function test_a_removed_platform_item_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        (new NerkhQuoteSource(options: ['item' => 'GOLD18K_STOCK']))->parse($this->payload());
    }
}
