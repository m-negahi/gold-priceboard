<?php

declare(strict_types=1);

namespace App\Modules\Kyc\Domain\Ports;

use App\Modules\Kyc\Application\Dto\VerificationResultDto;

interface IdentityVerificationPort
{
    /** @param array<string, string> $input */
    public function verify(string $check, array $input): VerificationResultDto;
}
