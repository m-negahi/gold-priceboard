<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Application\Dto\SourceResultDto;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Infrastructure\Sources\MilliQuoteSource;
use App\Modules\Pricing\Infrastructure\Sources\TalaseaQuoteSource;
use App\Modules\Pricing\Infrastructure\Sources\TechnogoldQuoteSource;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Se platform e 18 ayar ru payload e VAGHEI (2026-10-06):
 *   `tests/Fixtures/milli-price-external.json`
 *   `tests/Fixtures/talasea-get-gold-price.json`
 *   `tests/Fixtures/technogold-only-price.json`
 *
 * 🔴 Har kodum zarib e vahed e KHODESH ro dare (rial/mg، toman/mg، toman/g).
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

    // ── talasea ──────────────────────────────────────────────────

    /** `"26689"` toman/MILLIGRAM → ×1000 toman/g → ×10 = 266,890,000 rial/g. */
    public function test_talasea_scales_milligram_toman_to_rial_per_gram(): void
    {
        $result = (new TalaseaQuoteSource)->parse($this->fixture('talasea-get-gold-price.json'));

        self::assertSame(1, $result->count());
        $quote = $result->quotes[0];

        self::assertSame('talasea', $quote->providerKey);
        self::assertSame('xau-18k-g-irr', $quote->instrument->value);
        self::assertSame(Side::Last, $quote->side);
        self::assertSame(266_890_000, $quote->price->minor);
        self::assertSame(26_689 * 1000 * 10, $quote->price->minor);
        self::assertSame(0.62, $quote->upstreamChangePercent);
    }

    /** talasea timestamp nemide — tazegi az `payloadHash` miad. */
    public function test_talasea_has_no_upstream_timestamp(): void
    {
        $result = (new TalaseaQuoteSource)->parse($this->fixture('talasea-get-gold-price.json'));

        self::assertNull($result->quotes[0]->upstreamAt);
    }

    /** Agar ruzi `price` ashari beshe (`"26689.5"`)، bayad daghigh bemune. */
    public function test_talasea_keeps_a_fractional_price_exact(): void
    {
        $payload = $this->fixture('talasea-get-gold-price.json');
        $payload['price'] = '26689.5';

        $result = (new TalaseaQuoteSource)->parse($payload);

        self::assertSame(266_895_000, $result->quotes[0]->price->minor);
    }

    public function test_talasea_rejects_a_payload_without_price(): void
    {
        $this->expectException(RuntimeException::class);

        (new TalaseaQuoteSource)->parse(['fee' => 0.01]);
    }

    public function test_talasea_describes_a_get_request(): void
    {
        $request = (new TalaseaQuoteSource)->request();

        self::assertSame('GET', $request->method);
        self::assertSame('https://api.talasea.ir/api/market/getGoldPrice', $request->url);
        self::assertSame('talasea', (new TalaseaQuoteSource)->key());
    }

    // ── technogold ───────────────────────────────────────────────

    /**
     * 🔴 `buy_price` = gheymati ke KARBAR mikhare = ASK؛ `sell_price` = BID.
     * Esm ha az did e karbar-and — bar'aks e `_buy` e tgju.
     */
    public function test_technogold_maps_buy_to_ask_and_sell_to_bid(): void
    {
        $result = (new TechnogoldQuoteSource)->parse($this->fixture('technogold-only-price.json'));

        self::assertSame(2, $result->count());

        $bySide = [];
        foreach ($result->quotes as $quote) {
            self::assertSame('technogold', $quote->providerKey);
            self::assertSame('xau-18k-g-irr', $quote->instrument->value);
            $bySide[$quote->side->value] = $quote->price->minor;
        }

        // toman/g × 10
        self::assertSame(267_561_150, $bySide['ask']);
        self::assertSame(264_233_280, $bySide['bid']);
        self::assertGreaterThan($bySide['bid'], $bySide['ask']);
    }

    public function test_technogold_has_no_upstream_timestamp(): void
    {
        $result = (new TechnogoldQuoteSource)->parse($this->fixture('technogold-only-price.json'));

        foreach ($result->quotes as $quote) {
            self::assertNull($quote->upstreamAt);
        }
    }

    /** Yek taraf e gom-shode gozaresh mishe va taraf e digar salem mimune. */
    public function test_technogold_reports_a_missing_side(): void
    {
        $payload = $this->fixture('technogold-only-price.json');
        unset($payload['results']['sell_price']);

        $result = (new TechnogoldQuoteSource)->parse($payload);

        self::assertSame(1, $result->count());
        self::assertSame(Side::Ask, $result->quotes[0]->side);
        self::assertSame(['results.sell_price'], $result->unmappedLabels);
    }

    public function test_technogold_rejects_an_unsuccessful_payload(): void
    {
        $this->expectException(RuntimeException::class);

        (new TechnogoldQuoteSource)->parse(['succeed' => false, 'results' => ['buy_price' => 1]]);
    }

    public function test_technogold_builds_the_url_from_the_tradeable_id(): void
    {
        self::assertSame(
            'https://api2.technogold.gold/customer/tradeables/only-price/1',
            (new TechnogoldQuoteSource)->request()->url,
        );
        self::assertSame(
            'https://example.test/customer/tradeables/only-price/7',
            (new TechnogoldQuoteSource(['base' => 'https://example.test/', 'tradeable_id' => 7]))->request()->url,
        );
        self::assertSame('technogold', (new TechnogoldQuoteSource)->key());
    }

    // ── moshtarak ────────────────────────────────────────────────

    /** Hich kodum az se platform bedun e payload e vaghei gheymat NEMISAZE. */
    public function test_platform_results_are_source_result_dtos(): void
    {
        self::assertInstanceOf(SourceResultDto::class, (new MilliQuoteSource)->parse($this->fixture('milli-price-external.json')));
        self::assertInstanceOf(SourceResultDto::class, (new TalaseaQuoteSource)->parse($this->fixture('talasea-get-gold-price.json')));
        self::assertInstanceOf(SourceResultDto::class, (new TechnogoldQuoteSource)->parse($this->fixture('technogold-only-price.json')));
    }
}
