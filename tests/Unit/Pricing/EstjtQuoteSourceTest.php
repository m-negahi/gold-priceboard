<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Infrastructure\Sources\EstjtQuoteSource;
use App\Modules\Pricing\Infrastructure\Sources\TgjuQuoteSource;
use PHPUnit\Framework\TestCase;

/**
 * Ru payload e VAGHEI ye `tests/Fixtures/estjt-new-price.json`.
 */
final class EstjtQuoteSourceTest extends TestCase
{
    /** @return array{gold: string, coin: string} */
    private function payload(): array
    {
        $json = (string) file_get_contents(__DIR__.'/../../Fixtures/estjt-new-price.json');

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, RawQuoteDto> */
    private function quotes(): array
    {
        $result = (new EstjtQuoteSource)->parse($this->payload());

        $byKey = [];
        foreach ($result->quotes as $quote) {
            $byKey[$quote->instrument->value] = $quote;
        }

        return $byKey;
    }

    public function test_parses_all_nine_instruments(): void
    {
        $quotes = $this->quotes();

        $expected = [
            'xau-oz-usd', 'xau-mesghal-irr', 'xau-18k-g-irr', 'xau-24k-g-irr',
            'xau-coin-emami-irr', 'xau-coin-bahar-irr', 'xau-coin-half-irr',
            'xau-coin-quarter-irr', 'xau-coin-gerami-irr',
        ];

        foreach ($expected as $symbol) {
            self::assertArrayHasKey($symbol, $quotes, "«{$symbol}» parse nashod.");
        }

        self::assertCount(9, $quotes);
    }

    /**
     * 🔴 Mohem tarin test e in file.
     *
     * estjt TOMAN mide. `۲۶٫۶۸۱٫۸۰۰` toman bayad beshe 266,818,000 RIAL.
     * Agar kasi `x10` ro bardare, in adad mishe 26,681,800 va test fail
     * mikone — jaye inke yek gheymat e 10 barabar ghalat be production bere.
     */
    public function test_converts_toman_to_rial(): void
    {
        $quotes = $this->quotes();

        self::assertSame(266_818_000, $quotes['xau-18k-g-irr']->price->minor);
        self::assertSame(355_720_000, $quotes['xau-24k-g-irr']->price->minor);
        self::assertSame(1_155_800_000, $quotes['xau-mesghal-irr']->price->minor);
        self::assertSame(2_752_000_000, $quotes['xau-coin-emami-irr']->price->minor);
        self::assertSame(2_628_000_000, $quotes['xau-coin-bahar-irr']->price->minor);
        self::assertSame(1_445_000_000, $quotes['xau-coin-half-irr']->price->minor);
        self::assertSame(800_000_000, $quotes['xau-coin-quarter-irr']->price->minor);
        self::assertSame(380_000_000, $quotes['xau-coin-gerami-irr']->price->minor);
    }

    /**
     * 🔴 «انس طلا» tu hamin feed DOLLAR ast, dar hali ke 8 radif e digar
     * Toman-and.
     *
     * In daghighan dalil e inke `unit` bayad per-instrument bashe. Agar yek
     * `upstream_unit=toman` e sath e provider e'mal beshe, ons mishe
     * `$41,420` — onghadr bavar-pazir ke kasi motavajeh nashe.
     */
    public function test_gold_ounce_is_dollars_while_everything_else_is_toman(): void
    {
        $quotes = $this->quotes();
        $ounce = $quotes['xau-oz-usd'];

        self::assertSame(QuoteCurrency::USD, $ounce->price->quote);
        // "$ ۴۱۴۲" -> 4142 dollar -> minor ba scale 4
        self::assertSame(41_420_000, $ounce->price->minor);
        self::assertSame(4142.0, $ounce->price->toFloat());

        // ...va NA x10 shode.
        self::assertNotSame(414_200_000, $ounce->price->minor);
    }

    /** Hame ye instrument haye dakheli IRR-and. */
    public function test_domestic_instruments_are_quoted_in_rial(): void
    {
        $quotes = $this->quotes();

        foreach ($quotes as $symbol => $quote) {
            if ($symbol === 'xau-oz-usd') {
                continue;
            }

            self::assertSame(QuoteCurrency::IRR, $quote->price->quote, "{$symbol} bayad IRR bashe.");
        }
    }

    /**
     * 🔴 Moghayese ba tgju ye hamun lahze — in check e nahayi ye vahed ast.
     *
     * Do manba' e mostaghel bayad ba'd az normalize be ham nazdik bashan.
     * Agar yeki az do taraf vahed esh kharab beshe, in test ba yek ekhtelaf e
     * 10 barabari fail mikone.
     */
    public function test_agrees_with_tgju_after_normalisation(): void
    {
        $estjt = $this->quotes();

        $tgjuJson = (string) file_get_contents(__DIR__.'/../../Fixtures/tgju-ajax.json');
        $tgjuResult = (new TgjuQuoteSource)->parse(
            json_decode($tgjuJson, true, 512, JSON_THROW_ON_ERROR)['current'],
        );

        $tgju = [];
        foreach ($tgjuResult->quotes as $quote) {
            if ($quote->side === Side::Last) {
                $tgju[$quote->instrument->value] = $quote;
            }
        }

        foreach (['xau-18k-g-irr', 'xau-24k-g-irr', 'xau-coin-emami-irr'] as $symbol) {
            $a = $estjt[$symbol]->price->minor;
            $b = $tgju[$symbol]->price->minor;

            self::assertEqualsWithDelta(
                $b,
                $a,
                $b * 0.02,
                "{$symbol}: estjt va tgju bishtar az 2% ekhtelaf daran — ehtemalan vahed kharab ast.",
            );
        }
    }

    // ── label ha ─────────────────────────────────────────────────

    /** estjt timestamp nemide — tazegi az `payloadHash` miad. */
    public function test_has_no_upstream_timestamp(): void
    {
        foreach ($this->quotes() as $quote) {
            self::assertNull($quote->upstreamAt);
        }
    }

    public function test_all_quotes_use_the_last_side(): void
    {
        foreach ($this->quotes() as $quote) {
            self::assertSame(Side::Last, $quote->side);
        }
    }

    public function test_every_quote_is_tagged_with_the_provider_key(): void
    {
        foreach ($this->quotes() as $quote) {
            self::assertSame('estjt', $quote->providerKey);
        }
    }

    /** Label e nashenas = markup avaz shode. Bayad gozaresh beshe, na saket bofte. */
    public function test_reports_unmapped_labels(): void
    {
        $payload = $this->payload();
        $payload['gold'] = str_replace('طلا ۱۸ عیار', 'طلای هجده عیار', $payload['gold']);

        $result = (new EstjtQuoteSource)->parse($payload);

        self::assertContains('طلای هجده عیار', $result->unmappedLabels);
        self::assertSame(8, $result->count(), 'baghi bayad salem parse shan.');
    }

    /**
     * Label ha fold mishan, pas shekl haye Arabi/ZWNJ bayad hanuz match
     * beshan. Markup e WordPress mitune har lahze `ی` ro be `ي` avaz kone.
     */
    public function test_matches_labels_written_with_arabic_letters(): void
    {
        $payload = $this->payload();
        $payload['gold'] = str_replace('طلای ۲۴ عیار', 'طلاي ۲۴ عيار', $payload['gold']);

        $result = (new EstjtQuoteSource)->parse($payload);
        $symbols = array_map(static fn (RawQuoteDto $q): string => $q->instrument->value, $result->quotes);

        self::assertContains('xau-24k-g-irr', $symbols);
        self::assertSame([], $result->unmappedLabels);
    }

    // ── taab-avari ───────────────────────────────────────────────

    public function test_tolerates_a_missing_section(): void
    {
        $result = (new EstjtQuoteSource)->parse(['gold' => $this->payload()['gold']]);

        self::assertSame(4, $result->count(), 'faghat radif haye bakhsh e tala.');
    }

    public function test_handles_an_empty_payload(): void
    {
        self::assertTrue((new EstjtQuoteSource)->parse([])->isEmpty());
    }

    public function test_handles_html_without_any_rows(): void
    {
        $result = (new EstjtQuoteSource)->parse(['gold' => '<table></table>', 'coin' => '']);

        self::assertTrue($result->isEmpty());
    }

    /** HTML e kharab nabayad exception bede — WordPress markup e valid nemide. */
    public function test_tolerates_malformed_html(): void
    {
        $result = (new EstjtQuoteSource)->parse([
            'gold' => '<table><tr><td class="name">طلا ۱۸ عیار<td class="price">۲۶٫۶۸۱٫۸۰۰</table>',
        ]);

        self::assertSame(1, $result->count());
        self::assertSame(266_818_000, $result->quotes[0]->price->minor);
    }

    public function test_skips_rows_with_unparsable_prices(): void
    {
        $result = (new EstjtQuoteSource)->parse([
            'gold' => '<table><tr><td class="name">طلا ۱۸ عیار</td><td class="price">—</td></tr></table>',
        ]);

        self::assertTrue($result->isEmpty());
    }

    public function test_exposes_its_key(): void
    {
        self::assertSame('estjt', (new EstjtQuoteSource)->key());
    }
}
