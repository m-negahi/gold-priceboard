<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Dto;

/**
 * Vaziat e code e ZENDE ye yek shomare — chizi ke safhe ye login ba'd az
 * refresh mikhad ta be jaye shoru' az aval، step e code ro ba shomarande ye
 * DOROST e server neshun bede.
 */
final readonly class OtpChallengeDto
{
    public function __construct(
        public string $phone,
        public int $resendInSeconds,
        public int $expiresInSeconds,
    ) {}
}
