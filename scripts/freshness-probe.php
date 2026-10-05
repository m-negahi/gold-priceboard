<?php

declare(strict_types=1);

/* Chera symbol ha stale-and? — negah be `ts` e vaghei ye tgju. */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Modules\Pricing\Infrastructure\Sources\TgjuQuoteSource;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

$now = CarbonImmutable::now('UTC');
echo 'alan: '.$now->toIso8601String().' UTC  =  '.$now->setTimezone('Asia/Tehran')->format('Y-m-d H:i:s')." Tehran\n";
echo 'rooz e hafte: '.$now->setTimezone('Asia/Tehran')->format('l')."\n\n";

$result = (new TgjuQuoteSource([], 25))->fetch();

printf("%-24s %-22s %8s  %s\n", 'symbol', 'upstream (Tehran)', 'lag', 'saghf');
foreach ($result->quotes as $quote) {
    $lag = $quote->upstreamAt === null ? null : $now->getTimestamp() - $quote->upstreamAt->getTimestamp();
    $limit = $quote->instrument->freshnessSeconds();

    printf(
        "%-24s %-22s %7ss  %5ss  %s\n",
        $quote->instrument->value,
        $quote->upstreamAt?->setTimezone('Asia/Tehran')->format('Y-m-d H:i:s') ?? '—',
        $lag ?? '—',
        $limit,
        $lag !== null && $lag > $limit ? 'STALE' : 'ok',
    );
}
