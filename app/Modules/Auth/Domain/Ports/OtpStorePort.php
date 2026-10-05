<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Ports;

use App\Modules\Auth\Application\Dto\OtpEntryDto;

/**
 * Zakhire ye movaghat e OTP (Redis e omumi).
 *
 * ── Tarahi ──────────────────────────────────────────────────────────
 * Code-hash va shomaresh e talash ha tu YEK kelid-and، pas BA HAM expire va
 * BA HAM evict mishan — hich halati vojud nadare ke shomaresh bemune vali
 * code na، ya bar'aks (shomaresh e evict-shode ya'ni talash haye majani).
 *
 * Ru Redis e OMUMI (na `redis-prices`): OTP yek cache e VAGHEI-e — evict
 * shodanesh faghat ya'ni «dobare befrest»، na az dast raftan e data.
 */
interface OtpStorePort
{
    /**
     * Zakhire ye code e jadid — entry e ghabli (va shomaresh esh) ra KAMEL
     * jaygozin mikone.
     */
    public function put(string $phone, string $codeHash, int $ttlSeconds): void;

    /** `null` ya'ni code-i nist (expire shode، batel shode، ya hich vaght nabude). */
    public function get(string $phone): ?OtpEntryDto;

    /**
     * Yek talash e GHALAT ro sabt mikone — ATOMIC.
     *
     * @return int shomaresh e jadid؛ `-1` ya'ni entry dige vojud nadasht
     *             (expire shode beyn e `get` va in call). ⚠️ Nabayad entry
     *             ye jadid besaze — yek shomaresh e bedun e code va bedun e
     *             TTL yek kelid e yatim e abadi-e.
     */
    public function recordFailedAttempt(string $phone): int;

    /** Batel kardan e sarih — ba'd az verify e movafagh (yek-bar-masraf) ya 3 ghalat. */
    public function invalidate(string $phone): void;

    /**
     * Chand saniye az omr e code e zende munde؛ `0` ya'ni code-i nist.
     *
     * Baraye halat e avvaliye ye safhe ye login: refresh e safhe bayad be
     * step e code bargarde ba SHOMARANDE ye server، na az aval.
     */
    public function ttlRemaining(string $phone): int;

    /** Sabt e cooldown e resend. */
    public function startCooldown(string $phone, int $seconds): void;

    /** Chand saniye ta mojaz shodan e resend munde؛ `0` ya'ni mojaz ast. */
    public function cooldownRemaining(string $phone): int;

    /**
     * Pak kardan e cooldown — masraf: `otp:flush` e dev/e2e.
     *
     * ⚠️ `startCooldown($phone, 0)` in kar ro NEMIKONE: `SETEX` ba 0 ro
     * Redis ba khata rad mikone va cooldown e ghabli sar e jash mimune —
     * hamin bug yek bar e2e ro flaky kard (har test e dovom 429 migereft).
     */
    public function clearCooldown(string $phone): void;
}
