<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Actions;

use App\Modules\Auth\Domain\Enums\CustomerType;
use App\Modules\Auth\Domain\Enums\UserStatus;
use App\Modules\Auth\Domain\Exceptions\UserAdminException;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Common\Helpers\PhoneHelper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Modiriat e karbar tavasot e ADMIN (Phase 6): sakht + taghir e naghsh.
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
        if ($target->is_admin === $isAdmin) {
            return $target;
        }

        if (! $isAdmin) {
            if ($target->id === $actor->id) {
                throw UserAdminException::selfDemotion();
            }

            $this->assertAnotherActiveAdmin($target);
        }

        $target->forceFill(['is_admin' => $isAdmin])->save();

        return $target;
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
