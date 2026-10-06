<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Api\Application\Actions\ApiClientAction;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPlan;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Application\Actions\WalletAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kharid e plan az panel e moshtari (`AccountBillingController::purchase`).
 *
 * Chera: P10-02 khundan e site/plan ro az Controller be Query bord؛ in test
 * ghofl e un refactor ast — mokhabar e site e karbar e DIGAR bayad 404 bemune
 * (hazf e filter e `user_id` tu Query → test e aval ghermez).
 */
final class AccountPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchasing_on_another_users_site_is_not_found(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $site = (new ApiClientAction)->create($owner->id, 'site')['client'];
        $plan = $this->plan();

        $this->actingAs($intruder)
            ->post(route('account.billing.subscriptions.purchase'), [
                'site_id' => $site->id, 'plan_id' => $plan->id, 'purchase_key' => '4a6ad1bc-9a7f-4d66-a7e5-0c0b3b0d9c01',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('site_subscriptions', 0);
    }

    public function test_owner_purchases_a_plan_for_own_site(): void
    {
        $owner = User::factory()->create();
        $site = (new ApiClientAction)->create($owner->id, 'site')['client'];
        $plan = $this->plan();
        $wallet = app(WalletAction::class)->forOwner($owner->id);
        app(WalletAction::class)->credit($wallet, 10_000, 'topup', 'seed');

        $this->actingAs($owner)
            ->post(route('account.billing.subscriptions.purchase'), [
                'site_id' => $site->id, 'plan_id' => $plan->id, 'purchase_key' => '4a6ad1bc-9a7f-4d66-a7e5-0c0b3b0d9c02',
            ])
            ->assertRedirect(route('account.billing.subscriptions'));

        $this->assertDatabaseHas('site_subscriptions', ['api_client_id' => $site->id, 'api_plan_id' => $plan->id]);
        self::assertSame(8_000, $wallet->refresh()->balance_minor);
    }

    public function test_unknown_plan_is_not_found(): void
    {
        $owner = User::factory()->create();
        $site = (new ApiClientAction)->create($owner->id, 'site')['client'];

        $this->actingAs($owner)
            ->post(route('account.billing.subscriptions.purchase'), [
                'site_id' => $site->id, 'plan_id' => 999_999, 'purchase_key' => '4a6ad1bc-9a7f-4d66-a7e5-0c0b3b0d9c03',
            ])
            ->assertNotFound();
    }

    private function plan(): ApiPlan
    {
        return ApiPlan::query()->create([
            'key' => 'basic', 'name' => 'پایه', 'price_minor' => 2_000,
            'duration_days' => 30, 'calls_included' => 10, 'calls_per_day' => 10,
            'cost_per_call_minor' => 500, 'is_active' => true,
        ]);
    }
}
