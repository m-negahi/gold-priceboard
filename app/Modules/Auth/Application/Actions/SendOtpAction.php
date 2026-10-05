<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Actions;

use App\Modules\Auth\Application\Dto\SendOtpResultDto;
use App\Modules\Auth\Domain\Exceptions\OtpException;
use App\Modules\Auth\Domain\Exceptions\SmsException;
use App\Modules\Auth\Domain\Ports\OtpStorePort;
use App\Modules\Auth\Domain\Ports\SmsPort;
use App\Modules\Auth\Domain\Services\OtpCode;
use App\Modules\Auth\Support\OtpThrottle;
use App\Modules\Common\Helpers\PhoneHelper;
use Psr\Log\LoggerInterface;

/**
 * Ferestadan e code e login.
 *
 * ── 🔴 Enumeration safety ───────────────────────────────────────────
 * HICH check e «in shomare karbar dare?» inja NIST — be har shomare ye
 * motabar SMS mifrestim، chon sabt-e-nam ham ba hamin OTP ast. Natije:
 * raftar baraye shomare ye mojud va namojud BYTE-BE-BYTE yeksan ast va
 * hichkas nemitune az in endpoint lista ye moshtari dar biare.
 *
 * ── Tartib e gam ha (mohem) ─────────────────────────────────────────
 * cooldown -> throttle -> tolid+zakhire -> SMS -> hit+cooldown
 *
 * `hit` va `cooldown` FAGHAT ba'd az SMS e MOVAFAGH: shekast e Kavenegar
 * nabayad sahmie ye karbar ro bokhore (SMS-i naraft) — karbar e vaghei
 * bayad betune fori dobare bezane.
 */
final readonly class SendOtpAction
{
    public function __construct(
        private OtpStorePort $store,
        private OtpCode $otp,
        private SmsPort $sms,
        private OtpThrottle $throttle,
        private int $ttlSeconds = 120,
        private int $cooldownSeconds = 60,
        private int $digits = 6,
        private bool $bypass = false,
        private ?LoggerInterface $logger = null,
    ) {}

    public function execute(string $phone, string $ip): SendOtpResultDto
    {
        // Middleware normalize karde، vali Action nabayad be un tekiye kone —
        // in khat e defa' e khod e Action ast (masalan call az CLI/test).
        $normalized = PhoneHelper::normalize($phone);

        if ($normalized === null) {
            throw OtpException::invalidPhone();
        }

        $cooldown = $this->store->cooldownRemaining($normalized);

        if ($cooldown > 0) {
            throw OtpException::cooldown($cooldown);
        }

        $blocked = $this->throttle->sendBlockedFor($normalized, $ip);

        if ($blocked !== null) {
            throw OtpException::throttled($blocked);
        }

        $code = $this->otp->generate($this->digits);

        // 🔴 Zakhire GHABL az ersal: agar zakhire fail she hich SMS-i
        // naraftе؛ aks esh ya'ni karbar code-i dare ke ma nemishnasim.
        $this->store->put($normalized, $this->otp->hash($normalized, $code), $this->ttlSeconds);

        try {
            $this->sms->sendOtp($normalized, $code);
        } catch (SmsException $e) {
            // Code e zakhire-shode batel mishe ta «code e yatim» namune.
            $this->store->invalidate($normalized);

            // Jozyat tu log (bedun e code، shomare mask-shode) — jolo ye
            // karbar faghat yek payam e omumi.
            $this->logger?->warning('otp.send_failed', [
                'phone' => PhoneHelper::mask($normalized),
                'error' => $e->getMessage(),
            ]);

            throw OtpException::sendFailed();
        }

        $this->throttle->hitSend($normalized, $ip);
        $this->store->startCooldown($normalized, $this->cooldownSeconds);

        return new SendOtpResultDto(
            expiresInSeconds: $this->ttlSeconds,
            resendCooldownSeconds: $this->cooldownSeconds,
            // 🔴 FAGHAT dev — tu production hamishe null.
            bypassCode: $this->bypass ? $code : null,
        );
    }
}
