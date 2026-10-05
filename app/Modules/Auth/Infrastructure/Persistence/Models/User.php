<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Models;

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
    ];

    protected $fillable = [
        'phone',
        'name',
        'email',
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
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
