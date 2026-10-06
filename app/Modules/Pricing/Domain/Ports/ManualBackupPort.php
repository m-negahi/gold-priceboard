<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Ports;

use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\ValueObjects\TickTime;

interface ManualBackupPort
{
    /** @param array<string, SnapshotDto> $snapshots */
    public function sync(array $snapshots, TickTime $at): int;
}
