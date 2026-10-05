<?php

declare(strict_types=1);

/* Tablo ye ZENDE az masir e vaghei (Redis -> fallback e Postgres). */

use App\Modules\Pricing\Application\Queries\BoardQuery;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$board = $app->make(BoardQuery::class)->board($app->make(ProviderQuery::class)->allActive());

printf(
    "radif: %d   version: %d   spreadVersion: %d   degraded: %s\n",
    count($board->rows),
    $board->version,
    $board->spreadVersion,
    $board->degraded ? 'bale' : 'na',
);
echo "ETag: {$board->etag()}\n\n";

printf("%-22s %-7s %16s %16s %7s %6s %s\n", 'symbol', 'provider', 'kharid', 'foroosh', 'sen', 'bayat', 'moshtagh');
foreach ($board->rows as $row) {
    printf(
        "%-22s %-7s %16s %16s %6ss %6s %s\n",
        $row->instrument->value,
        $row->providerKey,
        number_format($row->buy->minor),
        number_format($row->sell->minor),
        $row->ageSeconds,
        $row->isStale ? 'BALE' : '-',
        $row->isDerived ? '*' : '-',
    );
}

echo "\njadval e moghayese (radif = symbol):\n";
foreach ($board->bySymbol() as $symbol => $rows) {
    $prices = array_map(
        static fn ($r): string => $r->providerKey.'='.number_format($r->sell->minor),
        $rows,
    );
    printf("  %-22s %s\n", $symbol, implode('  ', $prices));
}
