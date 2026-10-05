<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Ports;

use App\Modules\Auth\Domain\Exceptions\SmsException;

/**
 * Ferestadan e SMS.
 *
 * ── Chera faghat `sendOtp` va na `send(text)` e omumi ────────────────
 * Tanha SMS-i ke system mifereste OTP ast. Yek `send(text)` e omumi:
 *   1. Davat mikone matn e payam tu laye haye bala sakhte beshe — va un
 *      ya'ni CODE tu string ha va log ha michakhe.
 *   2. Kavenegar baraye OTP endpoint e `verify/lookup` (template) dare ke
 *      tahvil esh az matn e azad behtar ast — port e omumi un ro penhan
 *      mikard.
 * Rooz-i ke payam e gheyr-OTP lazem shod، method e jadid ba contract e
 * khodesh ezafe mishe.
 */
interface SmsPort
{
    /**
     * @throws SmsException
     */
    public function sendOtp(string $phone, string $code): void;
}
