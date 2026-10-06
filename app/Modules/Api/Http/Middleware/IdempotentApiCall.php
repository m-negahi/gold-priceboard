<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Wallet\Application\Queries\WalletQuery;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Retry = hamun pasokh, na data e taze ba charge e ghabli. Lock e moshtarak
 * jolo e do ejra e hamzaman ro migire. Cache e 24-sa'ate agar gom shod,
 * ledger jolo e serve e majani ba reference e masraf-shode ro migire.
 */
final readonly class IdempotentApiCall
{
    public function __construct(private WalletQuery $wallets) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var ApiClient $client */
        $client = $request->attributes->get('api_client');
        $requestId = trim((string) $request->header('X-Pb-Request-Id', ''));

        if (strlen($requestId) > 128) {
            return new JsonResponse(['message' => 'شناسه درخواست باید حداکثر 128 نویسه باشد.'], 422);
        }

        $raw = $requestId === '' ? (string) $request->header('X-Pb-Nonce') : $requestId;
        // 52 byte; ba prefix e refund ham zir e VARCHAR(64) mimune.
        $reference = 'api:'.substr(hash('sha256', $client->client_id.'|'.$raw), 0, 48);
        $request->attributes->set('billing_reference', $reference);

        if ($requestId === '') {
            return $next($request);
        }

        $fingerprint = hash('sha256', implode("\n", [
            $request->method(), $request->getRequestUri(), (string) $request->getContent(),
            (string) $request->header('If-None-Match'),
        ]));
        $key = 'api:replay:'.$reference;
        try {
            $lock = Cache::lock($key.':lock', 120);
            if (! $lock->get()) {
                return new JsonResponse(['message' => 'این درخواست در حال پردازش است؛ دوباره تلاش کنید.'], 409, ['Retry-After' => '1']);
            }
        } catch (Throwable $exception) {
            report($exception);

            return new JsonResponse(['message' => 'سرویس موقتا در دسترس نیست.'], 503);
        }

        try {
            try {
                if ($this->wallets->findForOwner($client->user_id)?->is_blocked) {
                    return new JsonResponse(['message' => 'کیف پول مسدود است. با پشتیبانی تماس بگیرید.'], 403);
                }

                $saved = Cache::get($key);
                if (is_array($saved)) {
                    if ($saved['fingerprint'] !== $fingerprint) {
                        return $this->conflict();
                    }

                    return new Response($saved['body'], $saved['status'], array_merge($saved['headers'], [
                        'X-Pb-Cost' => '0', 'X-Pb-Replayed' => '1',
                    ]));
                }

                if ($this->wallets->hasApiCharge($client->user_id, $reference, $raw, $client->client_id)) {
                    return $this->conflict();
                }
            } catch (Throwable $exception) {
                report($exception);

                return new JsonResponse(['message' => 'سرویس موقتا در دسترس نیست.'], 503);
            }

            $response = $next($request);
            // Rad e billing (402/403/429/503) debit nadare va retry e ba'di momkene movafagh bashe.
            if ($response->headers->has('X-Pb-Cost')) {
                $headers = [];
                foreach (['Content-Type', 'ETag', 'X-Pb-Balance', 'X-Pb-Fallback'] as $header) {
                    if ($response->headers->has($header)) {
                        $headers[$header] = $response->headers->get($header);
                    }
                }
                try {
                    Cache::put($key, [
                        'fingerprint' => $fingerprint, 'body' => $response->getContent(),
                        'status' => $response->getStatusCode(), 'headers' => $headers,
                    ], 86400);
                } catch (Throwable $exception) {
                    // Javab e avval amade ast; ledger retry e bedun e cache ro 409 mikone.
                    report($exception);
                }
            }

            return $response;
        } finally {
            try {
                $lock->release();
            } catch (Throwable) { /* TTL lock ro azad mikone. */
            }
        }
    }

    private function conflict(): JsonResponse
    {
        return new JsonResponse(['message' => 'این شناسه قبلاً استفاده شده است؛ برای درخواست جدید شناسه تازه بفرستید.'], 409);
    }
}
