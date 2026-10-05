<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exceptions;

use RuntimeException;

/**
 * Khata ye ersal e SMS.
 *
 * 🔴 Payam haye in exception tu LOG mishinan، pas hich vaght nabayad
 * API key (ke tu URL e Kavenegar ast) ya khod e CODE toosheshun bashe.
 * Shomare hamishe mask-shode miad.
 */
final class SmsException extends RuntimeException
{
    public static function missingApiKey(string $driver): self
    {
        return new self("driver e «{$driver}»: API key set nashode.");
    }

    public static function invalidReceptor(string $driver, string $maskedPhone): self
    {
        return new self("driver e «{$driver}»: shomare ye girande motabar nist ({$maskedPhone}).");
    }

    public static function connection(string $driver): self
    {
        // ⚠️ AMDAN hich jozyat-i az exception e asli nemiad: payam e
        // `ConnectionException` e Laravel URL e kamel ro dare — va tu
        // Kavenegar API key DAKHEL e URL ast.
        return new self("driver e «{$driver}»: ettesal bargharar nashod.");
    }

    public static function httpStatus(string $driver, int $status): self
    {
        return new self("driver e «{$driver}»: pasokh e HTTP {$status}.");
    }

    /**
     * 🔴 Kavenegar khata ha ro ba HTTP 200 barmigardune — code e vaghei tu
     * `return.status` e body ast. Shomare ye nadorost va e'tebar e
     * tamam-shode har do «200» midan؛ agar faghat `successful()` ro check
     * koni، SMS hich vaght nemirese va hich khata-i ham nemibini.
     */
    public static function apiStatus(string $driver, int $apiStatus, string $message): self
    {
        return new self("driver e «{$driver}»: API status {$apiStatus} — {$message}");
    }

    public static function unknownDriver(string $driver): self
    {
        return new self("driver e nashenas e SMS: «{$driver}».");
    }
}
