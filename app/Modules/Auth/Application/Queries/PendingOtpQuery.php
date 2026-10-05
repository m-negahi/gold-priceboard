<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Queries;

use App\Modules\Auth\Application\Dto\OtpChallengeDto;
use App\Modules\Auth\Domain\Ports\OtpStorePort;
use App\Modules\Common\Helpers\PhoneHelper;

/**
 * Faghat MIKHUNE: na code misaze، na misuzune، na talash sabt mikone.
 *
 * Masraf: `LoginController::show()` — refresh e safhe bayad be step e code
 * bargarde ba shomarande ye server (na client)، chon client mitune tab ro
 * baste bashe ya cookie pak shode bashe.
 */
final readonly class PendingOtpQuery
{
    public function __construct(private OtpStorePort $store) {}

    /** `null` ya'ni chizi baraye edame nist: na code e zende، na cooldown. */
    public function challengeFor(string $phone): ?OtpChallengeDto
    {
        $normalized = PhoneHelper::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        $expiresIn = $this->store->ttlRemaining($normalized);
        $resendIn = $this->store->cooldownRemaining($normalized);

        if ($expiresIn === 0 && $resendIn === 0) {
            return null;
        }

        return new OtpChallengeDto($normalized, $resendIn, $expiresIn);
    }
}
