<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Domain\Services\HmacSignature;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Auth\Domain\Enums\UserStatus;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Auth e HMAC e API (P4-04).
 *
 * Header ha: `X-Pb-Client`، `X-Pb-Timestamp` (unix)، `X-Pb-Nonce`،
 * `X-Pb-Signature`.
 *
 * ── Se mohafez ──────────────────────────────────────────────────────
 * 1. SKEW ±30s: emza ye zabt-shode faghat 60 saniye ghabel e replay e
 *    NAZARI ast — ke nonce hamun ro ham mibande.
 * 2. NONCE yek-bar-masraf (TTL 90s > panjere ye skew): replay e daghigh
 *    e hamun request mohal e. `Cache::add` atomic ast (SETNX+TTL).
 *    🔴 Cache ghat' bashe FAIL-CLOSED im (503) — auth e bedun e zed e
 *    replay auth nist.
 * 3. DECOY-HMAC: client e nashenas ham yek verify ye kamel ba secret e
 *    sakhtegi mikhore ta zaman e pasokh e «client nist» ba «emza ghalat
 *    ast» yeki bashe — timing attack ru vojud e client_id kur mishe.
 *
 * 🔴 HAME ye shekast ha yek payam e yeksan e 401 daran — hich jozyiat
 * (kodum header، kodum marhale) be biroon nemire.
 */
final class ApiHmacAuth
{
    private const MAX_SKEW_SECONDS = 30;

    private const NONCE_TTL_SECONDS = 90;

    /** Secret e sakhtegi baraye decoy — faghat ta'adol e zamani. */
    private const DECOY_SECRET = 'pb-decoy-secret-timing-equalizer';

    public function handle(Request $request, Closure $next): Response
    {
        $clientId = (string) $request->header('X-Pb-Client', '');
        $timestamp = (string) $request->header('X-Pb-Timestamp', '');
        $nonce = (string) $request->header('X-Pb-Nonce', '');
        $signature = (string) $request->header('X-Pb-Signature', '');

        if ($clientId === '' || $timestamp === '' || $nonce === '' || $signature === '' || strlen($nonce) > 64) {
            return $this->unauthorized();
        }

        // ── Skew ────────────────────────────────────────────────────
        $now = CarbonImmutable::now()->getTimestamp();

        if (preg_match('/^\d{1,12}$/', $timestamp) !== 1
            || abs($now - (int) $timestamp) > self::MAX_SKEW_SECONDS) {
            return $this->unauthorized();
        }

        $canonical = HmacSignature::canonical(
            $request->method(),
            $request->getRequestUri(),
            $timestamp,
            $nonce,
            (string) $request->getContent(),
        );

        // ── Client + decoy ──────────────────────────────────────────
        // `owner_status` ba SUBQUERY (P6-05) — hamun yek query، bedun e
        // relation e Api -> Auth.
        $client = ApiClient::query()
            ->select('api_clients.*')
            ->addSelect(['owner_status' => DB::table('users')
                ->select('status')
                ->whereColumn('users.id', 'api_clients.user_id')
                ->limit(1)])
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->first();

        if ($client === null) {
            // Decoy: hamun kar e mohasebati، hamun javab.
            HmacSignature::verify(self::DECOY_SECRET, $canonical, $signature);

            return $this->unauthorized();
        }

        try {
            $secret = Crypt::decryptString($client->secret_encrypted);
        } catch (Throwable $e) {
            // Secret e kharab (APP_KEY avaz shode?) — hamun 401 e omumi؛
            // jozyat faghat tu log e server، hich chiz be biroon.
            logger()->warning('api.secret_undecryptable', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);

            return $this->unauthorized();
        }

        if (! HmacSignature::verify($secret, $canonical, $signature)) {
            return $this->unauthorized();
        }

        // ── Saheb e disable (P6-05) ─────────────────────────────────
        // BA'D az emza: faghat saheb e vaghei ye secret vaziat ro mibine
        // (bi-emza hamun 401 e omumi). HAME ye site haye karbar ba ham.
        if ($client->getAttribute('owner_status') !== UserStatus::Active->value) {
            return new JsonResponse(['message' => 'حساب کاربری صاحب این کلید غیرفعال است.'], 403);
        }

        // ── Nonce (BA'D az emza: attacker e bi-emza nabayad betune ba
        // nonce haye ja'li cache ro por kone) ─────────────────────────
        try {
            $fresh = Cache::add("api:nonce:{$clientId}:{$nonce}", 1, self::NONCE_TTL_SECONDS);
        } catch (Throwable) {
            // 🔴 FAIL-CLOSED: bedun e zed e replay، service nemidim.
            return new JsonResponse(['message' => 'سرویس موقتا در دسترس نیست.'], 503);
        }

        if ($fresh !== true) {
            return $this->unauthorized();
        }

        // `last_used_at` dampened — na har call (5,760 UPDATE e ruzane).
        if ($client->last_used_at === null || $client->last_used_at->diffInSeconds() > 60) {
            $client->forceFill(['last_used_at' => CarbonImmutable::now()])->saveQuietly();
        }

        $request->attributes->set('api_client', $client);

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(['message' => 'اعتبارسنجی درخواست ناموفق بود.'], 401);
    }
}
