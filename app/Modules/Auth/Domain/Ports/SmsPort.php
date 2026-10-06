<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Ports;

use App\Modules\Auth\Domain\Exceptions\SmsException;

/** OTP va vaziat e ticket ba template haye joda; matn e azad az laye ye UI nemiad. */
interface SmsPort
{
    /**
     * @throws SmsException
     */
    public function sendTicketStatus(string $phone, int $ticketId, string $status): void;

    public function sendOtp(string $phone, string $code): void;
}
