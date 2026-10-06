<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Modules\Pricing\Domain\Ports\ManualBackupPort;
use App\Modules\Pricing\Domain\ValueObjects\TickTime;

final class FakeManualBackup implements ManualBackupPort
{
    public array $snapshots = [];

    public bool $fails = false;

    public function sync(array $snapshots, TickTime $at): int
    {
        if ($this->fails) {
            throw new \RuntimeException('backup e test shekast khord.');
        }

        $this->snapshots = $snapshots;

        return count($snapshots);
    }
}
