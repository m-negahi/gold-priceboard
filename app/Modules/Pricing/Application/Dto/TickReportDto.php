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
            /*
             * 🔴 `(object)` ru map haye KELID-DAR — ejbari، na zibasazi.
             *
             * `json_encode` yek array e PHP e khali ro `[]` mikone va yek
             * array ba kelid e string ro `{}`. Pas `sinks` vaghti por bud
             * object mishod va vaghti khali bud ARRAY — do shekl e
             * motefavet baraye yek field. Har client e type-dar (va Phase 4
             * in payload ro MIFROUSHE) ba un misheknae، va `sinks.influx`
             * tu yek tick e rad-shode aslan ghabl e address dadan nabud.
             *
             * `rejections` amdan LIST mimune — un vaghe'an yek lista-st.
             */
            'providers' => (object) array_map(
                static fn (ProviderHealthDto $h): array => $h->toArray(),
                $this->health,
            ),
            'sinks' => (object) array_map(
                static fn (SinkResultDto $s): array => $s->toArray(),
                $this->sinks,
            ),
            'rejections' => array_values(array_map(
                static fn (RejectionDto $r): array => $r->toArray(),
                $this->rejections,
            )),
            'unmapped' => (object) $this->unmapped,
        ];
    }
}
