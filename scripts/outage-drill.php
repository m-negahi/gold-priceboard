<?php

declare(strict_types=1);

/*
 * Tamrin e ghat'i: tick haye GHADIMI tu spool mirizim (shabih e inke Influx
 * 20 daghighe ghat' bude), ba'd drain mikonim va check mikonim ke candle ha
 * ham sakhte shodan — na faghat tick e kham.
 *
 * Bedun e `rebuildMinuteCandles`, task e `candles_1m` (lookback -3m) in
 * tick ha ro HARGEZ nemibine va `m5`-`d1` yek surakh e daemi peyda mikonan.
 *
 * Ejra: docker compose exec octane php scripts/outage-drill.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxSpool;

$spool = new InfluxSpool(40_000);
$spool->clear();

$base = intdiv(time() - 1_200, 60) * 60;
$lines = [];

for ($m = 0; $m < 3; $m++) {
    for ($s = 0; $s < 60; $s += 15) {
        $timestamp = $base + $m * 60 + $s;
        $value = 300_000_000 + $m * 1_000_000 + $s * 1_000;
        $lines[] = "price,provider=outage,symbol=xau-18k-g-irr,side=last,quote=irr value={$value},lag=0 {$timestamp}";
    }
}

$spool->push($lines);

printf(
    "spool: %d khat, az %s UTC (%d daghighe pish)\n",
    $spool->size(),
    gmdate('H:i:s', $base),
    (int) ((time() - $base) / 60),
);
printf("bazeh: %s .. %s\n", gmdate('H:i:s', $base), gmdate('H:i:s', $base + 165));
