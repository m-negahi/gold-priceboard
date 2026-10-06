<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exceptions;

use RuntimeException;

/**
 * Khata haye jarayan e OTP — payam ha FARSI-and chun mostaghim be KARBAR
 * neshun dade mishan (`SignIn` e Livewire hamin ha ro render mikone).
 *
 * ── 🔴 Enumeration safety ───────────────────────────────────────────
 * HICH payam-i nabayad lo bede ke in shomare KARBAR DARE ya na. Pas:
 *   - `invalidCode` yeki-e baraye «ghalat»، «monghazi» va «aslan ferestade
 *     nashode» — tafkik eshun be attacker naghshe ye hamle mide.
 *   - Tu send، aslan check e vojud e karbar NADARIM (sabt-e-nam ham ba
 *     hamin OTP ast).
 */
final class OtpException extends RuntimeException
{
    /** Saniye ta mojaz shodan e talash e ba'di — baraye countdown e UI. */
    public int $retryAfterSeconds = 0;

    public static function invalidPhone(): self
    {
        return new self('شمارهٔ موبایل معتبر نیست.');
    }

    public static function cooldown(int $seconds): self
    {
        $e = new self("کد قبلاً ارسال شده است؛ لطفاً {$seconds} ثانیه صبر کنید.");
        $e->retryAfterSeconds = $seconds;

        return $e;
    }

    public static function throttled(int $seconds): self
    {
        $minutes = max(1, (int) ceil($seconds / 60));
        $e = new self("تعداد تلاش‌ها بیش از حد مجاز است؛ لطفاً {$minutes} دقیقه بعد دوباره تلاش کنید.");
        $e->retryAfterSeconds = $seconds;

        return $e;
    }

    public static function invalidCode(): self
    {
        return new self('کد نادرست است یا منقضی شده؛ در صورت نیاز کد جدید بگیرید.');
    }

    /** P6-05 — faghat BA'D az code e dorost partab mishe (enumeration-safe). */
    public static function accountDisabled(): self
    {
        return new self('حساب کاربری شما غیرفعال شده است.');
    }

    public static function sendFailed(): self
    {
        // Jozyat e khata (Kavenegar/…) tu LOG mimune، na jolo ye karbar.
        return new self('ارسال پیامک با خطا مواجه شد؛ لطفاً دوباره تلاش کنید.');
    }
}
