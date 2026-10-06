<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Http\Controllers;

use App\Modules\Pricing\Application\Dto\CandleDto;
use App\Modules\Pricing\Application\Dto\CandleRequestDto;
use App\Modules\Pricing\Application\Queries\CandleQuery;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Application\Queries\PublicBoardQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Interval;
use App\Modules\Pricing\Domain\Enums\Side;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tablo ye OMUMI ye site (Phase 5).
 *
 * `home()` SSR ast (first paint baraye SEO)؛ `boardJson()` hamun data ro
 * ba ETag mide ta Alpine har 15s poll kone — poll e bi-taghir 304 migire,
 * na kol e payload.
 *
 * ⚠️ `/board.json` OMUMI ast (bedun e auth) — bar khalaf e `/api/v1/*` ke
 * HMAC + charge dare. In yeki chizi-e ke tablo ye site azash mikhune.
 */
final readonly class PublicBoardController
{
    public function __construct(
        private PublicBoardQuery $board,
        private CandleQuery $candles,
        private ProviderQuery $providers,
    ) {}

    public function home(): View
    {
        return view('public.board', ['board' => $this->board->present()]);
    }

    public function boardJson(Request $request): JsonResponse
    {
        $board = $this->board->present();

        // ETag e za'if: `W/"..."` — content e semantic yeki-e، hatta age
        // whitespace farg kone.
        $etag = 'W/'.$board['etag'];

        if ($request->headers->get('If-None-Match') === $etag) {
            return new JsonResponse(null, 304, ['ETag' => $etag]);
        }

        return new JsonResponse($board, 200, [
            'ETag' => $etag,
            // 15s — hamun cadence e poll؛ CDN/proxy mitune cache kone.
            'Cache-Control' => 'public, max-age=5',
        ]);
    }

    /**
     * Candle haye OMUMI baraye chart (P5-04).
     *
     * Har param az ENUM/lista rad mishe — hich reshte ye kham be Flux
     * nemirese (hamun allowlist e `Flux::tag`). Spread ham e'mal mishe ta
     * chart ba tablo yeki bashe.
     */
    public function candlesJson(Request $request): JsonResponse
    {
        $instrument = Instrument::tryFrom((string) $request->query('symbol', ''));
        $interval = Interval::tryFrom((string) $request->query('interval', ''))
            ?? Interval::default();

        if ($instrument === null) {
            return new JsonResponse(['message' => 'نماد نامعتبر است.'], 422);
        }

        $configs = $this->providers->allActive();
        $providerKey = (string) $request->query('provider', '');

        // Provider e peed-farz: avvalin provider e fa'al ke in symbol ro dare.
        if (! array_key_exists($providerKey, $configs)) {
            $providerKey = array_key_first($configs) ?? '';
        }

        if ($providerKey === '') {
            return new JsonResponse(['data' => [], 'degraded' => true]);
        }

        $to = time();
        $from = $to - $this->lookbackSeconds($interval);

        $series = $this->candles->candles(CandleRequestDto::make(
            providerKey: $providerKey,
            instrument: $instrument,
            interval: $interval,
            from: $from,
            to: $to,
            side: Side::Last,
        ));

        return new JsonResponse([
            'data' => array_map(static fn (CandleDto $c): array => $c->toArray(), $series->candles),
            'symbol' => $instrument->value,
            'label' => $instrument->label(),
            'interval' => $interval->value,
            'provider' => $providerKey,
            'precision' => $instrument->quote()->displayPrecision(),
            'displayDivisor' => $instrument->quote()->displayDivisor(),
            'degraded' => $series->degraded,
        ], 200, ['Cache-Control' => 'public, max-age='.$interval->cacheSeconds()]);
    }

    /** Che moddat gozashte baraye har interval (ta chart por bashe). */
    private function lookbackSeconds(Interval $interval): int
    {
        return match ($interval) {
            Interval::M1 => 6 * 3_600,       // 6 saat
            Interval::M5 => 2 * 86_400,      // 2 rooz
            Interval::M15 => 7 * 86_400,     // yek hafte
            Interval::H1 => 30 * 86_400,     // yek mah
            Interval::H4 => 120 * 86_400,    // 4 mah
            Interval::D1 => 365 * 86_400,    // yek sal
        };
    }
}
