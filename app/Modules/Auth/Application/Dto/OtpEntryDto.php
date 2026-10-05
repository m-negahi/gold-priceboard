<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Dto;

/**
 * Yek OTP e zende tu store.
 *
 * `codeHash` — HMAC، hichvaght khod e code (bebin `OtpCode`).
 * `attempts` — tedad e talash haye GHALAT ta alan.
 */
final readonly class OtpEntryDto
{
    public function __construct(
        public string $codeHash,
        public int $attempts = 0,
    ) {}
}
