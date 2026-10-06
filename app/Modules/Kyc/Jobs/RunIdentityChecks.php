<?php

declare(strict_types=1);

namespace App\Modules\Kyc\Jobs;

use App\Modules\Common\Enums\QueueName;
use App\Modules\Kyc\Application\Actions\RunIdentityChecksAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RunIdentityChecks implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $runId)
    {
        $this->onQueue(QueueName::Verification->value);
    }

    public function handle(RunIdentityChecksAction $action): void
    {
        $action->execute($this->runId);
    }
}
