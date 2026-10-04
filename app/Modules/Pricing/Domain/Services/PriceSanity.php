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
 */
final readonly class PriceSanity
{
    /** Chand darsad ekhtelaf beyn e outlier e moallagh va tick e ba'di hanuz «ta'eed» hesab mishe. */
    private const CONFIRMATION_TOLERANCE_PCT = 2.0;

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

        // Tick e avval — chizi baraye moghayese nist.
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
}
