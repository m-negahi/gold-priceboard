<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

use App\Modules\Pricing\Domain\ValueObjects\TickTime;

/**
 * Natije ye yek tick — chi gerefte shod, chi rad shod, har sink chi shod.
 *
 * In chizi-e ke `pricing:tick` chap mikone va tu `TickMonitor` e panel
 * neshun dade mishe. Bedun e un, «chera in symbol ru tablo nist» faghat ba
 * khundan e log e kham javab dade mishe.
 */
final readonly class TickReportDto
{
    /**
     * @param  array<string, ProviderHealthDto>  $health
     * @param  array<int, RejectionDto>  $rejections
     * @param  array<string, SinkResultDto>  $sinks
     * @param  array<string, array<int, string>>  $unmapped  providerKey => label ha
     */
    public function __construct(
        public TickTime $at,
        public int $accepted = 0,
        public array $health = [],
        public array $rejections = [],
        public array $sinks = [],
        public array $unmapped = [],
        public int $durationMs = 0,
        public bool $skipped = false,
        public ?string $skipReason = null,
    ) {}

    public function rejectedCount(): int
    {
        return count($this->rejections);
    }

    public function failedProviders(): int
    {
        return count(array_filter($this->health, static fn (ProviderHealthDto $h): bool => ! $h->succeeded));
    }

    public function failedSinks(): int
    {
        return count(array_filter($this->sinks, static fn (SinkResultDto $s): bool => ! $s->succeeded));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'at' => $this->at->toString(),
            'accepted' => $this->accepted,
            'rejected' => $this->rejectedCount(),
            'duration_ms' => $this->durationMs,
            'skipped' => $this->skipped,
            'skip_reason' => $this->skipReason,
            'providers' => array_map(
                static fn (ProviderHealthDto $h): array => $h->toArray(),
                $this->health,
            ),
            'sinks' => array_map(
                static fn (SinkResultDto $s): array => $s->toArray(),
                $this->sinks,
            ),
            'rejections' => array_map(
                static fn (RejectionDto $r): array => $r->toArray(),
                $this->rejections,
            ),
            'unmapped' => $this->unmapped,
        ];
    }
}
