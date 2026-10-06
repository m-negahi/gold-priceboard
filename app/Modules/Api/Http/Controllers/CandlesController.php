<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers;

use App\Modules\Pricing\Application\Dto\CandleDto;
use App\Modules\Pricing\Application\Dto\CandleRequestDto;
use App\Modules\Pricing\Application\Queries\CandleQuery;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Interval;
use App\Modules\Pricing\Domain\Enums\Side;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Candle ha (P4-06). Har parameter az ENUM rad mishe — hich reshte ye
 * kham be Flux nemirese (hamun allowlist e `Flux::tag`).
 */
final readonly class CandlesController
{
    public function __construct(
        private CandleQuery $candles,
        private ProviderQuery $providers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $instrument = Instrument::tryFrom((string) $request->query('symbol', ''));
        $interval = Interval::tryFrom((string) $request->query('interval', ''));

        if ($instrument === null || $interval === null) {
            return new JsonResponse(['message' => 'نماد یا بازه نامعتبر است.'], 422);
        }

        $providerKey = (string) $request->query('provider', '');
        $configs = $this->providers->allActive();

        if (! array_key_exists($providerKey, $configs)) {
            return new JsonResponse(['message' => 'منبع ناشناخته است.'], 422);
        }

        $side = Side::tryFrom((string) $request->query('side', 'last')) ?? Side::Last;

        $to = (int) ($request->query('to') ?? time());
        $from = (int) ($request->query('from') ?? ($to - 7 * 86_400));

        if ($from <= 0 || $to <= 0 || $from >= $to) {
            return new JsonResponse(['message' => 'بازه زمانی نامعتبر است.'], 422);
        }

        $series = $this->candles->candles(CandleRequestDto::make(
            providerKey: $providerKey,
            instrument: $instrument,
            interval: $interval,
            from: $from,
            to: $to,
            side: $side,
            limit: (int) ($request->query('limit') ?? CandleRequestDto::MAX_LIMIT),
        ));

        return new JsonResponse([
            'data' => array_map(static fn (CandleDto $c): array => $c->toArray(), $series->candles),
            'quote' => $instrument->quote()->value,
            'precision' => $instrument->quote()->displayPrecision(),
            'displayDivisor' => $instrument->quote()->displayDivisor(),
            'spreadVersion' => $series->spreadVersion,
            'degraded' => $series->degraded,
        ]);
    }
}
