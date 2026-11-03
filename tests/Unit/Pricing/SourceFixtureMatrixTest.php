<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Infrastructure\Sources\EcogoldQuoteSource;
use App\Modules\Pricing\Infrastructure\Sources\EstjtQuoteSource;
use App\Modules\Pricing\Infrastructure\Sources\MelligoldQuoteSource;
use App\Modules\Pricing\Infrastructure\Sources\NerkhQuoteSource;
use App\Modules\Pricing\Infrastructure\Sources\TgjuQuoteSource;
use App\Modules\Pricing\Infrastructure\Sources\TlynQuoteSource;
use App\Modules\Pricing\Infrastructure\Sources\WallgoldQuoteSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 🔴 HAR instrument e HAR source، ba meghdar e DAGHIGH e minor، ru fixture e
 * VAGHEI.
 *
 * ── Chera yek file e jodagane ────────────────────────────────────────
 * Khata ye 10x (toman jaye rial، ya yek `×10` e farāmush-shode) ro **hich
 * mohafez e runtime nemitune begire** baraye avvalin bar:
 *   - `Instrument::sanityBand()` amdan onghadr pahn ast ke tavarrom e rial
 *     ro tahammol kone (tozih e kamel tu khod e un method).
 *   - Enheraf e tick-be-tick be `previous` niaz dare.
 *   - `PriceSanity::crossProvider()` (`P1-24`) be yek provider e DIGAR niaz
 *     dare ke hamun symbol ro bede.
 *
 * Tanha jayi ke mishe un khata ro ba YEK adad e daghigh gereft، hamin
 * jadval ast. Pas yek ja mimune، kamel ast، va **mohafez e kamel budan**
 * dare.
 *
 * ── Mohafez e kamel budan ───────────────────────────────────────────
 * `test_*_matrix_is_exhaustive` majmu'e ye kelid haye jadval ro ba majmu'e
 * ye kelid hayi ke Source VAGHE'AN az fixture misaze moghayese mikone. Pas:
 *   - map e jadid ezafe she va jadval be-rooz nashe -> FAIL
 *   - map hazf she va jadval be-rooz nashe -> FAIL
 * Bedun e un mohafez، yek `foreach` ru jadval faghat chizi ro test mikone
 * ke ghablan neveshte shode — ya'ni yek symbol e jadid SAKET bi-test mimune.
 */
final class SourceFixtureMatrixTest extends TestCase
{
    /** @var array<string, array<string, RawQuoteDto>> */
    private static array $cache = [];

    /**
     * tgju: 16 quote. Gheymat ha RIAL-and (bar khalaf e estjt).
     *
     * `xau-melted-g-irr` tanha instrument-i ast ke in feed jofte bid/ask e
     * VAGHEI baraye un mide.
     *
     * @return array<string, array{0: string, 1: int, 2: QuoteCurrency}>
     */
    public static function tgjuMatrix(): array
    {
        return self::rows([
            'xau-18k-g-irr:last' => [266_400_000, QuoteCurrency::IRR],
            'xau-24k-g-irr:last' => [355_196_000, QuoteCurrency::IRR],
            'xau-melted-g-irr:ask' => [264_921_000, QuoteCurrency::IRR],
            'xau-melted-g-irr:bid' => [263_175_000, QuoteCurrency::IRR],
            'xau-mesghal-irr:last' => [1_154_030_000, QuoteCurrency::IRR],
            'xau-coin-emami-irr:last' => [2_753_400_000, QuoteCurrency::IRR],
            'xau-coin-bahar-irr:last' => [2_619_450_000, QuoteCurrency::IRR],
            'xau-coin-half-irr:last' => [1_429_600_000, QuoteCurrency::IRR],
            'xau-coin-quarter-irr:last' => [789_900_000, QuoteCurrency::IRR],
            'xau-coin-gerami-irr:last' => [380_000_000, QuoteCurrency::IRR],
            'xag-999-g-irr:last' => [5_534_900, QuoteCurrency::IRR],
            'xag-925-g-irr:last' => [5_119_760, QuoteCurrency::IRR],
            // USD — scale 4، pas minor = dollar × 10,000.
            'xau-oz-usd:last' => [41_401_900, QuoteCurrency::USD],
            'xag-oz-usd:last' => [603_700, QuoteCurrency::USD],
            'xcu-lb-usd:last' => [64_920, QuoteCurrency::USD],
            'usd-irr:last' => [2_711_600, QuoteCurrency::IRR],
        ]);
    }

