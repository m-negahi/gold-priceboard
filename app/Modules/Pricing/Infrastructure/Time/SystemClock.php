<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Time;

use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Domain\ValueObjects\TickTime;
use Carbon\CarbonImmutable;

/** Sa'at e vaghei. Tu test ba `FrozenClock` avaz mishe. */
final readonly class SystemClock implements ClockPort
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    public function tick(int $gridSeconds = 15): TickTime
    {
        return TickTime::fromCarbon($this->now(), $gridSeconds);
    }
}
