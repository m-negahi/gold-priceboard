<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Common\Helpers\PhoneHelper;
use Illuminate\Database\Seeder;

/**
 * Admin e avvaliye — sahebe proje.
 *
 * Badan modiriat e admin ha ba CRUD e panel anjam mishe (khaste ye sahebe
 * proje، 2026-10-06)؛ in seeder faghat «avvalin kelid» ro misaze ta betune
 * vared e panel beshe.
 *
 * Idempotent va GHEYR-mokhareb: age karbar hast، faghat `is_admin` zemanat
 * mishe؛ name/email e por-shode bazneveshte NEMISHAN (shayad tu panel avaz
 * karde bashe).
 */
final class AdminSeeder extends Seeder
{
    private const ADMINS = [
        [
            'phone' => '09128452427',
            'name' => 'Morteza Negahi',
            'email' => 'morteza.negaih@gmail.com',
        ],
    ];

    public function run(): void
    {
        foreach (self::ADMINS as $admin) {
            $phone = PhoneHelper::normalize($admin['phone']);

            if ($phone === null) {
                continue;
            }

            /** @var User $user */
            $user = User::query()->firstOrCreate(['phone' => $phone]);

            // `is_admin` fillable nist (amdan) — faghat masir haye SARIH.
            $user->forceFill([
                'is_admin' => true,
                'name' => $user->name ?? $admin['name'],
                'email' => $user->email ?? $admin['email'],
                'phone_verified_at' => $user->phone_verified_at ?? now(),
            ])->save();
        }
    }
}
