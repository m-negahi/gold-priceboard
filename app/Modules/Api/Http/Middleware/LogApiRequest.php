<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Infrastructure\Monitoring\ApiRequestLog;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sabt e HAR call e API baraye inspector e panel (P4-11).
 *
 * BIRUNI tarin middleware e group e API ast ta 401 haye auth ro ham
 * bebine — «chera client e man 401 migire» raiej tarin soal e debug e.
 *
 * ⚠️ Chi sabt NEMISHE: header haye emza (secret ke nist، vali nonce/
 * signature ham masraf e debug nadaran va log eshun faghat surface e
 * nasht e. Body faghat ta 2KB va response faghat vaghti KHATA-st.
 */
final class LogApiRequest
{
    private const MAX_BODY_BYTES = 2_048;

    public function __construct(private readonly ApiRequestLog $log) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $response = $next($request);

        $status = $response->getStatusCode();
        $content = null;

        if ($status >= 400) {
            // Faghat khata ha body ye response ro lazem daran.
            $content = mb_substr((string) $response->getContent(), 0, self::MAX_BODY_BYTES);
        }

        $body = (string) $request->getContent();

        $this->log->push([
            'at' => CarbonImmutable::now()->toIso8601String(),
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'ip' => $request->ip(),
            // Az header miad — GHABL az auth ham hast؛ «eddea» ye client.
            'client_id' => (string) $request->header('X-Pb-Client', ''),
            'request_id' => (string) $request->header('X-Pb-Request-Id', ''),
            'cost' => $response->headers->get('X-Pb-Cost'),
            'balance' => $response->headers->get('X-Pb-Balance'),
            'etag' => $response->headers->get('ETag'),
            'body' => $body === '' ? null : mb_substr($body, 0, self::MAX_BODY_BYTES),
            'response' => $content,
        ]);

        return $response;
    }
}
