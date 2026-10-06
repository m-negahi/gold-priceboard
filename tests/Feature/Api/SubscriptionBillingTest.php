<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Application\Actions\ApiClientAction;
use App\Modules\Api\Application\Actions\ChargeApiCallAction;
use App\Modules\Api\Application\Actions\PurchasePlanAction;
use App\Modules\Api\Domain\Services\HmacSignature;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPlan;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPriceGroup;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Application\Actions\WalletAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class SubscriptionBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_included_calls_are_free_then_overage_and_expired_plan_use_default_rate(): void
    {
        $this->travelTo('2026-10-06 12:00:00');
        Redis::connection()->flushdb();
        $user = User::factory()->create();
        $group = ApiPriceGroup::query()->create(['key' => 'default', 'name' => 'پایه', 'cost_per_call_minor' => 1_000, 'is_default' => true]);
        $client = (new ApiClientAction)->create($user->id, 'site', $group->id)['client'];
        $plan = ApiPlan::query()->create([
            'key' => 'one', 'name' => 'یک', 'price_minor' => 2_000,
            'duration_days' => 30, 'calls_included' => 1, 'calls_per_day' => 1,
            'cost_per_call_minor' => 500, 'is_active' => true,
        ]);
        $wallet = app(WalletAction::class)->forOwner($user->id);
        app(WalletAction::class)->credit($wallet, 10_000, 'topup', 'seed');
        app(PurchasePlanAction::class)->purchase($client, $plan, 'purchase-1');

        $first = app(ChargeApiCallAction::class)->charge($client, 'call-1');
        $second = app(ChargeApiCallAction::class)->charge($client, 'call-2');
        $this->travelTo('2026-11-07 12:00:00');
        $third = app(ChargeApiCallAction::class)->charge($client, 'call-3');

        self::assertSame('included', $first['status']);
        self::assertSame(0, $first['cost']);
        self::assertSame(500, $second['cost']);
        self::assertSame(1_000, $third['cost']);
        self::assertSame(6_500, $wallet->refresh()->balance_minor);
        $this->assertDatabaseCount('site_subscription_calls', 1);
    }

    public function test_failed_included_call_releases_quota_for_next_call(): void
    {
        $this->travelTo('2026-10-06 12:00:00');
        Redis::connection()->flushdb();
        $user = User::factory()->create();
        $client = (new ApiClientAction)->create($user->id, 'site')['client'];
        $plan = ApiPlan::query()->create([
            'key' => 'one', 'name' => 'یک', 'price_minor' => 0,
            'duration_days' => 30, 'calls_included' => 1, 'calls_per_day' => 1,
            'cost_per_call_minor' => 500, 'is_active' => true,
        ]);
        app(PurchasePlanAction::class)->purchase($client, $plan, 'purchase-1');
        $first = app(ChargeApiCallAction::class)->charge($client, 'failed-call');

        app(ChargeApiCallAction::class)->releaseIncluded($client, 'failed-call');
        $second = app(ChargeApiCallAction::class)->charge($client, 'successful-call');

        self::assertSame('included', $first['status']);
        self::assertSame('included', $second['status']);
        $this->assertDatabaseCount('site_subscription_calls', 1);
    }

    public function test_blocked_wallet_cannot_use_included_subscription_calls(): void
    {
        $user = User::factory()->create();
        $client = (new ApiClientAction)->create($user->id, 'site')['client'];
        $plan = ApiPlan::query()->create([
            'key' => 'blocked', 'name' => 'Blocked', 'price_minor' => 0,
            'duration_days' => 30, 'calls_included' => 3, 'calls_per_day' => 3,
            'cost_per_call_minor' => 500, 'is_active' => true,
        ]);
        app(PurchasePlanAction::class)->purchase($client, $plan, 'purchase-blocked');
        $wallets = app(WalletAction::class);
        $wallets->block($wallets->forOwner($user->id));

        self::assertSame('blocked', app(ChargeApiCallAction::class)->charge($client, 'blocked-call')['status']);
        $this->assertDatabaseCount('site_subscription_calls', 0);
    }

    public function test_purchase_issues_invoice_from_immutable_plan_and_buyer_snapshots(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'level' => 'invoice_verified',
            'invoice_profile' => ['registered_name' => 'Original Buyer'],
        ])->save();
        $client = (new ApiClientAction)->create($user->id, 'site')['client'];
        $plan = ApiPlan::query()->create([
            'key' => 'pro', 'name' => 'Original Plan', 'price_minor' => 2_000,
            'duration_days' => 30, 'calls_included' => 5, 'calls_per_day' => 2,
            'cost_per_call_minor' => 100, 'is_active' => true,
        ]);
        $wallet = app(WalletAction::class)->forOwner($user->id);
        app(WalletAction::class)->credit($wallet, 3_000, 'topup', 'seed');

        $subscription = app(PurchasePlanAction::class)->purchase($client, $plan, 'purchase-1');
        $plan->forceFill(['name' => 'Changed Plan'])->save();
        $user->forceFill(['invoice_profile' => ['registered_name' => 'Changed Buyer']])->save();

        self::assertSame(1_000, $wallet->refresh()->balance_minor);
        $this->assertDatabaseHas('billing_invoices', ['site_subscription_id' => $subscription->id, 'kind' => 'subscription', 'amount_minor' => 2_000]);
        $invoice = DB::table('billing_invoices')->where('site_subscription_id', $subscription->id)->first();
        self::assertStringContainsString('Original Plan', (string) $invoice->lines_snapshot);
        self::assertStringContainsString('Original Buyer', (string) $invoice->buyer_snapshot);
        $this->assertDatabaseHas('finance_journals', ['source_key' => 'wallet:'.DB::table('wallet_transactions')->where('reason', 'plan_purchase')->value('id')]);
    }

    /**
     * P8-06: plan e BEDUN e nerkh e mazad (`cost_per_call_minor = 0`) ba'd az
     * por shodan e sahmie → 429 + payam e Farsi، va HICH kasri az wallet.
     */
    public function test_exhausted_quota_on_a_plan_without_overage_returns_429_without_wallet_debit(): void
    {
        $this->travelTo('2026-10-06 12:00:00');
        Redis::connection()->flushdb();
        $user = User::factory()->create();
        ApiPriceGroup::query()->create(['key' => 'default', 'name' => 'پایه', 'cost_per_call_minor' => 1_000, 'is_default' => true]);
        $made = (new ApiClientAction)->create($user->id, 'site');
        $client = $made['client'];
        $plan = ApiPlan::query()->create([
            'key' => 'quota-only', 'name' => 'فقط سهمیه', 'price_minor' => 0,
            'duration_days' => 30, 'calls_included' => 1, 'calls_per_day' => 1,
            'cost_per_call_minor' => 0, 'is_active' => true,
        ]);
        $wallet = app(WalletAction::class)->forOwner($user->id);
        app(WalletAction::class)->credit($wallet, 10_000, 'topup', 'seed');
        app(PurchasePlanAction::class)->purchase($client, $plan, 'purchase-quota');

        $this->signed($client->client_id, $made['secret'], 'req-1')->assertOk()->assertHeader('X-Pb-Cost', '0');
        $blocked = $this->signed($client->client_id, $made['secret'], 'req-2');

        $blocked->assertStatus(429)->assertJson(['message' => 'سقف روزانه پلن شما پر شده است.']);
        self::assertSame(10_000, $wallet->refresh()->balance_minor);
        $this->assertDatabaseMissing('wallet_transactions', ['reason' => 'api_call']);
        $this->assertDatabaseCount('site_subscription_calls', 1);
    }

    /**
     * P8-05: kharid e dovom GHABL az tamum shodan e avali az `ends_at` e
     * ghabli shoru' mishe (stack)، na az alan — moshtari hich ruzi ro az dast nemide.
     */
    public function test_buying_before_expiry_stacks_the_new_subscription_after_the_previous_end(): void
    {
        $this->travelTo('2026-10-06 12:00:00');
        $user = User::factory()->create();
        $client = (new ApiClientAction)->create($user->id, 'site')['client'];
        $plan = ApiPlan::query()->create([
            'key' => 'monthly', 'name' => 'ماهانه', 'price_minor' => 0,
            'duration_days' => 30, 'calls_included' => 10, 'calls_per_day' => 10,
            'cost_per_call_minor' => 100, 'is_active' => true,
        ]);
        $first = app(PurchasePlanAction::class)->purchase($client, $plan, 'purchase-1');

        $this->travelTo('2026-10-10 09:00:00');
        $second = app(PurchasePlanAction::class)->purchase($client, $plan, 'purchase-2');

        self::assertSame('2026-11-05 12:00:00', $first->ends_at->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-05 12:00:00', $second->starts_at->format('Y-m-d H:i:s'));
        self::assertSame('2026-12-05 12:00:00', $second->ends_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('site_subscriptions', 2);
    }

    /** Masir e data ye charge-dar ba emza ye HMAC — `get` e bi-body ta emza nashkane. */
    private function signed(string $clientId, string $secret, string $requestId): TestResponse
    {
        $uri = '/api/v1/prices';
        // Zir e `travelTo` bayad az Carbon biad، vagarna skew e timestamp 401 mide.
        $timestamp = (string) now()->getTimestamp();
        $nonce = bin2hex(random_bytes(8));

        return $this->get($uri, [
            'Accept' => 'application/json',
            'X-Pb-Client' => $clientId,
            'X-Pb-Timestamp' => $timestamp,
            'X-Pb-Nonce' => $nonce,
            'X-Pb-Request-Id' => $requestId,
            'X-Pb-Signature' => HmacSignature::sign($secret, HmacSignature::canonical('GET', $uri, $timestamp, $nonce, '')),
        ]);
    }
}
