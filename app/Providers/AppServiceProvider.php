<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\Telescope;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Telescope dev dependency ast; production bedun e require-dev ham boot mishe.
        if ($this->app->environment('local', 'testing') && class_exists(Telescope::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }
}
