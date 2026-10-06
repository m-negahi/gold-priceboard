<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Actions;

use App\Modules\Auth\Domain\Exceptions\OtpException;
use App\Modules\Auth\Domain\Ports\OtpStorePort;
use App\Modules\Auth\Domain\Services\OtpCode;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Auth\Support\OtpThrottle;
use App\Modules\Common\Helpers\PhoneHelper;
use Carbon\CarbonImmutable;

/**
 * Ta'eed e code + sakht/vorud e karbar.
 *
 * ── 🔴 Karbar FAGHAT ba'd az ta'eed sakhte mishe ────────────────────
 * Agar tu `send` besazim، har kasi ke yek bar shomare type kone (ya attacker
 * ba lista ye shomare ha) radif e `users` misaze — jadval por e karbar e
 * ashghal mishe va «tedad e karbar ha» ye dorugh mishe.
 *
 * ── 🔴 Ba'd az `max_attempts` ghalat، code BATEL mishe ──────────────
 * Na faghat shomordan: 6 ragham = 10^6 halat؛ bedun e batel kardan، hads
 * zadan faghat yek mas'ale ye sor'at ast. Ba 3 talash، ehtemal e hads
 * 3e-6 ast va tamum.
 *
 * ── Yek payam baraye hame ye shekast ha ─────────────────────────────
 * «ghalat»، «monghazi» va «aslan ferestade nashode» hame yek javab daran
 * (`invalidCode`) — tafkik eshun be attacker naghshe mide.
 */
final readonly class VerifyOtpAction
{
    public function __construct(
        private OtpStorePort $store,
        private OtpCode $otp,
        private OtpThrottle $throttle,
        private int $maxAttempts = 3,
    ) {}

    public function execute(string $phone, string $code, string $ip): User
    {
        $normalized = PhoneHelper::normalize($phone);

        if ($normalized === null) {
            throw OtpException::invalidPhone();
        }

        $blocked = $this->throttle->verifyBlockedFor($normalized, $ip);

        if ($blocked !== null) {
            throw OtpException::throttled($blocked);
        }

        // 🔴 GHABL az check — har talash hesab mishe، movafagh ya na.
        $this->throttle->hitVerify($normalized, $ip);

        $entry = $this->store->get($normalized);

        if ($entry === null || $entry->attempts >= $this->maxAttempts) {
            // Monghazi / nabude / ghablan batel — hame yek javab.
            throw OtpException::invalidCode();
        }

        if (! $this->otp->verify($normalized, $code, $entry->codeHash)) {
            $attempts = $this->store->recordFailedAttempt($normalized);

            // 🔴 Be saghf resid -> code BATEL، hatta age talash e ba'di
            // code e DOROST ro bezane.
            if ($attempts >= $this->maxAttempts || $attempts === -1) {
                $this->store->invalidate($normalized);
            }

            throw OtpException::invalidCode();
        }

        // Do request mitunan yek entry ro bekhunan; faghat barande e consume login migire.
        if (! $this->store->consume($normalized, $entry->codeHash, $this->maxAttempts)) {
            throw OtpException::invalidCode();
        }
        $this->throttle->clearVerifies($normalized);

        return $this->signIn($normalized);
    }

    private function signIn(string $phone): User
    {
        $now = CarbonImmutable::now();

        /** @var User $user */
        $user = User::query()->firstOrCreate(['phone' => $phone]);

        // 🔴 P6-05: karbar e disable ba code e DOROST ham login nemishe.
        // Check BA'D az ta'eed e code ast — payam faghat be saheb e vaghei
        // ye shomare mirese، pas vaziat e hesab ro be attacker lo nemide.
        if (! $user->isActive()) {
            throw OtpException::accountDisabled();
        }

        // `forceFill` amdi: in soton ha fillable nistan (va nabayad bashan).
        $user->forceFill([
            'phone_verified_at' => $user->phone_verified_at ?? $now,
            'last_login_at' => $now,
        ])->save();

        return $user;
    }
}
