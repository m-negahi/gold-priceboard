<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

/**
 * Natije ye neveshtan tu yek sink (Redis / Postgres / Influx).
 *
 * Har sink JODAGANE sabt mishe chun har kodum jodagane ham mitune bimofte —
 * va «Influx ghat' bud vali tablo salem bud» yek halat e salem e system ast،
 * na yek shekast.
 */
final readonly class SinkResultDto
{
    public function __construct(
        public string $name,
        public bool $succeeded,
        public int $rows = 0,
        public ?string $error = null,
        public int $durationMs = 0,
        /** Khat hayi ke be spool rikhte shodan (faghat Influx). */
        public int $spooled = 0,
    ) {}

    public static function ok(string $name, int $rows, int $durationMs = 0): self
    {
        return new self($name, true, $rows, durationMs: $durationMs);
    }

    public static function failed(string $name, string $error, int $spooled = 0): self
    {
        return new self($name, false, error: $error, spooled: $spooled);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'sink' => $this->name,
            'ok' => $this->succeeded,
            'rows' => $this->rows,
            'error' => $this->error,
            'duration_ms' => $this->durationMs,
            'spooled' => $this->spooled,
        ];
    }
}
