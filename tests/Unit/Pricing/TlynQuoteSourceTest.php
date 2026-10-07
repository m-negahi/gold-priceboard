<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Infrastructure\Sources\TlynQuoteSource;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Ru payload e VAGHEI ye `tests/Fixtures/tlyn-price.json` (2026-10-06).
 * Meghdar haye daghigh e har side tu `SourceFixtureMatrixTest`؛ inja raftar.
 */
final class TlynQuoteSourceTest extends TestCase
{
    /** @return array<string, mixed> */
    private function payload(): array
    {
        $json = (string) file_get_contents(__DIR__.'/../../Fixtures/tlyn-price.json');

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, RawQuoteDto> */
    private function quotes(?array $payload = null): array
    {
        $result = (new TlynQuoteSource)->parse($payload ?? $this->payload());

        $byKey = [];
        foreach ($result->quotes as $quote) {
            $byKey[$quote->instrument->value.':'.$quote->side->value] = $quote;
        }

        return $byKey;
    }

    public function test_parses_six_instruments_with_both_sides(): void
    {
        $quotes = $this->quotes();

        foreach (['xau-melted-g-irr', 'xau-mesghal-irr', 'xau-coin-emami-irr', 'xau-coin-half-irr', 'xau-coin-quarter-irr', 'xau-oz-usd'] as $symbol) {
            self::assertArrayHasKey("{$symbol}:ask", $quotes, "«{$symbol}» ask nadare.");
            self::assertArrayHasKey("{$symbol}:bid", $quotes, "«{$symbol}» bid nadare.");
        }

        self::assertCount(12, $quotes);
    }

    /**
     * 🔴 HEZAR toman: `26891` → 26,891,000 toman → 268,910,000 rial. Agar
     * yeki az do zarib (×1000، ×10) bioftad، in test ghermez mishe.
     */
    public function test_scales_thousand_toman_to_rial(): void
    {
        $gold = $this->quotes()['xau-melted-g-irr:ask'];

        self::assertSame(268_910_000, $gold->price->minor);
        self::assertSame(26_891 * 1000 * 10, $gold->price->minor);
        self::assertSame(QuoteCurrency::IRR, $gold->price->quote);
    }

    /** `sell` e platform = karbar mikhare = ASK؛ `buy` = BID. Ask > bid. */
    public function test_sell_is_ask_and_buy_is_bid(): void
    {
        $quotes = $this->quotes();

        self::assertSame(Side::Ask, $quotes['xau-melted-g-irr:ask']->side);
        self::assertSame(268_910_000, $quotes['xau-melted-g-irr:ask']->price->minor);
        self::assertSame(266_770_000, $quotes['xau-melted-g-irr:bid']->price->minor);
        self::assertGreaterThan($quotes['xau-melted-g-irr:bid']->price->minor, $quotes['xau-melted-g-irr:ask']->price->minor);
    }

    /** `XAU` DOLLAR ast va NABAYAD ×1000 ya ×10 beshe. */
    public function test_ounce_is_dollars_without_any_multiplier(): void
    {
        $ounce = $this->quotes()['xau-oz-usd:ask'];

        self::assertSame(QuoteCurrency::USD, $ounce->price->quote);
        self::assertSame(41_740_000, $ounce->price->minor);
        self::assertSame(4174.0, $ounce->price->toFloat());
    }

    /** `date.utc_timestamp` ISO ba Z — `jdatetime` e sekke ha kharab bud (2647). */
    public function test_uses_the_utc_timestamp(): void
    {
        $quotes = $this->quotes();

        self::assertSame('2026-10-06T18:48:33+00:00', $quotes['xau-melted-g-irr:ask']->upstreamAt?->toIso8601String());
        self::assertSame('2026-10-06T18:52:30+00:00', $quotes['xau-coin-emami-irr:ask']->upstreamAt?->toIso8601String());
    }

    public function test_maps_the_day_band(): void
    {
        $gold = $this->quotes()['xau-melted-g-irr:ask'];

        self::assertSame(269_170_000, $gold->dayHigh?->minor);
        self::assertSame(265_820_000, $gold->dayLow?->minor);
    }

    public function test_ignores_computed_rows_like_bubble_and_intl_gold(): void
    {
        $symbols = array_unique(array_map(static fn (RawQuoteDto $q): string => $q->instrument->value, array_values($this->quotes())));

        self::assertCount(6, $symbols);
        self::assertSame([], (new TlynQuoteSource)->parse($this->payload())->unmappedLabels);
    }

    public function test_reports_a_missing_or_disabled_mapped_symbol(): void
    {
        $payload = $this->payload();
        $payload['prices'] = array_values(array_filter($payload['prices'], static fn (array $r): bool => $r['symbol'] !== 'SEKEH_NIM'));
        $payload['prices'][0]['status'] = 'disabled'; // GOLD18

        $result = (new TlynQuoteSource)->parse($payload);

        self::assertSame(['GOLD18', 'SEKEH_NIM'], $result->unmappedLabels);
        self::assertSame(8, $result->count());
    }

    public function test_rejects_a_payload_without_prices(): void
    {
        $this->expectException(RuntimeException::class);

        (new TlynQuoteSource)->parse(['data' => []]);
    }

    public function test_describes_a_get_request(): void
    {
        $request = (new TlynQuoteSource)->request();

        self::assertSame('GET', $request->method);
        self::assertSame('https://price.tlyn.ir/api/v1/price', $request->url);
        self::assertSame('tlyn', (new TlynQuoteSource)->key());
    }
}
