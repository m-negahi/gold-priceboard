<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

use App\Modules\Pricing\Domain\Enums\HealthState;
use Carbon\CarbonImmutable;

/** Natije ye yek tala'sh baraye gereftan e gheymat az yek provider. */
final readonly class ProviderHealthDto
{
    public function __construct(
        public string $providerKey,
        public bool $succeeded,
        public CarbonImmutable $attemptedAt,
        public int $latencyMs = 0,
        public int $symbolCount = 0,
        public int $rejectedCount = 0,
        public ?string $error = null,
        public int $consecutiveFailures = 0,
        /** `md5` e payload e kham — baraye provider hayi ke timestamp nemidan. */
        public ?string $payloadHash = null,
    ) {}

    public function state(): HealthState
    {
        return HealthState::fromFailures($this->consecutiveFailures);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider_key' => $this->providerKey,
            'succeeded' => $this->succeeded,
            'attempted_at' => $this->attemptedAt->toIso8601String(),
            'latency_ms' => $this->latencyMs,
            'symbol_count' => $this->symbolCount,
            'rejected_count' => $this->rejectedCount,
            'error' => $this->error,
            'consecutive_failures' => $this->consecutiveFailures,
            'health_state' => $this->state()->value,
            'payload_hash' => $this->payloadHash,
        ];
    }
}
