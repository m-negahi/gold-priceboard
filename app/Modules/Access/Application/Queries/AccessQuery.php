<?php

declare(strict_types=1);

namespace App\Modules\Access\Application\Queries;

use App\Modules\Access\Domain\PermissionCatalog;
use App\Modules\Access\Infrastructure\Persistence\Models\Role;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class AccessQuery
{
    /** Profile e nabude = admin e ghadimi ba dastresi e kamel; profile e khali = hich ejaze. */
    public function permissions(?User $user): array
    {
        if ($user === null || ! $user->is_admin || ! $user->isActive()) {
            return [];
        }
        $profile = DB::table('admin_access_profiles')->where('user_id', $user->id)->first();
        if ($profile === null || $profile->full_access) {
            return ['*'];
        }

        return Role::query()->whereIn('id', DB::table('admin_role_assignments')->select('role_id')->where('user_id', $user->id))
            ->get()->flatMap(fn (Role $role): array => $role->permissions)->unique()->values()->all();
    }

    public function allows(?User $user, ?string $permission): bool
    {
        $permissions = $this->permissions($user);

        return in_array('*', $permissions, true) || ($permission !== null && in_array($permission, $permissions, true));
    }

    public function landingRoute(User $user): string
    {
        foreach (['panel.dashboard', 'panel.users.index', 'panel.tickets.index', 'panel.providers.index', 'panel.priorities.index', 'panel.finance.index', 'panel.plans.index', 'panel.sites.index', 'panel.media.index', 'panel.seo.index', 'panel.access.index', 'panel.monitor.ticks'] as $route) {
            if ($this->allows($user, PermissionCatalog::forRoute($route))) {
                return $route;
            }
        }

        return 'panel.no-access';
    }

    public function page(): array
    {
        return [
            'roles' => Role::query()->orderBy('name')->get(),
            'admins' => User::query()->where('is_admin', true)->orderBy('name')->get(['id', 'name', 'phone']),
            'profiles' => DB::table('admin_access_profiles')->get()->keyBy('user_id'),
            'assignments' => DB::table('admin_role_assignments')->get()->groupBy('user_id')->map(fn ($rows) => $rows->pluck('role_id')->all()),
            'audits' => DB::table('access_audits')->orderByDesc('id')->limit(20)->get(),
        ];
    }
}