    /**
     * estjt: 9 quote. Hame TOMAN-and **be joz «انس طلا» ke DOLLAR ast**.
     *
     * 🔴 Hamun estesna dalil e in ast ke `upstream_unit` bayad
     * PER-INSTRUMENT bashe: yek `upstream_unit=toman` e sath e provider،
     * ons ro ×10 mikone va `$41,420` ro mikone `$414,200` — onghadr
     * bavar-pazir ke az zir e cheshm rad beshe.
     *
     * @return array<string, array{0: string, 1: int, 2: QuoteCurrency}>
     */
    public static function estjtMatrix(): array
    {
        return self::rows([
            // Toman × 10 -> rial.
            'xau-18k-g-irr:last' => [266_818_000, QuoteCurrency::IRR],
            'xau-24k-g-irr:last' => [355_720_000, QuoteCurrency::IRR],
            'xau-mesghal-irr:last' => [1_155_800_000, QuoteCurrency::IRR],
            'xau-coin-emami-irr:last' => [2_752_000_000, QuoteCurrency::IRR],
            'xau-coin-bahar-irr:last' => [2_628_000_000, QuoteCurrency::IRR],
            'xau-coin-half-irr:last' => [1_445_000_000, QuoteCurrency::IRR],
            'xau-coin-quarter-irr:last' => [800_000_000, QuoteCurrency::IRR],
            'xau-coin-gerami-irr:last' => [380_000_000, QuoteCurrency::IRR],
            // …va in yeki DOLLAR، bedun e ×10.
            'xau-oz-usd:last' => [41_420_000, QuoteCurrency::USD],
        ]);
    }

    /**
     * nerkh: 11 quote. IRR ha TOMAN-and، `OUNCE`/`OUNCE_SILVER` DOLLAR.
     *
     * Fixture 2026-10-06 ast (do rooz ba'd az tgju/estjt)؛ bazaar ~0.2–0.8%
     * jabeja shode، pas moghayese ye cross-source hanuz zir e 3% mimune.
     *
     * @return array<string, array{0: string, 1: int, 2: QuoteCurrency}>
     */
    public static function nerkhMatrix(): array
    {
        return self::rows([
            // Toman × 10 -> rial.
            'xau-18k-g-irr:last' => [267_045_000, QuoteCurrency::IRR],
            'xau-24k-g-irr:last' => [356_020_000, QuoteCurrency::IRR],
            'xau-mesghal-irr:last' => [1_158_950_000, QuoteCurrency::IRR],
            'xau-coin-emami-irr:last' => [2_722_900_000, QuoteCurrency::IRR],
            'xau-coin-bahar-irr:last' => [2_648_100_000, QuoteCurrency::IRR],
            'xau-coin-half-irr:last' => [1_434_000_000, QuoteCurrency::IRR],
            'xau-coin-quarter-irr:last' => [772_000_000, QuoteCurrency::IRR],
            'xau-coin-gerami-irr:last' => [381_500_000, QuoteCurrency::IRR],
            'usd-irr:last' => [2_691_000, QuoteCurrency::IRR],
            // USD — scale 4، bedun e ×10.
            'xau-oz-usd:last' => [41_640_000, QuoteCurrency::USD],
            'xag-oz-usd:last' => [610_000, QuoteCurrency::USD],
            // 🔴 MAZANEH (abshode، per mesghal) ÷4.3318 → gram.
            'xau-melted-g-irr:last' => [267_041_415, QuoteCurrency::IRR],
            // 🔴 MOHASEBATI: ons e noghre × dollar (bcmath، half-up) —
            // 610,000 × 2,691,000 × khulus / 311,034,768.
            'xag-999-g-irr:last' => [5_272_300, QuoteCurrency::IRR],
            'xag-925-g-irr:last' => [4_881_759, QuoteCurrency::IRR],
        ]);
    }

    /**
     * Platform ha (API e khod e site) — faghat 18 ayar.
     *
     *   melligold:  toman/g، jofte (`price_buy` = ask، `price_sell` = bid —
     *               dar fixture barabar)
     *
     * @return array<string, array{0: string, 1: int, 2: QuoteCurrency}>
     */
    public static function melligoldMatrix(): array
    {
        // 🔴 ABSHODE (na 18 ayar) — platform e kharid/foroosh e abshode.
        return self::rows([
            'xau-melted-g-irr:ask' => [265_536_240, QuoteCurrency::IRR],
            'xau-melted-g-irr:bid' => [265_536_240, QuoteCurrency::IRR],
        ]);
    }

