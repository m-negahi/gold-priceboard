<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Sms;

use App\Modules\Auth\Domain\Ports\SmsPort;
use Psr\Log\LoggerInterface;

/**
 * Driver e DEV: code tu log chap mishe ta betuni login koni.
 *
 * 🔴 FAGHAT dev. In tanha jayi tu kol e system ast ke code e OTP e
 * plaintext jayi sabt mishe — va hamin dalil e vojudesh ast (dev SMS
 * nadare). Tu production `SMS_DRIVER=log` ya'ni code haye hame tu log —
 * pas `SmsManager` moghe'e sakht hoshdar nemide، vali config e production
 * bayad `kavenegar` bashe.
 */
final readonly class LogSms implements SmsPort
{
    public function __construct(private LoggerInterface $logger) {}

    public function sendOtp(string $phone, string $code): void
    {
        $this->logger->info('sms.otp (driver e log — faghat dev)', [
            'phone' => $phone,
            'code' => $code,
        ]);
    }
}
