<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Pipeline\Stages;

use App\Modules\Pricing\Application\Dto\SinkResultDto;
use App\Modules\Pricing\Application\Pipeline\Stage;
use App\Modules\Pricing\Application\Pipeline\TickContext;
use App\Modules\Pricing\Domain\Enums\StagePolicy;
use App\Modules\Pricing\Domain\Ports\ManualBackupPort;
use Throwable;

final readonly class ManualBackupStage implements Stage
{
    public function __construct(private ManualBackupPort $backup) {}

    public function name(): string
    {
        return 'manual_backup';
    }

    public function policy(): StagePolicy
    {
        return StagePolicy::Isolated;
    }

    public function handle(TickContext $context): void
    {
        $snapshots = [];

        foreach ($context->snapshots as $snapshot) {
            $snapshots[$snapshot->key()] = $snapshot;
        }

        try {
            $count = $this->backup->sync($snapshots, $context->at);
            $context->recordSink(SinkResultDto::ok('manual_backup', $count, 0));
        } catch (Throwable $exception) {
            $context->recordSink(SinkResultDto::failed('manual_backup', mb_substr($exception->getMessage(), 0, 480)));
        }
    }
}
