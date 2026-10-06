<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ApiPlanAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_archive_a_rial_subscription_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('panel.plans.create'))->assertOk();

        $this->actingAs($admin)->post(route('panel.plans.store'), [
            'key' => 'gold', 'name' => 'پلن طلایی', 'duration_days' => 30,
            'calls_included' => 100, 'calls_per_day' => 10,
            'price_rial' => '۱,۲۰۰,۰۰۰', 'cost_per_call_rial' => '500',
            'sort' => 1, 'is_active' => '1',
        ])->assertRedirect(route('panel.plans.index'));
        $this->assertDatabaseHas('api_plans', [
            'key' => 'gold', 'price_minor' => 1_200_000,
            'cost_per_call_minor' => 500, 'is_active' => true,
        ]);
        $id = (int) DB::table('api_plans')->where('key', 'gold')->value('id');

        $this->actingAs($admin)->put(route('panel.plans.update', $id), [
            'key' => 'gold', 'name' => 'پلن طلایی جدید', 'duration_days' => 45,
            'calls_included' => 200, 'calls_per_day' => 20,
            'price_rial' => '2,400,000', 'cost_per_call_rial' => '750',
            'sort' => 2, 'is_active' => '1',
        ])->assertRedirect(route('panel.plans.index'));
        $this->assertDatabaseHas('api_plans', ['id' => $id, 'name' => 'پلن طلایی جدید', 'price_minor' => 2_400_000]);

        $this->actingAs($admin)->post(route('panel.plans.archive', $id))
            ->assertRedirect(route('panel.plans.index'));
        $this->assertDatabaseHas('api_plans', ['id' => $id, 'is_active' => false]);
    }
}
