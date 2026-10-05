<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Modules\Pricing\Application\Dto\ProviderHealthDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use RuntimeException;

/** Cache e in-memory — jaye `redis-prices` tu test. */
final class ArrayHotCache implements HotCachePort
{
    /** @var array<string, SnapshotDto> */
    private array $board = [];

    /** @var array<string, ProviderHealthDto> */
    private array $health = [];

    private int $version = 0;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $providers = null;

    private int $spreadVersion = 0;

    private bool $available = true;

    /** 🔴 Write ha miterkan vali read ha salem-and — bebin `failWritesOnly()`. */
    private bool $writesFail = false;

    public function board(): array
    {
        $this->guard();

        return $this->board;
    }

    public function putBoard(array $snapshots): void
    {
        $this->assertWritable();

        $this->guard();

        foreach ($snapshots as $snapshot) {
            $this->board[$snapshot->key()] = $snapshot;
        }

        $this->version++;
    }

    public function boardVersion(): int
    {
        $this->guard();

        return $this->version;
    }

    public function putProviders(array $providers): void
    {
        $this->assertWritable();

        $this->guard();

        $this->providers = $providers;
    }

    public function providers(): ?array
    {
        $this->guard();

        return $this->providers;
    }

    public function spreadVersion(): int
    {
        $this->guard();

        return $this->spreadVersion;
    }

    public function bumpSpreadVersion(): int
    {
        $this->assertWritable();

        $this->guard();

        return ++$this->spreadVersion;
    }

    public function putHealth(ProviderHealthDto $health): void
    {
        $this->assertWritable();

        $this->guard();

        $this->health[$health->providerKey] = $health;
    }

    public function health(): array
    {
        $this->guard();

        return $this->health;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    /** Shabih-sazi ye ghat'i ye Redis. */
    /**
     * 🔴 OOM: write ha rad mishan، read ha salem.
     *
     * In halat e FARZI nist — daghighan chizi-e ke `redis-prices` mikone.
     * Un instance `--maxmemory 64mb --maxmemory-policy noeviction` dare، pas
     * vaghti por she Redis ba `OOM command not allowed` write ha ro rad
     * mikone **vali read ha ro kamel javab mide** va `PING` ham salem ast.
     *
     * Natije: tablo YAKH mizane. `isAvailable()` `true` mide، `board()` data
     * bar migardune، `BoardQuery` `degraded=false` e'lam mikone — va faghat
     * chizi ke dad mizane `ageSeconds` ast ke bala mire. In bad tarin nou' e
     * ghat'i-e: hame chiz salem be nazar mirese.
     */
    public function failWritesOnly(): self
    {
        $this->writesFail = true;

        return $this;
    }

    private function assertWritable(): void
    {
        if ($this->writesFail) {
            throw new RuntimeException(
                'OOM command not allowed when used memory > \'maxmemory\'.',
            );
        }
    }

    public function breakIt(): self
    {
        $this->available = false;

        return $this;
    }

    public function repair(): self
    {
        $this->writesFail = false;

        $this->available = true;

        return $this;
    }

    /** Shabih-sazi ye evict shodan — hamun chizi ke `allkeys-lru` mikone. */
    public function evictEverything(): self
    {
        $this->board = [];

        return $this;
    }

    private function guard(): void
    {
        if (! $this->available) {
            throw new RuntimeException('Redis dar dastres nist (fake).');
        }
    }
}
