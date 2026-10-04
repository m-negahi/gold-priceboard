<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\Ports\SnapshotStorePort;
use RuntimeException;

/** Store e in-memory — jaye Postgres tu test e pipeline. */
final class InMemorySnapshotStore implements SnapshotStorePort
{
    /** @var array<string, SnapshotDto> */
    private array $snapshots = [];

    /** @var array<int, SnapshotDto> */
    private array $changes = [];

    private bool $broken = false;

    /** @param array<int, SnapshotDto> $initial */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $snapshot) {
            $this->snapshots[$snapshot->key()] = $snapshot;
        }
    }

    public function all(): array
    {
        $this->guard();

        return $this->snapshots;
    }

    public function save(array $snapshots): void
    {
        $this->guard();

        foreach ($snapshots as $snapshot) {
            $this->snapshots[$snapshot->key()] = $snapshot;
        }
    }

    public function appendChanges(array $changed): void
    {
        $this->guard();

        foreach ($changed as $snapshot) {
            $this->changes[] = $snapshot;
        }
    }

    /** Shabih-sazi ye ghat'i ye Postgres. */
    public function breakIt(): self
    {
        $this->broken = true;

        return $this;
    }

    public function repair(): self
    {
        $this->broken = false;

        return $this;
    }

    /** @return array<int, SnapshotDto> */
    public function changes(): array
    {
        return $this->changes;
    }

    public function count(): int
    {
        return count($this->snapshots);
    }

    private function guard(): void
    {
        if ($this->broken) {
            throw new RuntimeException('Postgres dar dastres nist (fake).');
        }
    }
}
