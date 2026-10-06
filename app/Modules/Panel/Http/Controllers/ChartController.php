<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Pricing\Application\Dto\CandleDto;
use App\Modules\Pricing\Application\Queries\ChartQuery;
use App\Modules\Pricing\Application\Queries\SymbolPriorityQuery;
use App\Modules\Pricing\Domain\Enums\ChartPeriod;
use App\Modules\Pricing\Domain\Enums\Instrument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Chart e panel e admin (P5-08) — nazok، faghat Query.
 *
 * Har param az ENUM rad mishe (hamun allowlist e chart e omumi) — hich
 * reshte ye kham be Flux nemirese. Bar khalaf e `/candles.json` e omumi،
 * cache e public NADARE (pasokh e posht e auth).
 */
final readonly class ChartController
{
    public function __construct(
        private ChartQuery $chart,
        private SymbolPriorityQuery $priorities,
    ) {}

    public function show(Request $request): View
    {
        $symbol = Instrument::tryFrom((string) $request->query('symbol', '')) ?? Instrument::Gold18Gram;
        $names = $this->priorities->providerNames();

        return view('panel::charts.index', [
            'symbol' => $symbol->value,
            'instruments' => Instrument::labels(),
            'providers' => $names,
            'periods' => ChartPeriod::labels(),
            // Daghighat e namayesh e har symbol (dollar 2 raghame ashar، rial 0).
            'precisions' => array_combine(
                array_map(static fn (Instrument $i): string => $i->value, Instrument::cases()),
                array_map(static fn (Instrument $i): int => $i->quote()->displayPrecision(), Instrument::cases()),
            ),
            'period' => ChartPeriod::default()->value,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $instrument = Instrument::tryFrom((string) $request->query('symbol', ''));
        $period = ChartPeriod::tryFrom((string) $request->query('period', '')) ?? ChartPeriod::default();

        if ($instrument === null) {
            return new JsonResponse(['message' => 'نماد نامعتبر است.'], 422);
        }

        $result = $this->chart->series($instrument, (string) $request->query('provider', ''), $period);

        return new JsonResponse([
            'data' => array_map(static fn (CandleDto $c): array => $c->toArray(), $result['candles']),
            'symbol' => $instrument->value,
            'label' => $instrument->label(),
            'period' => $period->value,
            'provider' => $result['provider'],
            'precision' => $instrument->quote()->displayPrecision(),
            'displayDivisor' => $instrument->quote()->displayDivisor(),
            'degraded' => $result['degraded'],
        ]);
    }
}
