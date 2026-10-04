<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Infrastructure\Support\NumberParser;
use App\Modules\Pricing\Infrastructure\Support\PersianText;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

/**
 * Test ru payload haye VAGHEI ye zakhire-shode (`tests/Fixtures/`), na data ye
 * dast-saz.
 *
 * Dalil: bug haye vaghei — `\t` e jolo ye meghdar, separator e `٫`, ZWNJ tu
 * label, symbol haye bayat az 2018 — faghat tu payload e vaghei hastan. Yek
 * fixture e tamiz e dast-saz hamashun ro penhan mikone.
 *
 * In test ha hanuz `QuoteSource` nadaran (`P1-08`/`P1-09`); chizi ke sabt
 * mikonan in ast ke laye ye parse ru data ye vaghei durost kar mikone va
 * shekl e payload hamuni-e ke entezar darim.
 */
final class RealPayloadTest extends TestCase
{
    private const TGJU_IRR = [[','], null, 0];

    private const TGJU_USD = [[','], '.', 4];

    private const ESTJT = [['٫', '٬', ','], null, 0];

    /** @return array<string, mixed> */
    private function tgju(): array
    {
        $json = file_get_contents(__DIR__.'/../../Fixtures/tgju-ajax.json');
        self::assertIsString($json, 'fixture e tgju peyda nashod.');

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array{gold: string, coin: string} */
    private function estjt(): array
    {
        $json = file_get_contents(__DIR__.'/../../Fixtures/estjt-new-price.json');
        self::assertIsString($json, 'fixture e estjt peyda nashod.');

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    private function parse(string $raw, array $policy): ?int
    {
        [$thousands, $decimal, $scale] = $policy;

        return NumberParser::toMinor($raw, $thousands, $decimal, $scale);
    }

    // ── tgju ─────────────────────────────────────────────────────

    public function test_tgju_payload_has_the_expected_shape(): void
    {
        $payload = $this->tgju();

        self::assertArrayHasKey('current', $payload);
        self::assertGreaterThan(900, count($payload['current']), 'tgju bayad sad ha symbol bede.');

        $sample = $payload['current']['geram18'];
        foreach (['p', 'h', 'l', 'd', 'dp', 'ts'] as $key) {
            self::assertArrayHasKey($key, $sample);
        }
    }

    /** Har kelid-i ke map mikonim bayad vaghe'an tu payload bashe. */
    public function test_every_mapped_tgju_key_exists(): void
    {
        $current = $this->tgju()['current'];

        $mapped = [
            'geram18', 'geram24', 'tgju_gold_irg18', 'tgju_gold_irg18_buy',
            'mesghal', 'sekee', 'sekeb', 'nim', 'rob', 'gerami',
            'silver_999', 'silver_925', 'ons', 'silver', 'copperp',
            'price_dollar_rl',
        ];

        foreach ($mapped as $key) {
            self::assertArrayHasKey($key, $current, "kelid e «{$key}» tu payload e tgju nist.");
        }
    }

    public function test_parses_real_tgju_rial_prices(): void
    {
        $current = $this->tgju()['current'];

        self::assertSame(266_400_000, $this->parse($current['geram18']['p'], self::TGJU_IRR));
        self::assertSame(355_196_000, $this->parse($current['geram24']['p'], self::TGJU_IRR));
        self::assertSame(1_154_030_000, $this->parse($current['mesghal']['p'], self::TGJU_IRR));
        self::assertSame(2_753_400_000, $this->parse($current['sekee']['p'], self::TGJU_IRR));
    }

    public function test_parses_real_tgju_usd_prices(): void
    {
        $current = $this->tgju()['current'];

        // $4140.19 ons e tala
        self::assertSame(41_401_900, $this->parse($current['ons']['p'], self::TGJU_USD));
        // $60.37 ons e noghre
        self::assertSame(603_700, $this->parse($current['silver']['p'], self::TGJU_USD));
        // $6.492 mes
        self::assertSame(64_920, $this->parse($current['copperp']['p'], self::TGJU_USD));
    }

    /**
     * 🔴 Dalil e vojud e `JUNK` tu `NumberParser`.
     * `mesghal_buy` e vaghei `"\t8520000"` ast.
     */
    public function test_real_payload_contains_values_with_leading_whitespace(): void
    {
        $raw = $this->tgju()['current']['mesghal_buy']['p'];

        self::assertStringStartsWith("\t", $raw, 'fixture dige `\t` nadare — in test bi-ma'.'ni mishe.');
        self::assertSame(8_520_000, $this->parse($raw, self::TGJU_IRR));
    }

    /**
     * 🔴 Dalil e vojud e filter e staleness.
     * Aksar e 963 symbol e tgju SAL ha bayat-an. Bedun e filter, `zinc` e 2021
     * va `geram18_buy` e 2018 ba'd az yek tick ru tablo mishinan.
     */
    public function test_most_tgju_symbols_are_stale(): void
    {
        $current = $this->tgju()['current'];
        $freshDate = '2026-10-04';

        $fresh = array_filter(
            $current,
            static fn ($row): bool => is_array($row) && str_starts_with((string) ($row['ts'] ?? ''), $freshDate),
        );

        self::assertLessThan(
            count($current) / 2,
            count($fresh),
            'entezar darim bishtar az nesf e symbol haye tgju bayat bashan.',
        );
    }

    public function test_known_dead_symbols_are_years_old(): void
    {
        $current = $this->tgju()['current'];

        self::assertStringStartsWith('2021', $current['zinc']['ts']);
        self::assertStringStartsWith('2018', $current['geram18_buy']['ts']);
    }

    /**
     * `tgju_gold_irg18` + `_buy` tanha jofte bid/ask e ZENDE-and — hamun
     * dalil e inke faghat `GoldMeltedGram` `hasNativeSides()` dare.
     */
    public function test_melted_gold_is_the_only_live_bid_ask_pair(): void
    {
        $current = $this->tgju()['current'];

        $ask = $current['tgju_gold_irg18'];
        $bid = $current['tgju_gold_irg18_buy'];

        self::assertStringStartsWith('2026-10-04', $ask['ts']);
        self::assertStringStartsWith('2026-10-04', $bid['ts']);

        $askMinor = $this->parse($ask['p'], self::TGJU_IRR);
        $bidMinor = $this->parse($bid['p'], self::TGJU_IRR);

        self::assertGreaterThan($bidMinor, $askMinor, 'foroosh bayad az kharid balatar bashe.');
    }

    // ── estjt ────────────────────────────────────────────────────

    public function test_estjt_payload_has_two_html_fragments(): void
    {
        $payload = $this->estjt();

        self::assertArrayHasKey('gold', $payload);
        self::assertArrayHasKey('coin', $payload);
        self::assertStringContainsString('<table>', $payload['gold']);
    }

    /**
     * @return array<string, string> label e fold-shode => gheymat e kham
     */
    private function scrapeRows(string $html): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);

        $rows = [];
        foreach ($xpath->query('//tr[td[@class="name"]]') as $tr) {
            $name = $xpath->query('.//td[@class="name"]', $tr)->item(0);
            $price = $xpath->query('.//td[@class="price"]', $tr)->item(0);

            if ($name !== null && $price !== null) {
                $rows[PersianText::fold(trim($name->textContent))] = trim($price->textContent);
            }
        }

        return $rows;
    }

