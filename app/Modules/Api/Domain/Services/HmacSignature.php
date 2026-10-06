<?php

declare(strict_types=1);

namespace App\Modules\Api\Domain\Services;

/**
 * Emza ye HMAC e request haye API (P4-04). PURE — hich Laravel.
 *
 * ── Canonical string ────────────────────────────────────────────────
 *   METHOD \n URI \n TIMESTAMP \n NONCE \n sha256(body)
 *
 * `URI` = path + query string (daghighan hamun ke ferestade shode).
 * Body e khali ham hash mishe (`sha256('')`) — na string e khali، ta
 * «bedun e body» va «body e khali» yek emza dashte bashan va client
 * ha gij nashan.
 *
 * ── Chera `hash_equals` ─────────────────────────────────────────────
 * Moghayese ye `===` zaman esh be tedad e byte haye barabar e avval
 * bastegi dare — baraye secret ha hamishe constant-time.
 */
final readonly class HmacSignature
{
    public static function canonical(string $method, string $uri, string $timestamp, string $nonce, string $body): string
    {
        return implode("\n", [
            strtoupper($method),
            $uri,
            $timestamp,
            $nonce,
            hash('sha256', $body),
        ]);
    }

    public static function sign(string $secret, string $canonical): string
    {
        return hash_hmac('sha256', $canonical, $secret);
    }

    public static function verify(string $secret, string $canonical, string $signature): bool
    {
        return hash_equals(self::sign($secret, $canonical), $signature);
    }
}
