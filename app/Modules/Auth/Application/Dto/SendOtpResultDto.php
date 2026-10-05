<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Dto;

/**
 * Natije ye `SendOtpAction` — chizi ke UI baraye countdown ha lazem dare.
 *
 * 🔴 `bypassCode` FAGHAT vaghti `otp.bypass` roshan ast meghdar dare
 * (dev). UI ba un code ro neshun mide + dokme ye por kardan e khodkar.
 * Tu production hamishe `null` ast.
 */
final readonly class SendOtpResultDto
{
    public function __construct(
        public int $expiresInSeconds,
        public int $resendCooldownSeconds,
        public ?string $bypassCode = null,
    ) {}
}
