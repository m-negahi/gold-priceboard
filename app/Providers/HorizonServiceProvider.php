<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function authorization(): void
    {
        Gate::define('viewHorizon', fn (User $user): bool => $user->is_admin && $user->isActive());
        Horizon::auth(fn ($request): bool => Gate::allows('viewHorizon'));
    }
}
