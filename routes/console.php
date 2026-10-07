<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;
use Laravel\Telescope\Telescope;

/*
 * ⚠️ `pricing:tick` AMDAN inja NIST.
 *
 * Un tu container e jodagane ye `price-worker` ba yek halghe ye
 * grid-aligned ejra mishe:
 *   - har tick yek process e taze  -> bedun e memory leak
 *   - tartibi                       -> bedun e overlap
 *   - ru grid e 15 saniye           -> timestamp e floor-shode = idempotency
 * Scheduler e Laravel kamtarin daghighat esh yek DAGHIGHE ast، pas aslan
 * nemitune tick e 15 saniye-i bede.
 */

// Replay e tick hayi ke vaghte ghat'i ye Influx spool shodan.
// Khodesh candle haye un bazeh ro ham dobare misaze — task e `candles_1m`
// lookback e faghat `-3m` dare va un tick haye ghadimi ro hargez nemibine.
Schedule::command('pricing:influx:drain')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('pricing-influx-drain');

// Task e Influx mitune SAKET bemire (type mismatch، OOM، taghir e nasakhe).
// Bedun e in check، avvalin kasi ke mifahme yek karbar ast — hafte ha ba'd.
Schedule::command('pricing:influx:health')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('pricing-influx-health');

// Refund haye API ke moghe ye ghat'i ye PG tu saf e Redis mundan — replay
// (idempotent ba reference). Bedun e in، pul e customer gom mishod.
Schedule::command('api:refunds:drain')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('api-refunds-drain');

// Usage e paydar e API (P6-08): Redis (calls) + ledger (hazine) ->
// `api_usage_daily`. Idempotent؛ emruz + diruz e Tehran.
Schedule::command('api:usage:rollup')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->name('api-usage-rollup');

// Metrik e queue faghat ba snapshot por mishe.
Schedule::command('horizon:snapshot')->everyFiveMinutes()->withoutOverlapping();

if (class_exists(Telescope::class) && config('telescope.enabled')) {
    Schedule::command('telescope:prune --hours=48')->daily()->withoutOverlapping();
}
