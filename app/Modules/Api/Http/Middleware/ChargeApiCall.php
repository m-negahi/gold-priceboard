<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Application\Actions\ChargeApiCallAction;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kasr e hazine ye call (P4-05/P4-06): reserve → ejra → error/304 → refund.
 *
 * ── Idempotency e billing ───────────────────────────────────────────
 * `reference` = `X-Pb-Request-Id` (agar client dad) vagarna nonce e
 * hamin request. Client-i ke retry e bedun e kasr e dobare mikhad،
 * `X-Pb-Request-Id` e SABET mifreste (nonce bayad har bar taze bashe —
 * zed e replay e auth) — ledger yek bar kasr mikone.
 *
 * ── Chi refund mishe ────────────────────────────────────────────────
 * 304 (ETag — poll e bi-taghir) va har pasokh e gheyr e 2xx. Faghat
 * data ye vaghei pul dare. (PLAN: «ETag ru snapshot — customer motavajeh
 * mishe kodum ro mikonim».)
 */
final class ChargeApiCall
{
    public function __construct(private readonly ChargeApiCallAction $charger) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var ApiClient|null $client */
        $client = $request->attributes->get('api_client');

        if ($client === null) {
            // Bedun e auth hich charge-i ma'ni nadare — config e route ghalat e.
            return new JsonResponse(['message' => 'اعتبارسنجی درخواست ناموفق بود.'], 401);
        }

        $reference = trim((string) $request->header('X-Pb-Request-Id', ''));

        if ($reference === '') {
            $reference = (string) $request->header('X-Pb-Nonce', '');
        }

        $charge = $this->charger->charge($client, $reference);

        if ($charge['status'] === 'blocked') {
            return new JsonResponse(['message' => 'کیف پول مسدود است. با پشتیبانی تماس بگیرید.'], 403);
        }

        if ($charge['status'] === 'insufficient') {
            return new JsonResponse(['message' => 'اعتبار کافی نیست. کیف پول را شارژ کنید.'], 402);
        }

        if ($charge['status'] === 'unavailable') {
            // 🔴 Fail-CLOSED.
            return new JsonResponse(['message' => 'سرویس موقتا در دسترس نیست.'], 503);
        }

        $response = $next($request);

        $charged = $charge['status'] === 'ok';

        if ($charged && ($response->getStatusCode() === 304 || ! $response->isSuccessful())) {
            $this->charger->refundCall($client, $reference, $charge['cost']);
            $charged = false;
        }

        // Shafafiat baraye customer (PLAN): chi kasr shod.
        $response->headers->set('X-Pb-Cost', (string) ($charged ? $charge['cost'] : 0));

        if ($charge['balance'] !== null) {
            $response->headers->set('X-Pb-Balance', (string) $charge['balance']);
        }

        return $response;
    }
}
