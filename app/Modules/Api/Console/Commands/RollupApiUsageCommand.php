<?php

declare(strict_types=1);

namespace App\Modules\Api\Console\Commands;

use App\Modules\Api\Application\Actions\RollupApiUsageAction;
use App\Modules\Api\Infrastructure\Usage\RedisUsageCounter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Rollup e usage e API be `api_usage_daily` (P6-08). Scheduler har 15
 * daghighe ejra mikone (`routes/console.php`).
 *
 * Pish-farz: EMRUZ + DIRUZ e Tehran — diruz ta chand daghighe ba'd az
 * nime-shab hanuz call/refund e akhar ro migire. Idempotent: har chand bar.
 */
final class RollupApiUsageCommand extends Command
{
    protected $signature = 'api:usage:rollup
        {--date= : akharin rooz (Y-m-d e Tehran)؛ pish-farz emruz}
        {--days=2 : chand rooz ta un rooz (be aghab)}';

    protected $description = 'Usage e ruzane ye API (Redis + ledger) ro tu api_usage_daily zakhire mikone.';

    public function handle(RollupApiUsageAction $rollup): int
    {
        $date = (string) ($this->option('date') ?? '');
        $end = $date === ''
            ? CarbonImmutable::now(RedisUsageCounter::TIMEZONE)
            : CarbonImmutable::createFromFormat('Y-m-d', $date, RedisUsageCounter::TIMEZONE);

        if ($end === null || ($date !== '' && $end->format('Y-m-d') !== $date)) {
            $this->error("Tarikh e na-motabar: {$date} (Y-m-d mikhad).");

            return self::INVALID;
        }

        $days = max(1, min(60, (int) $this->option('days')));

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $end->subDays($i)->format('Y-m-d');
            $count = $rollup->forDay($day);
            $this->info("Usage e {$day}: {$count} client be-rooz shod.");
        }

        return self::SUCCESS;
    }
}