    /** @return array<string, array{0: string, 1: int, 2: QuoteCurrency}> */
    public static function wallgoldMatrix(): array
    {
        // 🔴 «18 ayar» e wallgold ham ABSHODE ye moamelati-e (hame ye
        // platform ha hamin-tor-and).
        return self::rows([
            'xau-melted-g-irr:ask' => [265_300_000, QuoteCurrency::IRR],
            'xau-melted-g-irr:bid' => [265_300_000, QuoteCurrency::IRR],
            'xag-925-g-irr:ask' => [4_980_000, QuoteCurrency::IRR],
            'xag-925-g-irr:bid' => [4_980_000, QuoteCurrency::IRR],
        ]);
    }

    /**
     * tlyn (طلایین): HEZAR toman → ×1000 ×10؛ jofte vaghei (`sell`=ask،
     * `buy`=bid)؛ sekke ha buy=sell. `XAU` dollar.
     *
     * @return array<string, array{0: string, 1: int, 2: QuoteCurrency}>
     */
    public static function tlynMatrix(): array
    {
        return self::rows([
            // 🔴 `GOLD18` e tlyn dar vaghe' ABSHODE ast.
            'xau-melted-g-irr:ask' => [268_910_000, QuoteCurrency::IRR],
            'xau-melted-g-irr:bid' => [266_770_000, QuoteCurrency::IRR],
            'xau-mesghal-irr:ask' => [1_164_890_000, QuoteCurrency::IRR],
            'xau-mesghal-irr:bid' => [1_155_600_000, QuoteCurrency::IRR],
            'xau-coin-emami-irr:ask' => [2_719_100_000, QuoteCurrency::IRR],
            'xau-coin-emami-irr:bid' => [2_719_100_000, QuoteCurrency::IRR],
            'xau-coin-half-irr:ask' => [1_434_600_000, QuoteCurrency::IRR],
            'xau-coin-half-irr:bid' => [1_434_600_000, QuoteCurrency::IRR],
            'xau-coin-quarter-irr:ask' => [772_300_000, QuoteCurrency::IRR],
            'xau-coin-quarter-irr:bid' => [772_300_000, QuoteCurrency::IRR],
            'xau-oz-usd:ask' => [41_740_000, QuoteCurrency::USD],
            'xau-oz-usd:bid' => [41_740_000, QuoteCurrency::USD],
        ]);
    }

    /**
     * ecogold (اکوگلد): toman ba 8 ashar e sefr → ×10؛ jofte vaghei
     * (`buy_price`=ask، `sell_price`=bid). «18 ayar» ABSHODE + noghre 999.
     *
     * @return array<string, array{0: string, 1: int, 2: QuoteCurrency}>
     */
    public static function ecogoldMatrix(): array
    {
        return self::rows([
            'xau-melted-g-irr:ask' => [257_830_000, QuoteCurrency::IRR],
            'xau-melted-g-irr:bid' => [257_810_000, QuoteCurrency::IRR],
            'xau-coin-emami-irr:ask' => [2_653_200_000, QuoteCurrency::IRR],
            'xau-coin-emami-irr:bid' => [2_637_000_000, QuoteCurrency::IRR],
            'xau-coin-half-irr:ask' => [1_421_000_000, QuoteCurrency::IRR],
            'xau-coin-half-irr:bid' => [1_414_000_000, QuoteCurrency::IRR],
            'xau-coin-quarter-irr:ask' => [760_000_000, QuoteCurrency::IRR],
            'xau-coin-quarter-irr:bid' => [756_760_000, QuoteCurrency::IRR],
            'xag-999-g-irr:ask' => [4_730_000, QuoteCurrency::IRR],
            'xag-999-g-irr:bid' => [4_650_000, QuoteCurrency::IRR],
        ]);
    }

    /**
     * @param  array<string, array{0: int, 1: QuoteCurrency}>  $matrix
     * @return array<string, array{0: string, 1: int, 2: QuoteCurrency}>
     */
    private static function rows(array $matrix): array
    {
        $rows = [];

        foreach ($matrix as $key => [$minor, $currency]) {
            $rows[$key] = [$key, $minor, $currency];
        }

        return $rows;
    }

    // ── tgju ────────────────────────────────────────────────────

    #[DataProvider('tgjuMatrix')]
    public function test_tgju_parses_the_exact_minor_value(string $key, int $minor, QuoteCurrency $currency): void
    {
        self::assertQuote('tgju', $key, $minor, $currency);
    }

