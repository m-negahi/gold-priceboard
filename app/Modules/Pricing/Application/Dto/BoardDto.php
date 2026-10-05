<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

/**
 * Kol e tablo + meta-i ke reader lazem dare.
 *
 * `version` + `spreadVersion` ba ham ETag e `/api/board.json` ro misazan:
 * poll e bi-taghir 304 bar migardune jaye kol e payload.
 */
final readonly class BoardDto
{
    /** @param array<int, BoardRowDto> $rows */
    public function __construct(
        public array $rows,
        public int $version = 0,
        public int $spreadVersion = 0,
        /** `true` = az Postgres khunde shod chun store e dagh dar dastres nabud. */
        public bool $degraded = false,
    ) {}

    public function etag(): string
    {
        return sprintf('"b%d-s%d-%d"', $this->version, $this->spreadVersion, count($this->rows));
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /**
     * Radif ha be tafkik e symbol — shekl e jadval e moghayese
     * (radif = symbol، soton = provider).
     *
     * @return array<string, array<int, BoardRowDto>>
     */
    public function bySymbol(): array
    {
        $grouped = [];

        foreach ($this->rows as $row) {
            $grouped[$row->instrument->value][] = $row;
        }

        return $grouped;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'spreadVersion' => $this->spreadVersion,
            'degraded' => $this->degraded,
            'rows' => array_map(static fn (BoardRowDto $row): array => $row->toArray(), $this->rows),
        ];
    }
}
