<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;

/**
 * Yek gheymat ke rad shod — ba DALIL.
 *
 * Dalil string e azad nist: `stale` / `out_of_band` / `deviation` /
 * `non_positive`. `TickMonitor` ru hamin group mikone، va «chera in symbol
 * ru tablo nist» mishe yek negah be panel.
 */
final readonly class RejectionDto
{
    public function __construct(
        public string $providerKey,
        public Instrument $instrument,
        public Side $side,
        public string $reason,
        public ?int $minor = null,
        public ?int $lagSeconds = null,
    ) {}

    /** Dasteh ye dalil (ghesmat e ghabl az `:`). */
    public function category(): string
    {
        $position = strpos($this->reason, ':');

        return $position === false ? $this->reason : substr($this->reason, 0, $position);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->providerKey,
            'symbol' => $this->instrument->value,
            'side' => $this->side->value,
            'reason' => $this->reason,
            'category' => $this->category(),
            'minor' => $this->minor,
            'lag_seconds' => $this->lagSeconds,
        ];
    }
}