    public function test_the_tgju_matrix_is_exhaustive(): void
    {
        self::assertMatrixCoversTheSource('tgju', self::tgjuMatrix());
    }

    // ── estjt ───────────────────────────────────────────────────

    #[DataProvider('estjtMatrix')]
    public function test_estjt_parses_the_exact_minor_value(string $key, int $minor, QuoteCurrency $currency): void
    {
        self::assertQuote('estjt', $key, $minor, $currency);
    }

    public function test_the_estjt_matrix_is_exhaustive(): void
    {
        self::assertMatrixCoversTheSource('estjt', self::estjtMatrix());
    }

    // ── nerkh ───────────────────────────────────────────────────

    #[DataProvider('nerkhMatrix')]
    public function test_nerkh_parses_the_exact_minor_value(string $key, int $minor, QuoteCurrency $currency): void
    {
        self::assertQuote('nerkh', $key, $minor, $currency);
    }

    public function test_the_nerkh_matrix_is_exhaustive(): void
    {
        self::assertMatrixCoversTheSource('nerkh', self::nerkhMatrix());
    }

    // ── platform ha ─────────────────────────────────────────────

    #[DataProvider('melligoldMatrix')]
    public function test_melligold_parses_the_exact_minor_value(string $key, int $minor, QuoteCurrency $currency): void
    {
        self::assertQuote('melligold', $key, $minor, $currency);
    }

    #[DataProvider('wallgoldMatrix')]
    public function test_wallgold_parses_the_exact_minor_value(string $key, int $minor, QuoteCurrency $currency): void
    {
        self::assertQuote('wallgold', $key, $minor, $currency);
    }

    #[DataProvider('tlynMatrix')]
    public function test_tlyn_parses_the_exact_minor_value(string $key, int $minor, QuoteCurrency $currency): void
    {
        self::assertQuote('tlyn', $key, $minor, $currency);
    }

    #[DataProvider('ecogoldMatrix')]
    public function test_ecogold_parses_the_exact_minor_value(string $key, int $minor, QuoteCurrency $currency): void
    {
        self::assertQuote('ecogold', $key, $minor, $currency);
    }

    /** @param array<string, array{0: string, 1: int, 2: QuoteCurrency}> $matrix */
    #[DataProvider('platformMatrices')]
    public function test_the_platform_matrix_is_exhaustive(string $source, array $matrix): void
    {
        self::assertMatrixCoversTheSource($source, $matrix);
    }

    /** @return array<string, array{0: string, 1: array<string, array{0: string, 1: int, 2: QuoteCurrency}>}> */
    public static function platformMatrices(): array
    {
        return [
            'melligold' => ['melligold', self::melligoldMatrix()],
            'wallgold' => ['wallgold', self::wallgoldMatrix()],
            'tlyn' => ['tlyn', self::tlynMatrix()],
            'ecogold' => ['ecogold', self::ecogoldMatrix()],
        ];
    }

    // ── 🔴 Moghayese ye do manba' e MOSTAGHEL ───────────────────

    /**
     * Mohem tarin mohafez e in file: har symbol-i ke HAR DO source midan،
     * bayad be ham nazdik bashe.
     *
     * Chera onghadr ghavi-e: do source kamelan mostaghel-and (yeki JSON e
     * rial، yeki HTML e toman ba separator e `٫`). Agar yeki az un ha vahed
     * ya martabe ash kharab beshe، ekhtelaf mishe 90% (toman/rial) ya 900%
     * (10x) — kheyli balatar az har ekhtelaf e vaghei ye bazaar.
     *
     * Saghf 3%: andaze-giri ye vaghei ru hamin fixture ha bishtarin ekhtelaf
     * 1.28% ast (rob' e sekke — sekke haye kuchik premium e maghaze-i
     * daran). 3% ja mide bedun e inke khata ye martabe-i ro az dast bede.
     *
     * ⚠️ Lista ye symbol ha SAKHT-CODE nashode — az eshterak e do fixture
     * hesab mishe، pas agar ruzi estjt yek symbol e jadid ezafe kone، un
     * khod-be-khod inja miad.
     */
    #[DataProvider('sourcesComparedWithTgju')]
    public function test_both_sources_agree_on_every_shared_symbol(string $source, int $minShared): void
    {
        $tgju = self::quotes('tgju');
        $other = self::quotes($source);

        $shared = array_intersect(array_keys($other), array_keys($tgju));

        if ($source === 'nerkh') {
            // 🔴 Noghre ye MOHASEBATI ye nerkh gheymat e SPOT ast va bazaar
            // e Iran premium dare (andaze-giri: tgju ~4.7% balatar) — az
            // saghf e 3% e in test mostasna، saghf e jodagane ye khodesh
            // ro tu `test_derived_silver_stays_within_the_market_band` dare.
            $shared = array_diff($shared, ['xag-999-g-irr:last', 'xag-925-g-irr:last']);
        }

        self::assertGreaterThanOrEqual(
            $minShared,
            count($shared),
            'eshterak kamtar az entezar ast — map e yeki az source ha kharab shode?',
        );

        foreach ($shared as $key) {
            $a = $other[$key]->price->minor;
            $b = $tgju[$key]->price->minor;

            self::assertSame(
                $other[$key]->price->quote,
                $tgju[$key]->price->quote,
                "{$key}: do source arz haye MOTEFAVET midan — flip e arz.",
            );

            self::assertEqualsWithDelta(
                $b,
                $a,
                $b * 0.03,
                "{$key}: {$source}={$a} va tgju={$b} bishtar az 3% ekhtelaf daran — ".
                'ehtemalan vahed ya martabe tu yeki az do source kharab ast.',
            );
        }
    }

