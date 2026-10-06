<?php

declare(strict_types=1);

namespace App\Modules\Access\Application\Actions;

use App\Modules\Access\Application\Queries\AccessQuery;
use App\Modules\Access\Domain\PermissionCatalog;
use App\Modules\Access\Infrastructure\Persistence\Models\Role;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class ManageAccessAction
{
    public function __construct(private AccessQuery $access) {}

    public function saveRole(User $actor, ?int $id, array $input): Role
    {
        $this->authorize($actor);
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:100', Rule::unique('access_roles', 'name')->ignore($id)],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(PermissionCatalog::LABELS))],
        ], ['name.required' => 'نام نقش را وارد کنید.', 'name.unique' => 'این نام قبلاً ثبت شده است.', 'permissions.*.in' => 'دسترسی نامعتبر است.'])->validate();

        return DB::transaction(function () use ($actor, $id, $data): Role {
            $admins = User::query()->where('is_admin', true)->orderBy('id')->lockForUpdate()->get();
            $actor = $admins->firstWhere('id', $actor->id);
            abort_unless($actor instanceof User, 403);
            $this->authorize($actor);
            $role = $id === null ? new Role : Role::query()->lockForUpdate()->findOrFail($id);
            $before = $role->exists ? $role->only(['name', 'permissions']) : [];
            $role->fill(['name' => $data['name'], 'permissions' => array_values(array_unique($data['permissions']))])->save();
            $this->requireManager($admins);
            $this->audit($actor, 'role_saved', $role->id, $before, $role->only(['name', 'permissions']));

            return $role;
        });
    }

    public function assign(User $actor, int $userId, array $roleIds, bool $fullAccess): void
    {
        $this->authorize($actor);
        $data = Validator::make(['roles' => $roleIds], [
            'roles' => ['array'], 'roles.*' => ['integer', 'distinct', 'exists:access_roles,id'],
        ], ['roles.*.exists' => 'نقش انتخاب‌شده موجود نیست.'])->validate();

        DB::transaction(function () use ($actor, $userId, $data, $fullAccess): void {
            $admins = User::query()->where('is_admin', true)->orderBy('id')->lockForUpdate()->get();
            $actor = $admins->firstWhere('id', $actor->id);
            abort_unless($actor instanceof User, 403);
            $this->authorize($actor);
            abort_unless($admins->contains('id', $userId), 404);
            $before = ['profile' => DB::table('admin_access_profiles')->where('user_id', $userId)->first(), 'roles' => DB::table('admin_role_assignments')->where('user_id', $userId)->pluck('role_id')->all()];
            DB::table('admin_access_profiles')->upsert([['user_id' => $userId, 'full_access' => $fullAccess, 'created_at' => now(), 'updated_at' => now()]], ['user_id'], ['full_access', 'updated_at']);
            DB::table('admin_role_assignments')->where('user_id', $userId)->delete();
            foreach ($data['roles'] as $roleId) {
                DB::table('admin_role_assignments')->insert(['user_id' => $userId, 'role_id' => $roleId]);
            }
            $this->requireManager($admins);
            $this->audit($actor, 'roles_assigned', $userId, $before, ['full_access' => $fullAccess, 'roles' => $data['roles']]);
        });
    }

    public function deleteRole(User $actor, int $id): void
    {
        $this->authorize($actor);
        DB::transaction(function () use ($actor, $id): void {
            $admins = User::query()->where('is_admin', true)->orderBy('id')->lockForUpdate()->get();
            $actor = $admins->firstWhere('id', $actor->id);
            abort_unless($actor instanceof User, 403);
            $this->authorize($actor);
            $role = Role::query()->lockForUpdate()->findOrFail($id);
            if (DB::table('admin_role_assignments')->where('role_id', $id)->exists()) {
                throw ValidationException::withMessages(['role' => 'نقش استفاده‌شده حذف نمی‌شود؛ ابتدا تخصیص آن را تغییر دهید.']);
            }
            $before = $role->only(['name', 'permissions']);
            $role->delete();
            $this->audit($actor, 'role_deleted', $id, $before, []);
        });
    }

    private function authorize(User $actor): void
    {
        abort_unless($this->access->allows($actor, 'access.manage'), 403);
    }

    private function requireManager(iterable $admins): void
    {
        foreach ($admins as $admin) {
            if ($this->access->allows($admin, 'access.manage')) {
                return;
            }
        }
        throw ValidationException::withMessages(['access' => 'حداقل یک مدیر فعال باید دسترسی مدیریت نقش‌ها را داشته باشد.']);
    }

    private function audit(User $actor, string $action, int $subjectId, array $before, array $after): void
    {
        DB::table('access_audits')->insert([
            'actor_id' => $actor->id, 'action' => $action, 'subject_id' => $subjectId,
            'before' => json_encode($before, JSON_THROW_ON_ERROR), 'after' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
