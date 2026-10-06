<?php

declare(strict_types=1);

namespace App\Modules\Access\Providers;

use App\Modules\Access\Application\Queries\AccessQuery;
use App\Modules\Access\Domain\PermissionCatalog;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AccessServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Access/Resources/views'), 'access');
        foreach (array_keys(PermissionCatalog::LABELS) as $permission) {
            Gate::define($permission, fn (User $user): bool => app(AccessQuery::class)->allows($user, $permission));
        }
    }
}
