<?php

declare(strict_types=1);

namespace App\Modules\Api\Providers;

use App\Modules\Api\Console\Commands\DrainApiRefundsCommand;
use App\Modules\Api\Console\Commands\RollupApiUsageCommand;
use Illuminate\Support\ServiceProvider;

/**
 * Provider e module e Api — fe'lan faghat command ha (P6-08). Route ha tu
 * `routes/api.php` (ADR-0027)، bind e khassi nadare.
 */
final class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RollupApiUsageCommand::class,
                DrainApiRefundsCommand::class,
            ]);
        }
    }
}
