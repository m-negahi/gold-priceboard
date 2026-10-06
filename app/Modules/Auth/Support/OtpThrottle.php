<?php

declare(strict_types=1);

namespace App\Modules\Auth\Support;

use Illuminate\Cache\RateLimiter;

/**
 * Rate limit e OTP — ru PHONE **va** IP، jodagane.
 *
 * ── 🔴 Chera har do ─────────────────────────────────────────────────
 * Faghat phone: attacker ba HAR request yek shomare ye jadid midé va
 *   SMS-khoone ro khali mikone (har SMS pul e!). IP jolosh ro migire.
 * Faghat IP: attacker ba botnet (hazaran IP) yek shomare ro bombaran
 *   mikone. Phone jolosh ro migire.
 *
 * ⚠️ Limit e IP faghat vaghti ma'ni dare ke `TrustProxies` dorost kar kone
 * (`P2-01`/`P2-02`) — vagarna hame ye karbar ha yek IP daran va ya hame ba
 * ham ban mishan ya hichkas.
 *
 * Send va verify limit e JODA daran: verify arzun ast (hich SMS-i nemire)
 * vali hads zadan e code ast، pas saghf esh bayad tangh tar az
 * `max_attempts * 1` bashe ta resend-loop ham nashe rah e hads.
 */
final readonly class OtpThrottle
{
    public function __construct(
        private RateLimiter $limiter,
        private int $sendPerPhone = 3,
        private int $sendPerIp = 10,
        private int $verifyPerPhone = 5,
        private int $verifyPerIp = 20,
        /** Panjare — pish-farz yek saat. */
        private int $decaySeconds = 3_600,
        /**
         * 🔴 `false` = rate limit KAMELAN khamoosh (dev/demo ba driver e
         * fake — khaste ye sahebe proje: login e dev nabayad throttle she).
         * Hame ye `blockedFor`/`hit` be no-op tabdil mishan. Tu production
         * (driver e vaghei) HAMISHE true-ast.
         */
        private bool $enabled = true,
    ) {}

    /** `null` = mojaz؛ adad = saniye ta azad shodan. */
    public function sendBlockedFor(string $phone, string $ip): ?int
    {
        return $this->blockedFor([
            ["otp:send:phone:{$phone}", $this->sendPerPhone],
            ["otp:send:ip:{$ip}", $this->sendPerIp],
        ]);
    }

    /**
     * Sabt e yek ersal e MOVAFAGH.
     *
     * ⚠️ Faghat ba'd az ersal e movafagh seda mishe: shekast e Kavenegar
     * nabayad sahmie ye karbar ro bokhore — SMS-i narafte.
     */
    public function hitSend(string $phone, string $ip): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->limiter->hit("otp:send:phone:{$phone}", $this->decaySeconds);
        $this->limiter->hit("otp:send:ip:{$ip}", $this->decaySeconds);
    }

    public function verifyBlockedFor(string $phone, string $ip): ?int
    {
        return $this->blockedFor([
            ["otp:verify:phone:{$phone}", $this->verifyPerPhone],
            ["otp:verify:ip:{$ip}", $this->verifyPerIp],
        ]);
    }

    /**
     * Sabt e yek TALASH e verify — ghabl az check e code، ya'ni har talash
     * hesab mishe (movafagh ya na). Vagarna attacker ta bi-nahayat talash e
     * «taghriban dorost» mikone.
     */
    public function hitVerify(string $phone, string $ip): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->limiter->hit("otp:verify:phone:{$phone}", $this->decaySeconds);
        $this->limiter->hit("otp:verify:ip:{$ip}", $this->decaySeconds);
    }

    /**
     * Pak kardan e shomaresh e verify e in PHONE ba'd az login e movafagh.
     *
     * Karbar e vaghei ke 2 bar login kone + chand typo، nabayad gir kone.
     * ⚠️ Shomaresh e IP pak NEMISHE: yek attacker pusht e yek IP nabayad ba
     * har movafaghiat sahmie ye taze begire.
     */
    public function clearVerifies(string $phone): void
    {
        $this->limiter->clear("otp:verify:phone:{$phone}");
    }

    /** @param array<int, array{0: string, 1: int}> $checks */
    private function blockedFor(array $checks): ?int
    {
        if (! $this->enabled) {
            return null;
        }

        foreach ($checks as [$key, $max]) {
            if ($this->limiter->tooManyAttempts($key, $max)) {
                return max(1, $this->limiter->availableIn($key));
            }
        }

        return null;
    }
}
