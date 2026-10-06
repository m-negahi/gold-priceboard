<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Actions;

use App\Modules\Access\Application\Queries\AccessQuery;
use App\Modules\Auth\Domain\Enums\CustomerType;
use App\Modules\Auth\Domain\Enums\UserStatus;
use App\Modules\Auth\Domain\Exceptions\UserAdminException;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Common\Helpers\PhoneHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Modiriat e karbar tavasot e ADMIN (Phase 6): sakht، taghir e naghsh،
 * disable/enable.
 *
 * 🔴 `is_admin` FAGHAT inja (va seeder) ba `forceFill` set mishe — hamun
 * gharardad e docblock e `User`: taghir e naghsh yek TASMIM ast، na form.
 *
 * ── Do mohafez e lockout ────────────────────────────────────────────
 * 1. Admin naghsh e KHODESH ro nemigire (yek click e eshtebah = birun
 *    oftadan az panel).
 * 2. Akharin admin e FA'AL demote nemishe — panel bedun e admin faghat ba
 *    dastresi ye DB/seeder bar migarde.
 */
final readonly class UserAdminAction
{
    public function __construct(private AccessQuery $access) {}

    /**
     * Karbar e jadid ba mobile. `phone_verified_at` null mimune ta avvalin
     * login e OTP — admin malekiat e shomare ro ta'eed nakarde.
     *
     * @throws UserAdminException
     */
    public function create(string $phone, ?string $name, bool $isAdmin, CustomerType $type): User
    {
        $normalized = PhoneHelper::normalize($phone);

        if ($normalized === null) {
            throw UserAdminException::invalidPhone();
        }

        if (User::query()->where('phone', $normalized)->exists()) {
            throw UserAdminException::duplicatePhone();
        }

        try {
            return DB::transaction(static function () use ($normalized, $name, $isAdmin, $type): User {
                $user = User::query()->create([
                    'phone' => $normalized,
                    'name' => $name === null || trim($name) === '' ? null : trim($name),
                    'customer_type' => $type,
                ]);

                $user->forceFill(['is_admin' => $isAdmin])->save();

                return $user;
            });
        } catch (QueryException) {
            // Do sakht e hamzaman ba yek shomare: unique e DB barande ast.
            throw UserAdminException::duplicatePhone();
        }
    }

    /** @throws UserAdminException */
    public function setRole(User $target, bool $isAdmin, User $actor): User
    {
        return DB::transaction(function () use ($target, $isAdmin, $actor): User {
            $admins = User::query()->where('is_admin', true)->orderBy('id')->lockForUpdate()->get();
            $locked = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($locked->is_admin === $isAdmin) {
                return $locked;
            }
            if (! $isAdmin) {
                if ($locked->id === $actor->id) {
                    throw UserAdminException::selfDemotion();
                }
                $this->assertAnotherActiveAdmin($locked);
            }
            $freshActor = $admins->firstWhere('id', $actor->id);
            abort_unless($freshActor instanceof User, 403);
            Gate::forUser($freshActor)->authorize('access.manage');
            $before = $this->auditSnapshot($locked);
            $locked->forceFill(['is_admin' => $isAdmin])->save();
            $this->assertActiveAccessManager();
            $this->recordAudit($locked, $freshActor, $before);

            return $target->refresh();
        });
    }

    /**
     * Disable (P6-05). Khod e enforce tu `EnsureActiveUser` (web) va
     * `ApiHmacAuth` (API) ast — inja faghat vaziat sabt mishe.
     *
     * `remember_token` charkhunde mishe ta cookie e «remember me» e ghadimi
     * dige karbar ro bar nagardune (laye ye dovom؛ middleware khodesh ham
     * har request ro check mikone).
     *
     * @throws UserAdminException
     */
    public function disable(User $target, string $reason, User $actor): User
    {
        return DB::transaction(function () use ($target, $reason, $actor): User {
            $admins = User::query()->where('is_admin', true)->orderBy('id')->lockForUpdate()->get();
            $locked = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($locked->id === $actor->id) {
                throw UserAdminException::selfDisable();
            }
            if ($locked->is_admin) {
                $this->assertAnotherActiveAdmin($locked);
            }
            $freshActor = $admins->firstWhere('id', $actor->id);
            abort_unless($freshActor instanceof User, 403);
            Gate::forUser($freshActor)->authorize($locked->is_admin ? 'access.manage' : 'users.manage');
            $before = $this->auditSnapshot($locked);
            $locked->forceFill([
                'status' => UserStatus::Disabled, 'disabled_at' => CarbonImmutable::now(),
                'disabled_reason' => trim($reason), 'remember_token' => Str::random(60),
            ])->save();
            if ($locked->is_admin) {
                $this->assertActiveAccessManager();
            }
            $this->recordAudit($locked, $freshActor, $before);

            return $target->refresh();
        });
    }

    public function enable(User $target, User $actor): User
    {
        return DB::transaction(function () use ($target, $actor): User {
            $admins = User::query()->where('is_admin', true)->orderBy('id')->lockForUpdate()->get();
            $locked = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $freshActor = $admins->firstWhere('id', $actor->id);
            abort_unless($freshActor instanceof User, 403);
            Gate::forUser($freshActor)->authorize($locked->is_admin ? 'access.manage' : 'users.manage');
            $before = $this->auditSnapshot($locked);
            $locked->forceFill(['status' => UserStatus::Active, 'disabled_at' => null, 'disabled_reason' => null])->save();
            $this->recordAudit($locked, $freshActor, $before);

            return $target->refresh();
        });
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(User $user): array
    {
        return $user->only(['is_admin', 'status', 'disabled_at', 'disabled_reason']);
    }

    /** @param array<string, mixed> $before */
    private function recordAudit(User $user, User $actor, array $before): void
    {
        DB::table('user_profile_audits')->insert(['user_id' => $user->id, 'actor_id' => $actor->id,
            'before' => json_encode($before, JSON_THROW_ON_ERROR), 'after' => json_encode($this->auditSnapshot($user), JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function assertActiveAccessManager(): void
    {
        foreach (User::query()->where('is_admin', true)->where('status', UserStatus::Active)->get() as $admin) {
            if ($this->access->allows($admin, 'access.manage')) {
                return;
            }
        }
        throw UserAdminException::lastAccessManager();
    }

    /** @throws UserAdminException agar $target akharin admin e fa'al bashe */
    private function assertAnotherActiveAdmin(User $target): void
    {
        $others = User::query()
            ->where('is_admin', true)
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($target->id)
            ->exists();

        if (! $others) {
            throw UserAdminException::lastAdmin();
        }
    }
}
