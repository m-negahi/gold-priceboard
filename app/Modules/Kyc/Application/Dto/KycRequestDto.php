<?php

declare(strict_types=1);

namespace App\Modules\Kyc\Application\Dto;

/**
 * Form e hoghoghi e onboarding (P7-03) — GHABLAN validate va normalize
 * shode (raghame Latin، tarikh e Jalali e canonical `YYYY/MM/DD`).
 *
 * Hamin shekl (`toMeta`) tu `tickets.meta` mishine va estelam (P7-05) va
 * ta'eed (P7-06) az hamun mikhunan — kelid ha gharardad-and.
 */
final class KycRequestDto
{
    public function __construct(
        public readonly string $registeredName,
        public readonly string $companyNationalId,
        public readonly string $economicCode,
        public readonly string $postalCode,
        public readonly string $address,
        public readonly string $phone,
        public readonly string $representativeNationalCode,
        public readonly string $representativeBirthDate,
    ) {}

    /** @return array<string, string> */
    public function toMeta(): array
    {
        return [
            'registered_name' => $this->registeredName,
            'company_national_id' => $this->companyNationalId,
            'economic_code' => $this->economicCode,
            'postal_code' => $this->postalCode,
            'address' => $this->address,
            'phone' => $this->phone,
            'representative_national_code' => $this->representativeNationalCode,
            'representative_birth_date' => $this->representativeBirthDate,
        ];
    }

    /** @param array<string, mixed> $meta */
    public static function fromMeta(array $meta): self
    {
        return new self(
            registeredName: (string) ($meta['registered_name'] ?? ''),
            companyNationalId: (string) ($meta['company_national_id'] ?? ''),
            economicCode: (string) ($meta['economic_code'] ?? ''),
            postalCode: (string) ($meta['postal_code'] ?? ''),
            address: (string) ($meta['address'] ?? ''),
            phone: (string) ($meta['phone'] ?? ''),
            representativeNationalCode: (string) ($meta['representative_national_code'] ?? ''),
            representativeBirthDate: (string) ($meta['representative_birth_date'] ?? ''),
        );
    }
}
