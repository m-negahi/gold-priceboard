<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Models;

use App\Modules\Auth\Domain\Enums\CustomerType;
use App\Modules\Auth\Domain\Enums\UserLevel;
use App\Modules\Auth\Domain\Enums\UserStatus;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Karbar — hoviat e login `phone` ast (E.164)، na email.
 *
 * ── 🔴 `is_admin` FILLABLE NIST. Hich vaght. ─────────────────────────
 * Har mass-assignment e `is_admin` yek privilege escalation e amade-ast:
 * yek `$user->update($request->all())` e bi-deghat tu HAR controller e
 * ayande kafi-e ta har kasi ke yek field e `is_admin=1` be form ezafe kone
 * admin beshe. Set kardanesh FAGHAT ba `forceFill()` e sarih (ya seeder) —
 * jayi ke taghir e naghsh yek TASMIM ast، na yek form.
 *
 * 🔴 `status`/`level`/`disabled_*` ham FILLABLE NISTAN (P6-01): disable
 * kardan va ertegha ye sath TASMIM e admin ast (`UserAdminAction`)، na
 * form — dalil hamun dalil e `is_admin`.
 *
 * `password` nullable ast: login ba OTP ast va karbar e ma'muli aslan
 * password NADARE.
 *
 * @property int $id
 * @property string $phone
 * @property CarbonImmutable|null $phone_verified_at
 * @property bool $is_admin
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $name
 * @property string|null $email
 * @property string|null $password
 * @property UserStatus $status
 * @property CarbonImmutable|null $disabled_at
 * @property string|null $disabled_reason
 * @property UserLevel $level
 * @property CustomerType $customer_type
 * @property string|null $national_code
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * 🔴 Default e sath e MODEL، na faghat DB.
     *
     * Bedun e in، `User::create([...])->is_admin` ta vaghti `refresh()`
     * nashode `null` ast (default e DB be instance e dar-hafeze
     * barnemigarde) — va yek check e `if ($user->is_admin === false)` ru
     * instance ye taze javab e GHALAT mide.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_admin' => false,
        'status' => 'active',
        'level' => 'basic',
        'customer_type' => 'individual',
    ];

    protected $fillable = [
        'phone',
        'name',
        'email',
        'customer_type',
        'national_code',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'email_verified_at' => 'immutable_datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'disabled_at' => 'immutable_datetime',
            'level' => UserLevel::class,
            'customer_type' => CustomerType::class,
        ];
    }

    /** Karbar e disable na login dare na API (P6-05). */
    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
