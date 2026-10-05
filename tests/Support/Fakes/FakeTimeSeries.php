<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Modules\Pricing\Application\Dto\CandleDto;
use App\Modules\Pricing\Application\Dto\CandleRequestDto;
use App\Modules\Pricing\Domain\Ports\TimeSeriesPort;
use App\Modules\Pricing\Domain\ValueObjects\TickTime;
use RuntimeException;

/** Time-series e in-memory — jaye Influx tu test. */
final class FakeTimeSeries implements TimeSeriesPort
{
    /** @var array<int, array{at: int, snapshots: array<int, mixed>}> */
    private array $writes = [];

    /** @var array<int, CandleDto> */
    private array $candles = [];

    private bool $available = true;

    /** Write rad mishe vali query salem — bebin `failWritesOnly()`. */
    private bool $writesFail = false;

    public function writeTicks(array $snapshots, TickTime $at): void
    {
        if ($this->writesFail) {
            // Halat e VAGHEI: Influx yek write ro ba 422 rad mikone (timestamp
            // birun e retention، type mismatch e field) dar hali ke query ha
            // kamelan salem-and. Tu `P1` hamin ro zende didim.
            throw new RuntimeException('HTTP 422: points beyond retention period');
        }

        $this->guard();

        $this->writes[] = ['at' => $at->timestamp(), 'snapshots' => $snapshots];
    }

    public function candles(CandleRequestDto $request): array
    {
        $this->guard();

        return $this->candles;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    /** @param array<int, CandleDto> $candles */
    public function willReturn(array $candles): self
    {
        $this->candles = $candles;

        return $this;
    }

    public function breakIt(): self
    {
        $this->available = false;

        return $this;
    }

    /** Write rad mishe، query salem. */
    public function failWritesOnly(): self
    {
        $this->writesFail = true;

        return $this;
    }

    public function repair(): self
    {
        $this->writesFail = false;

        $this->available = true;

        return $this;
    }

    /** @return array<int, array{at: int, snapshots: array<int, mixed>}> */
    public function writes(): array
    {
        return $this->writes;
    }

    public function writeCount(): int
    {
        return count($this->writes);
    }

    /** Hame ye timestamp haye neveshte-shode — baraye test e idempotency. */
    public function writtenTimestamps(): array
    {
        return array_map(static fn (array $w): int => $w['at'], $this->writes);
    }

    private function guard(): void
    {
        if (! $this->available) {
            throw new RuntimeException('Influx dar dastres nist (fake).');
        }
    }
}
