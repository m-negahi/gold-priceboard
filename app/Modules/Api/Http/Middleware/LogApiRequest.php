<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Infrastructure\Monitoring\ApiRequestLog;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sabt e HAR call e API baraye inspector e panel (P4-11) — mesle Telescope.
 *
 * BIRUNI tarin middleware e group e API ast ta 401 haye auth ro ham bebine
 * — «chera client e man 401 migire» raiej tarin soal e debug e.
 *
 * ── Chi sabt mishe ──────────────────────────────────────────────────
 * Request: method، uri، header haye marbut (emza/timestamp/nonce — baraye
 * debug e 401 lazem-and؛ SECRET aslan tu header nist pas nasht e nadare)،
 * body (GET body nadare vali POST haye ayande chera).
 * Response: status، header haye `X-Pb-*`/ETag، va BODY e kamel (ta saghf) —
 * baraye HAME ye call، na faghat khata (darkhast e «hame chizo neshun bede»).
 *
 * 304 body nadare (khod e pas-nadadan e data hamun noghte ash ast).
 */
final class LogApiRequest
{
    private const MAX_REQUEST_BYTES = 2_048;

    /** Response e `/prices` mitune chand KB bashe — saghf e bozorgtar. */
    private const MAX_RESPONSE_BYTES = 16_384;

    /** Header hayi ke arzesh e debug daran (secret nist). */
    private const TRACKED_HEADERS = [
        'X-Pb-Client', 'X-Pb-Timestamp', 'X-Pb-Nonce', 'X-Pb-Signature',
        'X-Pb-Request-Id', 'If-None-Match', 'User-Agent',
    ];

    public function __construct(private readonly ApiRequestLog $log) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $response = $next($request);

        $status = $response->getStatusCode();
        $requestBody = (string) $request->getContent();

        $this->log->push([
            'at' => CarbonImmutable::now()->toIso8601String(),
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'ip' => $request->ip(),
            'client_id' => (string) $request->header('X-Pb-Client', ''),
            'request_id' => (string) $request->header('X-Pb-Request-Id', ''),
            'cost' => $response->headers->get('X-Pb-Cost'),
            'balance' => $response->headers->get('X-Pb-Balance'),
            'etag' => $response->headers->get('ETag'),
            'request_headers' => $this->headers($request),
            'request_body' => $requestBody === '' ? null : mb_substr($requestBody, 0, self::MAX_REQUEST_BYTES),
            // 🔴 HAME ye response، na faghat khata (Telescope-like).
            'response' => $status === 304 ? null : mb_substr((string) $response->getContent(), 0, self::MAX_RESPONSE_BYTES),
        ]);

        return $response;
    }

    /** @return array<string, string> */
    private function headers(Request $request): array
    {
        $headers = [];

        foreach (self::TRACKED_HEADERS as $name) {
            $value = $request->header($name);

            if (is_string($value) && $value !== '') {
                // Signature kutah mishe — kamakesh baraye tashkhis kafi-e va
                // log ro shaloogh nemikone.
                $headers[$name] = $name === 'X-Pb-Signature'
                    ? mb_substr($value, 0, 16).'…'
                    : $value;
            }
        }

        return $headers;
    }
}
