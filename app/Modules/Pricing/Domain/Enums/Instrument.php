<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Symbol haye canonical e tablo.
 *
 * Ghaleb: `{metal}-{form}[-{purity|size}]-{quote}` — kebab e lowercase.
 * URL-safe ast (bedun e encode tu path/query), ba metal sort mishe, va kod haye
 * felez standard e jahani-an (ISO 4217: `X` + element) — XAU/XAG/XCU.
 *
 * Tartib e case ha = tartib e namayesh tu tablo.
 *
 * Har symbol **mostaghel az provider** ast: hamin `xau-18k-g-irr` ro ham tgju
 * mide ham estjt, va daghighan hamin chizi-e ke moghayese ro mumken mikone.
 * Map kardan e kelid e upstream be in symbol kar e har `QuoteSource` ast.
 */
enum Instrument: string
{
    use EnumTrait;

    // ── Tala, IRR ────────────────────────────────────────────────
    case Gold18Gram = 'xau-18k-g-irr';
    case Gold24Gram = 'xau-24k-g-irr';
    case GoldMeltedGram = 'xau-melted-g-irr';
    case GoldMesghal = 'xau-mesghal-irr';
    case CoinEmami = 'xau-coin-emami-irr';
    case CoinBahar = 'xau-coin-bahar-irr';
    case CoinHalf = 'xau-coin-half-irr';
    case CoinQuarter = 'xau-coin-quarter-irr';
    case CoinGerami = 'xau-coin-gerami-irr';

    // ── Tala, USD ────────────────────────────────────────────────
    case GoldOunce = 'xau-oz-usd';

    // ── Noghre ───────────────────────────────────────────────────
    case Silver999Gram = 'xag-999-g-irr';
    case Silver925Gram = 'xag-925-g-irr';
    case SilverOunce = 'xag-oz-usd';

    // ── Mes ──────────────────────────────────────────────────────
    case CopperPound = 'xcu-lb-usd';

    // ── Arz ──────────────────────────────────────────────────────
    case UsdIrr = 'usd-irr';

    /** Instrument e paye baraye chart va mohasebe. */
    public static function base(): self
    {
        return self::Gold18Gram;
    }

    public function metal(): ?Metal
    {
        return match ($this) {
            self::Gold18Gram, self::Gold24Gram, self::GoldMeltedGram, self::GoldMesghal,
            self::CoinEmami, self::CoinBahar, self::CoinHalf, self::CoinQuarter,
            self::CoinGerami, self::GoldOunce => Metal::Gold,

            self::Silver999Gram, self::Silver925Gram, self::SilverOunce => Metal::Silver,

            self::CopperPound => Metal::Copper,

            self::UsdIrr => null,
        };
    }

    public function unit(): Unit
    {
        return match ($this) {
            self::Gold18Gram, self::Gold24Gram, self::GoldMeltedGram,
            self::Silver999Gram, self::Silver925Gram => Unit::Gram,

            self::GoldMesghal, self::CoinEmami, self::CoinBahar,
            self::CoinHalf, self::CoinQuarter, self::CoinGerami => Unit::Piece,

            self::GoldOunce, self::SilverOunce => Unit::Ounce,

            self::CopperPound => Unit::Pound,

            self::UsdIrr => Unit::Currency,
        };
    }

    public function quote(): QuoteCurrency
    {
        return match ($this) {
            self::GoldOunce, self::SilverOunce, self::CopperPound => QuoteCurrency::USD,
            default => QuoteCurrency::IRR,
        };
    }

    /** Miyan-bor — `scale` hamishe az arz miad. */
    public function scale(): int
    {
        return $this->quote()->scale();
    }

    /** Ayar (az 1000). `null` ya'ni ma'ni nadare (arz). */
    public function purity(): ?int
    {
        return match ($this) {
            self::Gold18Gram, self::CoinEmami, self::CoinBahar, self::CoinHalf,
            self::CoinQuarter, self::CoinGerami => 750,
            self::Gold24Gram, self::GoldOunce, self::GoldMeltedGram => 999,
            self::GoldMesghal => 705,
            self::Silver999Gram, self::SilverOunce => 999,
            self::Silver925Gram => 925,
            self::CopperPound => 999,
            self::UsdIrr => null,
        };
    }

    /**
     * Vazn be geram. String ast na float — in adad ha tu mohasebe ye gheymat
     * be kar miran va bayad ba bcmath ejra shan, na ba float.
     */
    public function weightGrams(): ?string
    {
        return match ($this) {
            self::Gold18Gram, self::Gold24Gram, self::GoldMeltedGram,
            self::Silver999Gram, self::Silver925Gram => '1',
            self::GoldMesghal => '4.6083',
            self::CoinEmami, self::CoinBahar => '8.133',
            self::CoinHalf => '4.0665',
            self::CoinQuarter => '2.0332',
            self::CoinGerami => '1',
            self::GoldOunce, self::SilverOunce => '31.1035',
            self::CopperPound => '453.592',
            self::UsdIrr => null,
        };
    }

    public function group(): string
    {
        return $this->metal()?->value ?? 'fx';
    }

    /**
     * Chand saniye ba'd az `upstream_at`, gheymat BAYAT hesab mishe.
     *
     * 🔴 Chera per-instrument va na yek adad e global:
     * tgju 963 symbol mide ke faghat ~389 tash taze-an (`zinc` az 2021,
     * `geram18_buy` az 2018). Yek threshold e global ya un ashghal ha ro rah
     * mide, ya instrument haye kond e vaghei ro mindaze.
     *   - dollar va ons taghriban 24/5 moamele mishan  -> saft (180s)
     *   - tala/sekke faghat sa'at e bazaar              -> motevaset (900s)
     *   - noghre 925 mitune yek sa'at nakhore           -> shol (1800s)
     */
    public function freshnessSeconds(): int
    {
        return match ($this) {
            self::UsdIrr, self::GoldOunce, self::SilverOunce, self::CopperPound => 180,
            self::Silver925Gram, self::Silver999Gram => 1800,
            default => 900,
        };
    }

