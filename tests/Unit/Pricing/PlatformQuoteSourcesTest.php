<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Application\Dto\SourceResultDto;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Infrastructure\Sources\MelligoldQuoteSource;
use App\Modules\Pricing\Infrastructure\Sources\MilliQuoteSource;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Platform haye 18 ayar ru payload e VAGHEI:
 *   `tests/Fixtures/milli-price-external.json` (2026-10-06)
 *   `tests/Fixtures/melligold-buy-sell.json` (2026-10-07)
 *
 * (talasea/technogold 2026-10-07 ba darkhast e sahebe proje hazf shodan.)
 *
 * 🔴 Har kodum zarib e vahed e KHODESH ro dare (rial/mg، toman/g).
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

    // ── milli ────────────────────────────────────────────────────

    /** `price18 = 265100` rial/MILLIGRAM → 265,100,000 rial/g. */
    public function test_milli_scales_milligram_rial_to_gram(): void
    {
        $result = (new MilliQuoteSource)->parse($this->fixture('milli-price-external.json'));

        self::assertSame(1, $result->count());
        $quote = $result->quotes[0];

        self::assertSame('milli', $quote->providerKey);
        self::assertSame('xau-18k-g-irr', $quote->instrument->value);
        self::assertSame(Side::Last, $quote->side);
        self::assertSame(265_100_000, $quote->price->minor);
        self::assertSame(265_100 * 1000, $quote->price->minor);
        self::assertSame(QuoteCurrency::IRR, $quote->price->quote);
    }

    /** `"2026-10-06T20:40:00"` vaght e Tehran ast → 17:10 UTC. */
    public function test_milli_reads_the_date_as_tehran_time(): void
    {
        $result = (new MilliQuoteSource)->parse($this->fixture('milli-price-external.json'));

        self::assertSame('2026-10-06T17:10:00+00:00', $result->quotes[0]->upstreamAt?->toIso8601String());
    }

    public function test_milli_keeps_a_fractional_milligram_price_exact(): void
    {
        $payload = $this->fixture('milli-price-external.json');
        $payload['data']['price18'] = '265100.5';

        $result = (new MilliQuoteSource)->parse($payload);

        self::assertSame(265_100_500, $result->quotes[0]->price->minor);
    }

    public function test_milli_rejects_a_payload_without_price(): void
    {
        $this->expectException(RuntimeException::class);

        (new MilliQuoteSource)->parse(['code' => 0, 'data' => ['date' => '2026-10-06T20:40:00']]);
    }

    public function test_milli_reports_an_unparsable_price_instead_of_throwing(): void
    {
        $payload = $this->fixture('milli-price-external.json');
        $payload['data']['price18'] = 'n/a';

        $result = (new MilliQuoteSource)->parse($payload);

        self::assertTrue($result->isEmpty());
        self::assertSame(['data.price18'], $result->unmappedLabels);
    }

    public function test_milli_describes_a_get_request(): void
    {
        $request = (new MilliQuoteSource)->request();

        self::assertSame('GET', $request->method);
        self::assertSame('https://milli.gold/api/v1/public/milli-price/external', $request->url);
        self::assertSame('milli', (new MilliQuoteSource)->key());
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
            self::assertSame('xau-18k-g-irr', $quote->instrument->value);
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

    // ── moshtarak ────────────────────────────────────────────────

    /** Hich kodum az platform ha bedun e payload e vaghei gheymat NEMISAZE. */
    public function test_platform_results_are_source_result_dtos(): void
    {
        self::assertInstanceOf(SourceResultDto::class, (new MilliQuoteSource)->parse($this->fixture('milli-price-external.json')));
        self::assertInstanceOf(SourceResultDto::class, (new MelligoldQuoteSource)->parse($this->fixture('melligold-buy-sell.json')));
    }
}
