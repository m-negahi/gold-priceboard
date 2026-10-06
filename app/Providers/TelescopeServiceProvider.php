<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

final class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        // Local ham secret nadare: OTP, cookie va emza nabayad sabt beshan.
        Telescope::hideRequestParameters(['_token', 'password', 'code', 'secret', 'token']);
        Telescope::hideRequestHeaders(['cookie', 'authorization', 'x-csrf-token', 'x-xsrf-token', 'x-pb-signature']);
        Telescope::hideResponseParameters(['secret', 'code', 'devCode']);
    }

    protected function authorization(): void
    {
        Gate::define('viewTelescope', fn (User $user): bool => $user->is_admin && $user->isActive());
        Telescope::auth(fn ($request): bool => Gate::allows('viewTelescope'));
    }
}
