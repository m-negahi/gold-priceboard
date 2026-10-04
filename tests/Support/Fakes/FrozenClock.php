<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Domain\ValueObjects\TickTime;
use Carbon\CarbonImmutable;

/** Sa'at e sabet — test e staleness bedun e in random fail mikone. */
final class FrozenClock implements ClockPort
{
    private CarbonImmutable $now;

    public function __construct(string|CarbonImmutable $now = '2026-10-04T12:21:30Z')
    {
        $this->now = $now instanceof CarbonImmutable ? $now : CarbonImmutable::parse($now);
    }

    public function now(): CarbonImmutable
    {
        return $this->now;
    }

    public function tick(int $gridSeconds = 15): TickTime
    {
        return TickTime::fromCarbon($this->now, $gridSeconds);
    }

    /** Jolo bordan e zaman — baraye test e chand tick e poshte sar e ham. */
    public function advance(int $seconds): self
    {
        $this->now = $this->now->addSeconds($seconds);

        return $this;
    }
}
