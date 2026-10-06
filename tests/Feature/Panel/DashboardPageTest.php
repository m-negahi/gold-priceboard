<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Database\Seeders\ProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mizkar e vaghei (P3-05) az rooye HTTP — controller e nazok +
 * `DashboardQuery` + Query haye vaghei ye Pricing (sqlite + Redis e test).
 */
final class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_an_admin_sees_the_dashboard_overview(): void
    {
        // Seeder az HAMUN Action ha rad mishe (ghanun e proje).
        $this->seed(ProviderSeeder::class);

        $response = $this->actingAs($this->admin())->get('/dashboard');

        $response->assertOk()
            // H1 + bakhsh haye asli.
            ->assertSee('میزکار')
            ->assertSee('سلامت منابع')
            ->assertSee('قیمت فروش منابع')
            // Provider haye seed-shode tu lista ye salamat (hatta khamoosh).
            ->assertSee('اتحادیه طلا و جواهر تهران');
    }

    public function test_the_dashboard_renders_with_an_empty_database(): void
    {
        // Rooz e avval: hich provider، hich gheymat — nabayad 500 bede.
        $this->actingAs($this->admin())->get('/dashboard')
            ->assertOk()
            ->assertSee('هنوز منبعی ثبت نشده است');
    }
}
