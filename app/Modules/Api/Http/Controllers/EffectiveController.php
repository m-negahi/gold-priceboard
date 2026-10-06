<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers;

use App\Modules\Pricing\Application\Queries\EffectivePriceQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feed e MO'ASSER (P4-09+): yek gheymat be ezaye har symbol، ba olaviat e
 * provider va fallback be manual. In hamun «API-i ke hargez ghat' nemishe»
 * ast ke sahebe proje khast.
 *
 * `X-Pb-Fallback: 1` + `usingFallback` ru har symbol be customer mige ke un
 * gheymat az manba' e pushtiban (dasti/kohne) miad، na manba' e asli.
 */
final readonly class EffectiveController
{
    public function __construct(private EffectivePriceQuery $effective) {}

    public function index(Request $request): JsonResponse
    {
        $feed = $this->effective->feed();

        $etag = sprintf('W/"e%d:s%d:%d"', $feed['version'], $feed['spreadVersion'], $feed['fallbackCount']);

        if ($request->headers->get('If-None-Match') === $etag) {
            return new JsonResponse(null, 304, ['ETag' => $etag]);
        }

        $data = array_map(static fn (array $s): array => [
            'symbol' => $s['symbol'],
            'label' => $s['label'],
            'provider' => $s['provider'],
            'sell' => $s['sell'],
            'buy' => $s['buy'],
            'scale' => $s['scale'],
            'quote' => $s['quote'],
            'displayDivisor' => $s['displayDivisor'],
            'changePercent' => $s['changePercent'],
            'direction' => $s['direction'],
            'isStale' => $s['isStale'],
            'isDerived' => $s['isDerived'],
            'usingFallback' => $s['usingFallback'],
            // `quotedAt` tu BoardRowDto az qabl reshte ye ISO ast.
            'quotedAt' => $s['quotedAt'],
        ], $feed['symbols']);

        return new JsonResponse([
            'data' => $data,
            'degraded' => $feed['degraded'],
            // Alarm: age feed ru manba' e pushtiban oftade.
            'fallbackActive' => $feed['alarm'],
            'fallbackCount' => $feed['fallbackCount'],
        ], 200, [
            'ETag' => $etag,
            'X-Pb-Fallback' => $feed['alarm'] ? '1' : '0',
        ]);
    }
}