    /**
     * Noghre ye mohasebati (spot) nabayad az bazaar (tgju) bish az 10% dur
     * beshe — 10% khata ye martabe/vahed ro migire (90%/900%) vali premium
     * e tabi'i ye ~5% ro rad nemikone.
     */
    public function test_derived_silver_stays_within_the_market_band(): void
    {
        $nerkh = self::quotes('nerkh');
        $tgju = self::quotes('tgju');

        foreach (['xag-999-g-irr:last', 'xag-925-g-irr:last'] as $key) {
            $spot = $nerkh[$key]->price->minor;
            $market = $tgju[$key]->price->minor;

            self::assertEqualsWithDelta($market, $spot, $market * 0.10,
                "{$key}: noghre ye mohasebati kheyli az bazaar dur ast — vahed/martabe ro check kon.");
        }
    }

    /**
     * Har source e gheyr e tgju ba tgju moghayese mishe (tgju bishtarin
     * symbol ro dare). Adad = kamtarin eshterak e entezari.
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public static function sourcesComparedWithTgju(): array
    {
        return [
            'estjt' => ['estjt', 9],
            'nerkh' => ['nerkh', 11],
            // Platform ha faghat 18 ayar midan va bid/ask daran ke ba `last`
            // e tgju kelid e moshtarak nadare — bebin
            // `test_platform_sides_bracket_the_reference_price`.
        ];
    }

    /**
     * Platform haye bid/ask `last` nadaran، pas moghayese ye bala un ha ro
     * nemigire. Har jofte bayad ask >= bid bashe (map e bar-aks = spread e
     * manfi) va har do ta 3% az `last` e tgju fasele dashte bashan.
     *
     * 🔴 Marja' bayad snapshot e tgju e HAMUN LAHZE ye fixture e platform
     * bashe — bazaar tu yek mah 4%+ jabeja mishe (emami 2,753M → 2,635M)
     * va moghayese ba fixture e ghadimi khata ye martabe ro az harekat e
     * bazaar joda nemikone.
     */
    #[DataProvider('twoSidedPlatforms')]
    public function test_platform_sides_bracket_the_reference_price(string $source, string $reference = 'tgju'): void
    {
        $quotes = self::quotes($source);
        $tgju = self::quotes($reference);
        $checked = 0;

        foreach ($quotes as $key => $quote) {
            [$symbol, $side] = explode(':', $key);

            if ($side !== 'ask') {
                continue;
            }

            $ask = $quote->price->minor;
            $bid = $quotes["{$symbol}:bid"]->price->minor ?? null;
            // Melted tu tgju jofte-ast (last nadare) — marja' ask e tgju.
            $reference = $tgju["{$symbol}:last"]->price->minor ?? $tgju["{$symbol}:ask"]->price->minor ?? null;

            self::assertNotNull($bid, "{$source} / {$symbol}: bid nadare.");
            self::assertGreaterThanOrEqual($bid, $ask, "{$source} / {$symbol}: ask bayad >= bid bashe — side ha bar-aks map shodan?");

            if ($reference !== null) {
                self::assertEqualsWithDelta($reference, $ask, $reference * 0.03, "{$source} / {$symbol}: ask dur az tgju.");
                self::assertEqualsWithDelta($reference, $bid, $reference * 0.03, "{$source} / {$symbol}: bid dur az tgju.");
                $checked++;
            }
        }

        self::assertGreaterThan(0, $checked, "{$source}: hich symbol e moshtarak ba tgju nabud.");
    }

