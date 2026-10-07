<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Application\Dto\SourceResultDto;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Infrastructure\Sources\MelligoldQuoteSource;
use App\Modules\Pricing\Infrastructure\Sources\WallgoldQuoteSource;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Platform ha ru payload e VAGHEI:
 *   `tests/Fixtures/melligold-buy-sell.json` (2026-10-07 — ABSHODE)
 *   `tests/Fixtures/wallgold-price.json` (2026-10-07 — 18 ayar + noghre 925)
 *
 * 🔴 Zarib e vahed e har source mal e KHODESH e (toman/g inja).
 * Meghdar haye daghigh tu `SourceFixtureMatrixTest`؛ inja raftar.
 */
final class PlatformQuoteSourcesTest extends TestCase
{
    /** @return array<string, mixed> */
    private function fixture(string $name): array
    {
        $json = (string) file_get_contents(__DIR__.'/../../Fixtures/'.$name);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    // ── melligold ────────────────────────────────────────────────

    /**
     * 🔴 `price_buy` = gheymati ke KARBAR mikhare = ASK؛ `price_sell` = BID
     * (esm ha az did e karbar-and — hamun gharardad e technogold e sabegh).
     * Dar fixture har do BARABAR-and؛ map e jodagane mimune.
     */
    public function test_melligold_scales_toman_per_gram_and_maps_sides(): void
    {
        $result = (new MelligoldQuoteSource)->parse($this->fixture('melligold-buy-sell.json'));

        self::assertSame(2, $result->count());

        $bySide = [];
        foreach ($result->quotes as $quote) {
            self::assertSame('melligold', $quote->providerKey);
            self::assertSame('xau-melted-g-irr', $quote->instrument->value);
            self::assertSame(QuoteCurrency::IRR, $quote->price->quote);
            $bySide[$quote->side->value] = $quote->price->minor;
        }

        // toman/g × 10
        self::assertSame(265_536_240, $bySide['ask']);
        self::assertSame(265_536_240, $bySide['bid']);
        self::assertGreaterThanOrEqual($bySide['bid'], $bySide['ask']);
    }

    /** `timestamp` epoch e SANIYE ast (na ms). */
    public function test_melligold_reads_epoch_seconds_as_utc(): void
    {
        $result = (new MelligoldQuoteSource)->parse($this->fixture('melligold-buy-sell.json'));

        foreach ($result->quotes as $quote) {
            self::assertSame('2026-10-07T05:55:00+00:00', $quote->upstreamAt?->toIso8601String());
        }
    }

    /** Yek taraf e gom-shode gozaresh mishe va taraf e digar salem mimune. */
    public function test_melligold_reports_a_missing_side(): void
    {
        $payload = $this->fixture('melligold-buy-sell.json');
        unset($payload['data']['price_sell']);

        $result = (new MelligoldQuoteSource)->parse($payload);

        self::assertSame(1, $result->count());
        self::assertSame(Side::Ask, $result->quotes[0]->side);
        self::assertSame(['data.price_sell'], $result->unmappedLabels);
    }

    public function test_melligold_rejects_a_payload_without_data(): void
    {
        $this->expectException(RuntimeException::class);

        (new MelligoldQuoteSource)->parse(['message' => 'Success']);
    }

    /**
     * 🔴 `cookies` bayad true bashe: melligold posht e challenge e Arvan ast
     * (307 be hamun URL + Set-Cookie) va bedun e jar redirect halghe mishe.
     */
    public function test_melligold_describes_a_cookie_enabled_get_request(): void
    {
        $request = (new MelligoldQuoteSource)->request();

        self::assertSame('GET', $request->method);
        self::assertSame('https://melligold.com/api/v1/exchange/buy-sell-price/?symbol=XAU18', $request->url);
        self::assertTrue($request->cookies);
        self::assertSame('melligold', (new MelligoldQuoteSource)->key());
    }

    // ── wallgold ─────────────────────────────────────────────────

    /**
     * 🔴 Side az did e BAZAAR ast (mesl e `_buy` e tgju): `BUY` = kharid e
     * platform az karbar = bid؛ `SELL` = ask. Do bazaar: 18 ayar + noghre 925.
     */
    public function test_wallgold_maps_both_markets_with_market_perspective_sides(): void
    {
        $result = (new WallgoldQuoteSource)->parse($this->fixture('wallgold-price.json'));

        self::assertSame(4, $result->count());

        $byKey = [];
        foreach ($result->quotes as $quote) {
            self::assertSame('wallgold', $quote->providerKey);
            self::assertSame(QuoteCurrency::IRR, $quote->price->quote);
            $byKey[$quote->instrument->value.':'.$quote->side->value] = $quote->price->minor;
        }

        // toman/g × 10
        self::assertSame(265_300_000, $byKey['xau-18k-g-irr:ask']);
        self::assertSame(265_300_000, $byKey['xau-18k-g-irr:bid']);
        self::assertSame(4_980_000, $byKey['xag-925-g-irr:ask']);
        self::assertSame(4_980_000, $byKey['xag-925-g-irr:bid']);
    }

    /** `currentTime`/`priceExpiresAt` zaman e update NISTAN — timestamp nadarim. */
    public function test_wallgold_has_no_upstream_timestamp(): void
    {
        $result = (new WallgoldQuoteSource)->parse($this->fixture('wallgold-price.json'));

        foreach ($result->quotes as $quote) {
            self::assertNull($quote->upstreamAt);
        }
    }

    /** Yek call e namovafagh (success=false) faghat hamun taraf ro miandaze. */
    public function test_wallgold_reports_a_failed_side_and_keeps_the_rest(): void
    {
        $payload = $this->fixture('wallgold-price.json');
        $payload['SLV_925TMN']['SELL']['success'] = false;

        $result = (new WallgoldQuoteSource)->parse($payload);

        self::assertSame(3, $result->count());
        self::assertSame(['SLV_925TMN.SELL'], $result->unmappedLabels);
    }

    public function test_wallgold_rejects_an_empty_payload(): void
    {
        $this->expectException(RuntimeException::class);

        (new WallgoldQuoteSource)->parse([]);
    }

    /**
     * 🔴 Chand request e joda lazem dare (symbol × side) → `request()` null
     * ast va `FetchStage` masir e direct ro mire؛ API key az config/env.
     */
    public function test_wallgold_has_no_single_pooled_request(): void
    {
        self::assertNull((new WallgoldQuoteSource)->request());
        self::assertSame('wallgold', (new WallgoldQuoteSource)->key());
    }

    // ── moshtarak ────────────────────────────────────────────────

    /** Hich platform-i bedun e payload e vaghei gheymat NEMISAZE. */
    public function test_platform_results_are_source_result_dtos(): void
    {
        self::assertInstanceOf(SourceResultDto::class, (new MelligoldQuoteSource)->parse($this->fixture('melligold-buy-sell.json')));
        self::assertInstanceOf(SourceResultDto::class, (new WallgoldQuoteSource)->parse($this->fixture('wallgold-price.json')));
    }
}
