<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Access\Application\Queries\AccessQuery;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function authorization(): void
    {
        Gate::define('viewHorizon', fn (User $user): bool => app(AccessQuery::class)->allows($user, 'monitoring.view'));
        Horizon::auth(fn ($request): bool => Gate::allows('viewHorizon'));
    }
}
