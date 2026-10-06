<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Actions;

use App\Modules\Auth\Domain\Enums\CustomerType;
use App\Modules\Auth\Domain\Enums\UserLevel;
use App\Modules\Auth\Domain\Exceptions\UserAdminException;
use App\Modules\Auth\Http\Support\UserProfileRules;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Common\Helpers\PhoneHelper;
use App\Modules\Kyc\Application\Actions\QueueIdentityChecksAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final readonly class SaveUserProfileAction
{
    public function __construct(private UserAdminAction $admin, private QueueIdentityChecksAction $checks) {}

    /** @param array<string, mixed> $data */
    public function execute(?User $target, array $data, User $actor): User
    {
        abort_unless($actor->is_admin, 403);
        if ($target === null && ($data['role'] ?? 'customer') === 'admin') {
            Gate::forUser($actor)->authorize('access.manage');
        }
        $phone = PhoneHelper::normalize((string) $data['phone']);
        if ($phone === null) {
            throw UserAdminException::invalidPhone();
        }

        return DB::transaction(function () use ($target, $data, $actor, $phone): User {
            $admins = User::query()->where('is_admin', true)->orderBy('id')->lockForUpdate()->get();
            $freshActor = $admins->firstWhere('id', $actor->id);
            abort_unless($freshActor instanceof User, 403);
            Gate::forUser($freshActor)->authorize('users.manage');
            $user = $target === null ? null : User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($user?->is_admin || ($target === null && ($data['role'] ?? 'customer') === 'admin')) {
                Gate::forUser($freshActor)->authorize('access.manage');
            }
            if (User::query()->where('phone', $phone)->when($user !== null, fn ($query) => $query->whereKeyNot($user->id))->exists()) {
                throw UserAdminException::duplicatePhone();
            }
            $before = $user === null ? [] : $this->snapshot($user);
            $user ??= $this->admin->create($phone, $data['name'] ?? null, ($data['role'] ?? 'customer') === 'admin', CustomerType::from($data['customer_type']));
            $approvedIdentity = $user->invoice_profile;
            $identityBefore = $this->identitySnapshot($user);
            $profile = array_intersect_key($data, array_flip(UserProfileRules::PROFILE_FIELDS));
            if ($data['customer_type'] === 'individual') {
                $profile = array_diff_key($profile, array_flip(['registered_name', 'company_national_id', 'economic_code', 'representative_national_code', 'representative_birth_date']));
            } else {
                unset($profile['birth_date']);
            }
            $changedPhone = $user->phone !== $phone;
            $user->forceFill([
                'phone' => $phone, 'name' => $data['name'] ?? null, 'email' => $data['email'] ?? null,
                'customer_type' => $data['customer_type'], 'national_code' => $data['customer_type'] === 'individual' ? ($data['national_code'] ?? null) : null,
                'level' => $data['level'] ?? $user->level, 'identity_profile' => $profile,
                ...($changedPhone ? ['phone_verified_at' => null, 'remember_token' => Str::random(60)] : []),
            ]);
            if (is_array($approvedIdentity) && $approvedIdentity !== [] && $identityBefore !== $this->identitySnapshot($user)) {
                $user->forceFill(['invoice_profile' => null, 'level' => UserLevel::Basic]);
            }
            $user->save();
            $after = $this->snapshot($user);
            if ($before !== $after) {
                $auditId = DB::table('user_profile_audits')->insertGetId([
                    'user_id' => $user->id, 'actor_id' => $actor->id,
                    'before' => json_encode($before, JSON_THROW_ON_ERROR), 'after' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => now(),
                ]);
                $this->checks->execute($user->id, [...$profile, 'mobile' => $phone, 'national_code' => $user->national_code], 'profile:'.$auditId, actorId: $actor->id);
            }

            return $user;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(User $user): array
    {
        return ['is_admin' => $user->is_admin, 'phone' => $user->phone, 'phone_verified_at' => $user->phone_verified_at?->toIso8601String(), 'name' => $user->name, 'email' => $user->email, 'customer_type' => $user->customer_type->value, 'national_code' => $user->national_code, 'level' => $user->level->value, 'identity_profile' => $user->identity_profile, 'invoice_profile' => $user->invoice_profile];
    }

    /** @return array<string, string> */
    private function identitySnapshot(User $user): array
    {
        $fields = ['phone' => $user->phone, 'name' => (string) $user->name, 'customer_type' => $user->customer_type->value,
            'national_code' => $user->customer_type === CustomerType::Individual ? (string) $user->national_code : ''];
        foreach (UserProfileRules::PROFILE_FIELDS as $field) {
            $fields[$field] = (string) ($user->identity_profile[$field] ?? '');
        }

        return $fields;
    }
}
