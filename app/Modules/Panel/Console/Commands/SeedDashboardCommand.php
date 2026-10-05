<?php

declare(strict_types=1);

namespace App\Modules\Panel\Console\Commands;

use App\Modules\Common\Helpers\Env;
use App\Modules\Panel\Actions\SeedDashboardDemoAction;
use Illuminate\Console\Command;

/**
 * `panel:seed-dashboard`: dade-ye namayeshi-ye mizkar baraye Playwright (yek gheymat-e taze, yek
 * stale, yek manba' dar cooldown); `--clean` hamun ha ro pak mikone. Faghat local/testing —
 * mesle `otp:flush` command-e, na endpoint, ta hich vaght ta production zende namune.
 */
final class SeedDashboardCommand extends Command
{
    protected $signature = 'panel:seed-dashboard {--clean : Remove the seeded rows instead of creating them}';

    protected $description = 'Seed (or clean) the dashboard demo rows the e2e spec relies on (local/testing only)';

    public function __construct(private readonly SeedDashboardDemoAction $seed)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! Env::isDev($this->laravel)) {
            $this->components->error('panel:seed-dashboard only runs in local/testing.');

            return self::FAILURE;
        }

        if ($this->option('clean')) {
            $this->seed->cleanup();
            $this->components->info('Dashboard demo rows removed.');

            return self::SUCCESS;
        }

        $result = $this->seed->handle();

        $this->components->info(sprintf('Dashboard demo rows seeded: %s, %s.', implode(', ', $result['instruments']), $result['source']));

        return self::SUCCESS;
    }
}