    /** @return array<string, array{0: string, 1?: string}> */
    public static function twoSidedPlatforms(): array
    {
        return ['melligold' => ['melligold'], 'wallgold' => ['wallgold'], 'tlyn' => ['tlyn'],
            // Fixture e ecogold 2026-11-02 ast — ba tgju e hamun daghighe.
            'ecogold' => ['ecogold', 'tgju-2026-11-02'],
        ];
    }

    // ── Helper ──────────────────────────────────────────────────

    private static function assertQuote(string $source, string $key, int $minor, QuoteCurrency $currency): void
    {
        $quotes = self::quotes($source);

        self::assertArrayHasKey($key, $quotes, "«{$key}» az fixture e {$source} parse nashod.");

        $quote = $quotes[$key];

        // Meghdar e daghigh — inja jayi-e ke khata ye 10x khodesh ro mide.
        self::assertSame($minor, $quote->price->minor, "{$source} / {$key}: minor e ghalat.");
        self::assertSame($currency, $quote->price->quote, "{$source} / {$key}: arz e ghalat.");

        // Scale bayad az khod e instrument biad، na az Source — vagarna yek
        // adad e USD ba scale 0 zakhire mishe va 10,000 barabar kuchik mishe.
        self::assertSame(
            $quote->instrument->scale(),
            $quote->price->scale(),
            "{$source} / {$key}: scale ba instrument nemikhune.",
        );
    }

    /** @param array<string, array{0: string, 1: int, 2: QuoteCurrency}> $matrix */
    private static function assertMatrixCoversTheSource(string $source, array $matrix): void
    {
        $actual = array_keys(self::quotes($source));
        $expected = array_keys($matrix);

        sort($actual);
        sort($expected);

        self::assertSame(
            $expected,
            $actual,
            "Jadval e {$source} ba chizi ke Source vaghe'an misaze yeki nist. ".
            'Agar map e jadid ezafe kardi، radif esh ro ham be jadval ezafe kon — '.
            'vagarna un symbol SAKET bi-test mimune.',
        );
    }

    /** @return array<string, RawQuoteDto> */
    private static function quotes(string $source): array
    {
        if (isset(self::$cache[$source])) {
            return self::$cache[$source];
        }

        [$adapter, $fixture] = match ($source) {
            'tgju' => [new TgjuQuoteSource, 'tgju-ajax.json'],
            'tgju-2026-11-02' => [new TgjuQuoteSource, 'tgju-ajax-2026-11-02.json'],
            'estjt' => [new EstjtQuoteSource, 'estjt-new-price.json'],
            'nerkh' => [new NerkhQuoteSource, 'nerkh-prices.json'],
            'melligold' => [new MelligoldQuoteSource, 'melligold-buy-sell.json'],
            'wallgold' => [new WallgoldQuoteSource, 'wallgold-price.json'],
            'tlyn' => [new TlynQuoteSource, 'tlyn-price.json'],
            'ecogold' => [new EcogoldQuoteSource, 'ecogold-otc.json'],
            default => self::fail("source e nashenas: {$source}"),
        };

        $json = (string) file_get_contents(__DIR__.'/../../Fixtures/'.$fixture);
        $result = $adapter->parse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));

        $quotes = [];

        foreach ($result->quotes as $quote) {
            $quotes[$quote->instrument->value.':'.$quote->side->value] = $quote;
        }

        return self::$cache[$source] = $quotes;
    }

    /** Har kelid e jadval bayad be yek `Instrument` e vaghei eshare kone. */
    public function test_every_matrix_key_names_a_real_instrument(): void
    {
        foreach ([...array_keys(self::tgjuMatrix()), ...array_keys(self::estjtMatrix()), ...array_keys(self::nerkhMatrix()), ...array_keys(self::melligoldMatrix()), ...array_keys(self::wallgoldMatrix()), ...array_keys(self::tlynMatrix()), ...array_keys(self::ecogoldMatrix())] as $key) {
            $symbol = explode(':', $key)[0];

            self::assertNotNull(
                Instrument::tryFrom($symbol),
                "«{$symbol}» tu enum e Instrument nist.",
            );
        }
    }
}
