<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Modules\Auth\Application\Dto\OtpEntryDto;
use App\Modules\Auth\Domain\Ports\OtpStorePort;

/**
 * Fake e in-memory baraye `OtpStorePort`.
 *
 * 🔴 Dars e `P1-26`: fake bayad GHARARDAD ro emza kone، na yek nasakhe ye
 * mehrabun tar. `OtpStoreContractTest` HAR raftar ro ru in VA ru
 * `RedisOtpStore` e vaghei ejra mikone — pas in do nemitunan saket vagera
 * shan.
 *
 * Zaman ba `advanceSeconds()` kontrol mishe (baraye test e TTL/cooldown).
 */
final class InMemoryOtpStore implements OtpStorePort
{
    /** @var array<string, array{hash: string, attempts: int, expiresAt: int}> */
    private array $entries = [];

    /** @var array<string, int> phone => expiresAt */
    private array $cooldowns = [];

    private int $now = 1_000_000;

    public function put(string $phone, string $codeHash, int $ttlSeconds): void
    {
        // Jaygozini ye KAMEL — shomaresh e ghabli ham sefr mishe (mesl e
        // `DEL` + `HMSET` e adapter e vaghei).
        $this->entries[$phone] = [
            'hash' => $codeHash,
            'attempts' => 0,
            'expiresAt' => $this->now + $ttlSeconds,
        ];
    }

    public function get(string $phone): ?OtpEntryDto
    {
        $entry = $this->live($phone);

        return $entry === null ? null : new OtpEntryDto($entry['hash'], $entry['attempts']);
    }

    public function recordFailedAttempt(string $phone): int
    {
        if ($this->live($phone) === null) {
            // Mesl e Lua ye adapter e vaghei: entry e nabude SAKHTE nemishe.
            return -1;
        }

        return ++$this->entries[$phone]['attempts'];
    }

    public function invalidate(string $phone): void
    {
        unset($this->entries[$phone]);
    }

    public function startCooldown(string $phone, int $seconds): void
    {
        $this->cooldowns[$phone] = $this->now + $seconds;
    }

    public function cooldownRemaining(string $phone): int
    {
        return max(0, ($this->cooldowns[$phone] ?? 0) - $this->now);
    }

    // ── Kontrol e zaman (faghat fake) ───────────────────────────────

    public function advanceSeconds(int $seconds): self
    {
        $this->now += $seconds;

        return $this;
    }

    /** @return array{hash: string, attempts: int, expiresAt: int}|null */
    private function live(string $phone): ?array
    {
        $entry = $this->entries[$phone] ?? null;

        if ($entry === null) {
            return null;
        }

        if ($entry['expiresAt'] <= $this->now) {
            unset($this->entries[$phone]);

            return null;
        }

        return $entry;
    }
}
