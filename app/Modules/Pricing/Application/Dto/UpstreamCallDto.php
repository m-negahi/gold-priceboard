<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

use Carbon\CarbonImmutable;

/**
 * Yek call e HTTP e MA be yek provider e gheymat (P4-14).
 *
 * ── Chera joda az `ProviderHealthDto` ───────────────────────────────
 * Health yek KHOLASE per-tick ast (akharin natije ye har provider).
 * In Dto har TALASH ro joda sabt mikone — attempt e 1 e shekast-khorde va
 * attempt e 2 e movafagh DO radif-and. Baraye debug e khata ye provider
 * daghighan hamin tarikh-che lazem e، na kholase.
 */
final readonly class UpstreamCallDto
{
    public function __construct(
        public string $providerKey,
        public bool $succeeded,
        public CarbonImmutable $at,
        public string $method,
        public string $url,
        /** `null` ya'ni aslan pasokh e HTTP nayumad (khata ye ettesal). */
        public ?int $status = null,
        public int $latencyMs = 0,
        public int $attempt = 1,
        public ?string $error = null,
        /** Chand symbol az payload dar umad — `0` ba status 200 ya'ni parse kharab e. */
        public int $symbolCount = 0,
        public ?int $bytes = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->providerKey,
            'succeeded' => $this->succeeded,
            'at' => $this->at->toIso8601String(),
            'method' => $this->method,
            'url' => $this->url,
            'status' => $this->status,
            'latency_ms' => $this->latencyMs,
            'attempt' => $this->attempt,
            'error' => $this->error,
            'symbol_count' => $this->symbolCount,
            'bytes' => $this->bytes,
        ];
    }

    /** @param array<string, mixed> $data hamun shekl e `toArray()` */
    public static function fromArray(array $data): self
    {
        return new self(
            providerKey: (string) ($data['provider'] ?? ''),
            succeeded: (bool) ($data['succeeded'] ?? false),
            at: CarbonImmutable::parse((string) ($data['at'] ?? 'now')),
            method: (string) ($data['method'] ?? 'GET'),
            url: (string) ($data['url'] ?? ''),
            status: isset($data['status']) ? (int) $data['status'] : null,
            latencyMs: (int) ($data['latency_ms'] ?? 0),
            attempt: (int) ($data['attempt'] ?? 1),
            error: isset($data['error']) ? (string) $data['error'] : null,
            symbolCount: (int) ($data['symbol_count'] ?? 0),
            bytes: isset($data['bytes']) ? (int) $data['bytes'] : null,
        );
    }
}