    /**
     * Band e plausibility baraye `PriceSanity` — `[min, max]` tu MINOR unit.
     *
     * ── 🔴 In band khata ye 10x ro NEMIGIRE. Amdan. ──────────────────
     * Rial sari tavarrom dare: tala ye 18 ayar az ~3.8M rial (2018) be ~266M
     * (2026) raside — ~70 barabar tu 8 sal. Har band-i ke onghadr pahn bashe
     * ke chand sal davam biare, az yek martabe (10x) ham pahn tar ast. Pas
     * sakhtan e band-i ke ham 10x ro begire ham ta'mir nakhahe, momken NIST.
     *
     * Taghsim e kar e vaghei:
     *   - IN BAND         -> faghat tore ye «mozakhraf»: flip e arz, soton e
     *                        eshtebah e HTML, meghdar e manfi/sefr, matn e
     *                        parse-shode be onvan e adad.
     *   - ENHERAF E TICK  -> 10x ro migire. Yek khata ye 10x ya'ni 90% ya 900%
     *                        enheraf, ke az har saghf e manteghi kheyli balatar
     *                        ast. In mohafez e ASLI ye runtime ast.
     *   - TEST E FIXTURE  -> khata ye 10x e SYSTEMATIK (masalan Source-i ke
     *                        farāmush karde Toman ro x10 kone) tu `tests/` ba
     *                        meghdar e daghigh gerefte mishe, na tu runtime.
     *
     * ⚠️ Rakhne ye baghi-munde: AVVALIN tick e yek (provider, symbol) e jadid
     * ke hanuz `previous` nadare. Unja enheraf nemitune komak kone va faghat in
     * band e pahn hast. Hall esh moghayese ba provider haye DIGAR ast baraye
     * hamun symbol (task `P1-24`) — chun in yek site e moghayese ast, in data
     * ro ghablan darim.
     *
     * @return array{0: int, 1: int}
     */
    public function sanityBand(): array
    {
        return match ($this) {
            // rial, har geram
            self::Gold18Gram => [20_000_000, 5_000_000_000],
            self::Gold24Gram => [25_000_000, 6_000_000_000],
            self::GoldMeltedGram => [20_000_000, 5_000_000_000],

            // rial, har ghet'e
            self::GoldMesghal => [100_000_000, 20_000_000_000],
            self::CoinEmami, self::CoinBahar => [200_000_000, 40_000_000_000],
            self::CoinHalf => [100_000_000, 20_000_000_000],
            self::CoinQuarter => [50_000_000, 10_000_000_000],
            self::CoinGerami => [20_000_000, 5_000_000_000],

            // rial, noghre har geram
            self::Silver999Gram, self::Silver925Gram => [500_000, 100_000_000],

            // rial, dollar
            self::UsdIrr => [100_000, 100_000_000],

            // USD ba scale 4 -> minor = dollar x 10_000
            self::GoldOunce => [5_000_000, 1_000_000_000],      // $500 .. $100k
            self::SilverOunce => [50_000, 50_000_000],          // $5 .. $5k
            self::CopperPound => [1_000, 10_000_000],           // $0.1 .. $1k
        };
    }

    /**
     * Aya upstream baraye in instrument jofte bid/ask e VAGHEI mide?
     *
     * Faghat `xau-melted-g-irr` (abshode ye naghdi) — tgju un ro ba do kelid e
     * `tgju_gold_irg18` / `tgju_gold_irg18_buy` mide ke har do zende-an.
     * Baghi ye instrument ha yek gheymat daran va kharid/foroosh eshun az spread
     * sakhte mishe -> bayad ba `is_derived` neshun dade shan.
     */
    public function hasNativeSides(): bool
    {
        return $this === self::GoldMeltedGram;
    }

    public function icon(): string
    {
        return match ($this) {
            self::UsdIrr => 'currency-dollar',
            self::CoinEmami, self::CoinBahar, self::CoinHalf,
            self::CoinQuarter, self::CoinGerami => 'coin',
            self::GoldOunce, self::SilverOunce, self::CopperPound => 'world',
            default => $this->metal()?->icon() ?? 'circle',
        };
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Gold18Gram->value => 'طلای ۱۸ عیار',
            self::Gold24Gram->value => 'طلای ۲۴ عیار',
            self::GoldMeltedGram->value => 'آبشده نقدی',
            self::GoldMesghal->value => 'مثقال طلا',
            self::CoinEmami->value => 'سکه امامی',
            self::CoinBahar->value => 'سکه بهار آزادی',
            self::CoinHalf->value => 'نیم سکه',
            self::CoinQuarter->value => 'ربع سکه',
            self::CoinGerami->value => 'سکه گرمی',
            self::GoldOunce->value => 'انس طلا',
            self::Silver999Gram->value => 'نقره ۹۹۹',
            self::Silver925Gram->value => 'نقره ۹۲۵',
            self::SilverOunce->value => 'انس نقره',
            self::CopperPound->value => 'مس',
            self::UsdIrr->value => 'دلار آمریکا',
        ];
    }

    /**
     * Baraye select haye form — hame ye instrument ha ba meta.
     *
     * @return array<int, array{value: string, label: string, unit: string, quote: string, group: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $i): array => [
                'value' => $i->value,
                'label' => $i->label(),
                'unit' => $i->unit()->value,
                'quote' => $i->quote()->value,
                'group' => $i->group(),
                'icon' => $i->icon(),
            ],
            self::cases(),
        );
    }
}
