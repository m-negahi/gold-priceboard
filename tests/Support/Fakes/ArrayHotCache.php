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

    public function board(): array
    {
        $this->guard();

        return $this->board;
    }

    public function putBoard(array $snapshots): void
    {
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
        $this->guard();

        return ++$this->spreadVersion;
    }

    public function putHealth(ProviderHealthDto $health): void
    {
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
    public function breakIt(): self
    {
        $this->available = false;

        return $this;
    }

    public function repair(): self
    {
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
