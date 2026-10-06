<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * 🔴 Re-seed bayad GHEYR-mokhareb va idempotent bashe (ghanun e proje).
 *
 * Ghablan inja yek `User::factory()->create(['email' => 'test@example.com'])`
 * e baghi-mande az skeleton e Laravel bud: ejra ye DOVVOM e `db:seed` ru
 * soton e unique e `email` mishekast va kol e seed fail mishod. Karbar e
 * test lazem nist — `AdminSeeder` admin e vaghei ro misaze va test ha
 * factory ye khodeshun ro daran.
 */
final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            ProviderSeeder::class,
            AdminSeeder::class,
        ]);
    }
}
