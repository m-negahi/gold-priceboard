<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Services;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Domain\ValueObjects\SanityVerdict;

/**
 * Darvaze ye gheymat — ghabl az inke chizi be Redis/Postgres/Influx berese.
 *
 * ── Chera inja va na ba'dan ──────────────────────────────────────────
 * Yek parse e kharab ke be `candles_1d` berese yek DORUGH e DAEMI ye ghabl e
 * royat ast: pak kardanesh `delete` e predicate-i ru Influx + cascade e
 * re-run e hame ye task ha mikhahe. Rad kardan tu darvaze 20 khat ast.
 *
 * Do check e KAMELAN mostaghel:
 *
 * 1. BAND E MOTLAGH (`Instrument::sanityBand()`)
 *    migire: khata ye 10x e toman/rial, soton e eshtebah e HTML, flip e arz.
 *    Band ha amdan pahn-an — hadaf gereftan e khata ye martabe-i ast, na
 *    modiriat e bazaar.
 *
 * 2. ENHERAF E TICK-BE-TICK
 *    migire: parse e kharab-i ke ettefaghan dakhel e band oftade.
 *
 * ── ⚠️ Tale ye deviation ─────────────────────────────────────────────
 * Yek gap e VAGHEI (devaluation, khabar e siasi) mitune 12% bezane va in
 * guard un ro rad mikone — ya'ni daghighan vaghti bazaar jaleb mishe, ma
 * gheymat ro nemineveshtim.
 * Hall: «do outlier e mote'ali vaghei-an, yeki parse error ast» —
 * avvalin breach rad mishe va meghdar be onvan e `pendingOutlier` negah
 * dashte mishe; agar tick e BA'DI hamun hodud ro ta'eed kone, paziresh mishe.
 * In class bi-halat ast; negah dashtan e `pendingOutlier` kar e laye ye
 * Application (snapshot/cache) ast.
 *
 * ── 3. CROSS-PROVIDER (faghat AVVALIN tick) — `P1-24` ───────────────
 * Do check e bala yek RAKHNE daran ke ru ham ham pushesh esh nemidan:
 *
 *   - Band e motlagh khata ye 10x ro **NEMITUNE** begire. Rial onghadr sari
 *     tavarrom dare (tala ~70 barabar tu 8 sal) ke har band e chand-sale az
 *     yek martabe ham pahn tar ast. (Tozih e kamel tu
 *     `Instrument::sanityBand()`.)
 *   - Enheraf e tick-be-tick be `previous` niaz dare — va AVVALIN tick e yek
 *     (provider, symbol) hich `previous` nadare.
 *
 * Natije: yek provider e TAZE ba vahed e eshtebah (toman jaye rial) mitune
 * avvalin gheymat esh ro bedun e hich moghe'iat benevise. Va chun tick haye
 * ba'di ba hamun meghdar e ghalat SAZEGAR-and، check e enheraf hargez
 * roshan nemishe: yek dorugh e DAEMI.
 *
 * Hall: in yek site e MOGHAYESE ast — gheymat e provider haye DIGAR baraye
 * hamun symbol ro darim. Agar avvalin gheymat az un ha kheyli dur bashe،
 * rad mishe.
 */
