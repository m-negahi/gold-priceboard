<?php

declare(strict_types=1);

namespace App\Modules\Panel\Providers;

use App\Modules\Common\Providers\ModuleServiceProvider;
use App\Modules\Panel\Console\Commands\SeedDashboardCommand;

final class PanelServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Panel';
    }

    /** @return list<class-string> */
    protected function consoleCommands(): array
    {
        return [SeedDashboardCommand::class];
    }
}
