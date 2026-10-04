<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Services;

use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\SpreadMode;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Domain\ValueObjects\Spread;
use App\Modules\Pricing\Domain\ValueObjects\SpreadPair;

/**
 * E'mal e spread ru gheymat e kham. Static, bi-halat, bedun e hich IO.
 *
 * ── 🔴 Spread ru READ e'mal mishe, hargez ru WRITE ───────────────────
 * DB va Influx hamishe meghdar e KHAM ro negah midaran. Dalil ha, be tartib
 * e ghodrat:
 *
 * 1. Spread e bake-shode yek PELE ye ja'li tu chart mizare. Admin sa'at 14:00
 *    spread ro az +0.5% be +0.8% mibare -> candle haye ghabl ba spread e
 *    ghadim va ba'd ba jadid -> yek discontinuity e 0.3% ke harekat e gheymat
 *    NIST. Kol e ma'ni ye chart e teknikal in ast ke shekl esh ma'ni dare;
 *    MA/RSI/Bollinger un pele ro signal e vaghei mikhunan.
 * 2. Tarikhche ye Influx ghabl e re-spread nist — va ba'd az dur rikhtan e
 *    meghdar e kham, hich rah e durosti nadare.
 * 3. Meghdar e kham tanha vaghe'iyat e ghabl e audit ast. Yek `+50` ke
 *    eshtebah tu field e `percent` type shode mishe rollback e config, na
 *    hadese ye az-dast-raftan e data.
 * 4. Hazine ye read sefr ast.
 *
 * ── Se invariant (shekastaneshun asun ast, pas benevis) ──────────────
 * A) `changePercent` va `direction` HARGEZ tanzim nemishan. Spread e darsadi
 *    ru darsad e taghir riyazi-an neutral ast, va spread e meghdari be close
 *    e ghabli niaz dare ke tu payload nist.
 * B) `dayHigh`/`dayLow` ba delta ye SELL shift mishan va ba'd clamp mishan,
 *    ta gheymat e zende hargez az band birun nazane.
 * C) Spread-i ke gheymat ro be <= 0 bebare -> fallback be KHAM, na sefr. Yek
 *    tanzim e kharab nabayad service ro bokoshe ya be moshtari sefr bede.
 */
final readonly class SpreadPricer
{
    /**
     * E'mal e yek spread ru yek gheymat.
     *
     * `percent` -> darsad e hamun samt e gheymat e kham
     * `amount`  -> meghdar e sabet tu vahed e ASLI ye arz (be minor tabdil mishe)
     */
    public static function apply(Price $raw, Spread $spread): Price
    {
        if ($spread->isNeutral() || ! $raw->isPositive()) {
            return $raw;
        }

        $delta = $spread->mode === SpreadMode::Amount
            ? (int) round($spread->value * (10 ** $raw->scale()))
            : (int) round($raw->minor * $spread->value / 100);

        $adjusted = new Price($raw->minor + $delta, $raw->quote);

        // Invariant C — spread e kharab nabayad gheymat ro sefr/manfi kone.
        return $adjusted->isPositive() ? $adjusted : $raw;
    }

    /**
     * Sakhtan e jofte kharid/foroosh az yek gheymat e kham.
     *
     * ⚠️ Khoruji e in method MOSHTAGH ast, na gheymat e vaghei ye un maghaze.
     * Har ja neshun dade mishe bayad ba flag e `isDerived` biad (be joz
     * instrument hayi ke `hasNativeSides()` daran).
     *
     * @return array{buy: Price, sell: Price}
     */
    public static function applyPair(Price $raw, SpreadPair $spread): array
    {
        return [
            'buy' => self::apply($raw, $spread->buy),
            'sell' => self::apply($raw, $spread->sell),
        ];
    }

    /**
     * Shift e band e rooz hamrah e gheymat.
     *
     * Invariant B: har do ba delta ye SELL shift mishan (na har kodum ba
     * spread e khodeshun), ba'd clamp mishan ta `buy` va `sell` e zende
     * hatman dakhel e `[low, high]` bemunan. Bedun e clamp, gheymat e zende
     * mitune az band birun bezane va tu UI «بیشترین امروز» kamtar az gheymat e
     * fe'li neshun dade she.
     *
     * @return array{high: ?Price, low: ?Price}
     */
    public static function applyDayBand(
        ?Price $high,
        ?Price $low,
        Price $raw,
        Price $buy,
        Price $sell,
    ): array {
        $sellDelta = $sell->minor - $raw->minor;

        if ($high !== null) {
            $shifted = new Price($high->minor + $sellDelta, $high->quote);
            $high = Price::max($shifted, $sell);
        }

        if ($low !== null) {
            $shifted = new Price($low->minor + $sellDelta, $low->quote);
            $low = Price::min($shifted, $buy);
        }

        return ['high' => $high, 'low' => $low];
    }

    /**
     * E'mal e spread ru yek series e candle baraye chart.
     *
     * 🔴 Chart BAYAD hamun spread e board ro begire — vagarna `close` e
     * akharin candle ba gheymat e board fargh mikone va har karbar un ro bug
     * gozaresh mikone.
     *
     * Ru har chahar ta ye OHLC e'mal mishe. Har do mode monotone-an, pas
     * tartib e `high >= low` khod-be-khod hefz mishe: `percent` shekl e candle
     * ro eyn negah midare, `amount` faghat shift esh mide.
     *
     * ⚠️ Array e taze misaze, na in-place — vorudi mitune hamun entry e cache
     * bashe va dastkari esh spread e yek moshtari ro be moshtari ye ba'di
     * nasht mide.
     *
     * @param  array<int, array{timestamp: int, open: float, high: float, low: float, close: float, volume?: float}>  $candles
     * @return array<int, array{timestamp: int, open: float, high: float, low: float, close: float, volume?: float}>
     */
    public static function applySeries(array $candles, Spread $spread, QuoteCurrency $quote): array
    {
        if ($spread->isNeutral() || $candles === []) {
            return $candles;
        }

        // Candle ha az Influx be sorat e MAJOR (float) miyan, chun chart ba
        // float kar mikone. Baraye e'mal e spread be minor mibarim ta hamun
        // mantegh e `apply()` ejra she — makhsusan mode e `amount` ke be scale
        // e arz vabaste ast.
        $factor = 10 ** $quote->scale();

        return array_map(
            static function (array $candle) use ($spread, $quote, $factor): array {
                foreach (['open', 'high', 'low', 'close'] as $field) {
                    $minor = (int) round(((float) $candle[$field]) * $factor);

                    // ⚠️ cast e sarih e `(float)` lazem ast: tu PHP `int / int`
                    // vaghti baghi-munde nadare INT bar migardune. Baraye IRR ke
                    // `$factor === 1` ast, bedun e cast kol e series int mishe va
                    // JSON e chart baraye IRR int va baraye USD float mide —
                    // KLineCharts va har masraf-konande ye digar bayad yek shekl
                    // bebinan.
                    $candle[$field] = (float) (self::apply(new Price($minor, $quote), $spread)->minor / $factor);
                }

                return $candle;
            },
            $candles,
        );
    }
}