    public function test_scrapes_all_nine_estjt_instruments(): void
    {
        $payload = $this->estjt();
        $rows = $this->scrapeRows($payload['gold']) + $this->scrapeRows($payload['coin']);

        $expected = [
            'انس طلا', 'مظنه تهران', 'طلا 18 عیار', 'طلای 24 عیار',
            'سکه طرح جدید', 'سکه طرح قدیم', 'نیم سکه', 'ربع سکه', 'سکه گرمی',
        ];

        foreach ($expected as $label) {
            self::assertArrayHasKey($label, $rows, "radif e «{$label}» scrape nashod.");
        }
    }

    /**
     * 🔴 Mohem tarin assert e in file.
     *
     * estjt TOMAN mide. Ba'd az x10, tala ye 18 ayar bayad be gheymat e rial e
     * tgju ye hamun lahze nazdik bashe. Agar kasi x10 ro bardare, in test
     * ba yek ekhtelaf e 10 barabari fail mikone — na production.
     */
    public function test_estjt_prices_are_toman_and_match_tgju_after_conversion(): void
    {
        $rows = $this->scrapeRows($this->estjt()['gold']);

        $tomanMinor = $this->parse($rows['طلا 18 عیار'], self::ESTJT);
        self::assertSame(26_681_800, $tomanMinor);

        $rialMinor = $tomanMinor * 10;
        $tgjuRial = $this->parse($this->tgju()['current']['geram18']['p'], self::TGJU_IRR);

        // Do manba' e motefavet — chand dahom darsad ekhtelaf tabi'i-e.
        self::assertEqualsWithDelta(
            $tgjuRial,
            $rialMinor,
            $tgjuRial * 0.02,
            'ba\'d az tabdil e Toman be Rial, estjt bayad be tgju nazdik bashe.',
        );
    }

    public function test_estjt_coin_prices_parse_with_arabic_separator(): void
    {
        $rows = $this->scrapeRows($this->estjt()['coin']);

        self::assertSame(275_200_000, $this->parse($rows['سکه طرح جدید'], self::ESTJT));
        self::assertSame(262_800_000, $this->parse($rows['سکه طرح قدیم'], self::ESTJT));
        self::assertSame(144_500_000, $this->parse($rows['نیم سکه'], self::ESTJT));
        self::assertSame(80_000_000, $this->parse($rows['ربع سکه'], self::ESTJT));
        self::assertSame(38_000_000, $this->parse($rows['سکه گرمی'], self::ESTJT));
    }

    /**
     * 🔴 Dalil e inke `upstream_unit` bayad PER-INSTRUMENT bashe.
     * «انس طلا» ye estjt ba `$` miad va DOLLAR ast, dar hali ke 8 radif e
     * digar Toman-and.
     */
    public function test_estjt_gold_ounce_is_quoted_in_dollars_unlike_the_rest(): void
    {
        $rows = $this->scrapeRows($this->estjt()['gold']);

        self::assertStringContainsString('$', $rows['انس طلا']);

        // Baghi ye radif ha hich alamat e dollar nadaran.
        foreach (['مظنه تهران', 'طلا 18 عیار', 'طلای 24 عیار'] as $label) {
            self::assertStringNotContainsString('$', $rows[$label]);
        }
    }
}
