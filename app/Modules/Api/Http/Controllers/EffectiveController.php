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
            'manualOnly' => $s['manualOnly'],
            // `quotedAt` tu BoardRowDto az qabl reshte ye ISO ast.
            'quotedAt' => $s['quotedAt'],
        ], $feed['symbols']);

        $payload = [
            'data' => $data,
            'degraded' => $feed['degraded'],
            // Alarm: age feed ru manba' e pushtiban oftade.
            'fallbackActive' => $feed['alarm'],
            'fallbackCount' => $feed['fallbackCount'],
        ];
        // Gheymat e dasti/olaviat bedun tick-version ham avaz mishe.
        $etag = 'W/"e'.hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return new JsonResponse(null, 304, ['ETag' => $etag, 'X-Pb-Fallback' => $feed['alarm'] ? '1' : '0']);
        }

        return new JsonResponse($payload, 200, [
            'ETag' => $etag,
            'X-Pb-Fallback' => $feed['alarm'] ? '1' : '0',
        ]);
    }
}
