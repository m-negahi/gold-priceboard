<?php

declare(strict_types=1);

/*
 * Andaze-giri ye `FetchStage`: hamzaman vs poshte-sar-e-ham.
 *
 * Da'va ye tarahi in bud: «estjt ~7s va tgju ~0.5s ast, pas fetch bayad
 * movazi bashe vagarna tick az 15 saniye rad mikone». In script un ro
 * misanje، na inke farz konim.
 *
 * Ejra: docker compose exec octane php scripts/fetch-benchmark.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Modules\Pricing\Application\Dto\ProviderConfigDto;
use App\Modules\Pricing\Application\Pipeline\Stages\FetchStage;
use App\Modules\Pricing\Application\Pipeline\TickContext;
use App\Modules\Pricing\Domain\ValueObjects\TickTime;
use App\Modules\Pricing\Infrastructure\Sources\EstjtQuoteSource;
use App\Modules\Pricing\Infrastructure\Sources\TgjuQuoteSource;
use Illuminate\Contracts\Console\Kernel;

$tgju = new TgjuQuoteSource([], 25);
$estjt = new EstjtQuoteSource([], 25);

// ── poshte-sar-e-ham ────────────────────────────────────────────
$startedAt = microtime(true);
foreach ([$tgju, $estjt] as $source) {
    try {
        $source->fetch();
    } catch (Throwable $e) {
        echo "  {$source->key()}: shekast — {$e->getMessage()}\n";
    }
}
$sequential = (int) round((microtime(true) - $startedAt) * 1000);

// ── hamzaman (FetchStage) ───────────────────────────────────────
$context = new TickContext(
    at: TickTime::now(),
    sources: [$tgju, $estjt],
    providers: [
        'tgju' => new ProviderConfigDto('tgju'),
        'estjt' => new ProviderConfigDto('estjt'),
    ],
);

$startedAt = microtime(true);
(new FetchStage)->handle($context);
$concurrent = (int) round((microtime(true) - $startedAt) * 1000);

echo "poshte-sar-e-ham : {$sequential} ms\n";
echo "hamzaman         : {$concurrent} ms\n";
printf("sarfe-jooyi      : %d ms (%.0f%%)\n", $sequential - $concurrent,
    $sequential > 0 ? ($sequential - $concurrent) / $sequential * 100 : 0);
echo "\nquote ha: ".count($context->raw)."\n";
foreach ($context->health as $key => $health) {
    printf("  %-8s %-6s %5d ms  %2d symbol  %s\n", $key,
        $health->succeeded ? 'OK' : 'FAIL', $health->latencyMs, $health->symbolCount,
        $health->error ?? '');
}

$budget = (int) config('pricing.tick_seconds', 15) * 1000;
echo "\nbudget e tick: {$budget} ms — ";
echo $concurrent < $budget ? "hamzaman ja mishe\n" : "RAD MIKONE\n";
echo $sequential < $budget ? "poshte-sar-e-ham ham ja mishe\n" : "poshte-sar-e-ham RAD MIKONE\n";
