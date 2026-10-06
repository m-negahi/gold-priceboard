<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 *
 * Default: karbar e OTP-i ye vaghei — phone e verify-shode، BEDUN e
 * password/email/name. Un se ta ekhtiari-and va state e jodagane daran.
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            // `unique()` e faker + format e E.164 e vaghei ye Irani.
            'phone' => '+989'.fake()->unique()->numerify('#########'),
            'phone_verified_at' => now(),
            'name' => null,
            'email' => null,
            'password' => null,
        ];
    }

    /*
     * 🔴 Closure e `state()` NABAYAD `static` bashe: Factory un ro `bindTo($this)`
     * mikone va PHP 8.5 ru closure e static ErrorException mide (avvalin
     * masraf e `named()` tu P6-02 gereftesh).
     */
    public function unverified(): static
    {
        return $this->state(fn (): array => ['phone_verified_at' => null]);
    }

    public function admin(): static
    {
        // 🔴 `is_admin` fillable NIST — hamin ja ham bayad sarih set beshe.
        return $this->afterCreating(static function (User $user): void {
            $user->forceFill(['is_admin' => true])->save();
        });
    }

    public function named(string $name): static
    {
        return $this->state(fn (): array => ['name' => $name]);
    }
}