final readonly class PriceSanity
{
    /** Chand darsad ekhtelaf beyn e outlier e moallagh va tick e ba'di hanuz «ta'eed» hesab mishe. */
    private const CONFIRMATION_TOLERANCE_PCT = 2.0;

    /**
     * Saghf e ekhtelaf ba provider haye DIGAR، baraye avvalin tick.
     *
     * ── Chera 20% va na 10% ─────────────────────────────────────────
     * Provider ha VAGHE'AN ba ham farg daran: estjt (etehadiye ye Tehran) va tgju
     * (Tehran) do bazaar e motefavet-and va har maghaze nerkh e khodesh ro
     * dare. Andaze-giri ye zende: ons 0.27% ekhtelaf، tala ye 18 ayar
     * 0.26%.
     *
     * Chizi ke in check bayad begire az yek martabe bozorgtar ast:
     *   toman jaye rial -> 90% ekhtelaf
     *   khata ye 10x    -> 900% ekhtelaf
     *   soton e eshtebah -> ma'mulan chand barabar
     *
     * 20% onghadr pahn ast ke hich ekhtelaf e VAGHEI ye bazaar ro rad
     * nakone، va onghadr tang ast ke har khata ye vahed/martabe ro begire.
     */
    public const MAX_CROSS_PROVIDER_DEVIATION_PCT = 20.0;

    public static function check(
        Instrument $instrument,
        Price $price,
        ?Price $previous,
        ?Price $pendingOutlier,
        float $maxDeviationPct,
    ): SanityVerdict {
        if (! $price->isPositive()) {
            return SanityVerdict::reject('non_positive');
        }

        [$min, $max] = $instrument->sanityBand();

        if ($price->minor < $min || $price->minor > $max) {
            return SanityVerdict::reject("out_of_band:{$price->minor}");
        }

        // Tick e avval — `previous` nist، pas check e enheraf nemitune ejra
        // beshe. Inja `crossProvider()` masuliat ro migire (az
        // `ValidateStage` seda mishe).
        if ($previous === null || ! $previous->isPositive()) {
            return SanityVerdict::accept();
        }

        $deviation = abs($price->minor - $previous->minor) / $previous->minor * 100;

        if ($deviation <= $maxDeviationPct) {
            return SanityVerdict::accept();
        }

        // Az saghf gozasht. Agar tick e ghabli ham hamin hodud ro gofte bud,
        // ya'ni harekat VAGHEI ast na parse error.
        if ($pendingOutlier !== null && $pendingOutlier->isPositive()) {
            $gap = abs($price->minor - $pendingOutlier->minor) / $pendingOutlier->minor * 100;

            if ($gap <= self::CONFIRMATION_TOLERANCE_PCT) {
                return SanityVerdict::accept(confirmedOutlier: true);
            }
        }

        return SanityVerdict::reject(
            sprintf('deviation:%.2f%%', $deviation),
            holdAsOutlier: true,
        );
    }

    /**
     * Check e AVVALIN tick ba gheymat e provider haye DIGAR.
     *
     * Faghat vaghti seda mishe ke `previous` nabashe — ya'ni in avvalin bar
     * ast ke in (provider, symbol, side) gheymat mide.
     *
     * ── ⚠️ Mahdudiat e sadeghane ──────────────────────────────────
     * Agar hich provider e digari un symbol ro nade، in check **hich kari
     * nemitune bokone** va ghabul mikone. Un halat e bootstrap ast: avvalin
     * ejra ye system ke hanuz hich gheymati zakhire nashode. Be hamin
     * dalil seeder provider ha ro `is_active=false` shoru' mikone va
     * ravesh e kar (skill e `add-price-provider`) mige provider ha ro
     * YEKI-YEKI roshan kon va `TickMonitor` ro check kon — un vaght baraye
     * provider e DOVOM be ba'd، in check vaghe'an kar mikone.
     *
     * ── Chera `holdAsOutlier` nemide ──────────────────────────────
     * Ghanun e «do outlier» be yek snapshot e ghabli niaz dare ke un ro
     * negah dare — va daghighan hamun chizi-e ke inja nadarim. Alave bar
     * un، ekhtelaf e >20% ba baghi ye bazaar yek ekhtelaf e VAGHEI ye
     * bazaar nist، yek eshtebah e CONFIG ast (vahed، map e symbol، soton).
     * Un bayad tavasot e admin DOROST she، na ba sabr kardan.
     *
     * @param  array<int, Price>  $references  gheymat e provider haye digar baraye HAMUN instrument
     */
    public static function crossProvider(
        Price $price,
        array $references,
        float $maxDeviationPct = self::MAX_CROSS_PROVIDER_DEVIATION_PCT,
    ): SanityVerdict {
        $median = self::median($references);

        if ($median === null) {
            // Hich marja'-i nist — halat e bootstrap.
            return SanityVerdict::accept();
        }

        $deviation = abs($price->minor - $median) / $median * 100;

        if ($deviation <= $maxDeviationPct) {
            return SanityVerdict::accept();
        }

        return SanityVerdict::reject(
            sprintf('cross_provider:%.2f%%', $deviation),
        );
    }

    /**
     * Miane ye marja' ha — na miangin.
     *
     * 🔴 Miangin ba yek provider e kharab kharab mishe: agar yeki az se
     * marja' 10 barabar bashe، miangin ~4 barabar mishe va un vaght yek
     * gheymat e DOROST ro rad mikonim. Miane ba yek outlier tekun nemikhore.
     *
     * @param  array<int, Price>  $references
     */
    private static function median(array $references): ?float
    {
        $values = [];

        foreach ($references as $reference) {
            if ($reference->isPositive()) {
                $values[] = $reference->minor;
            }
        }

        if ($values === []) {
            return null;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? (float) $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
