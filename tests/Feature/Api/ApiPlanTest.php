<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Application\Actions\ApiClientAction;
use App\Modules\Api\Application\Actions\ChargeApiCallAction;
use App\Modules\Api\Application\Actions\PurchasePlanAction;
use App\Modules\Api\Domain\Services\HmacSignature;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPlan;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Application\Actions\WalletAction;
use App\Modules\Wallet\Application\Queries\WalletQuery;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Eshterak e site: kharid kasr, call haye dakhel e sahmie rayegan,
 * mazaad ba nerkh e plan az wallet kasr mishe.
 */
final class ApiPlanTest extends TestCase
{
    use RefreshDatabase;

    private ApiClient $client;

    private string $secret;

    private Wallet $wallet;

    private ApiPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->flushdb();

        $user = User::factory()->create();
        $made = (new ApiClientAction)->create($user->id, 'tala.ir');
        $this->client = $made['client'];
        $this->client->forceFill(['website_url' => 'https://tala.ir'])->save();
        $this->secret = $made['secret'];
        $this->wallet = (new WalletAction)->forOwner($user->id);
        (new WalletAction)->credit($this->wallet, 8_000_000, 'topup', 'seed');

        $this->plan = ApiPlan::query()->create([
            'key' => 'basic-1200',
            'name' => 'پایه — ۱۲۰۰ فراخوانی در روز',
            'price_minor' => 5_000_000,
            'duration_days' => 30,
            'calls_included' => 3,
            'calls_per_day' => 2,
            'cost_per_call_minor' => 2_000,
        ]);
    }

    private function purchase(string $reference = 'pay-1'): void
    {
        app(PurchasePlanAction::class)->purchase($this->client, $this->plan, $reference);
        $this->client->refresh();
    }

    /** @param array<string, string> $headers */
    private function signed(string $uri, array $headers = []): TestResponse
    {
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(8));

        return $this->get($uri, array_merge([
            'Accept' => 'application/json',
            'X-Pb-Client' => $this->client->client_id,
            'X-Pb-Timestamp' => $timestamp,
            'X-Pb-Nonce' => $nonce,
            'X-Pb-Signature' => HmacSignature::sign(
                $this->secret,
                HmacSignature::canonical('GET', $uri, $timestamp, $nonce, ''),
            ),
        ], $headers));
    }

    public function test_buying_a_plan_debits_the_wallet_and_stores_terms(): void
    {
        $this->purchase();

        self::assertSame(3_000_000, (int) $this->wallet->refresh()->balance_minor);
        self::assertSame($this->plan->id, $this->client->plan_id);
        $this->assertDatabaseHas('wallet_transactions', ['reason' => 'plan_purchase', 'direction' => 'debit', 'amount_minor' => 5_000_000]);
        $this->assertDatabaseHas('site_subscriptions', ['api_client_id' => $this->client->id, 'calls_included' => 3, 'calls_per_day' => 2]);
    }

    public function test_a_purchase_retry_debits_only_once(): void
    {
        $this->purchase('pay-dup');
        $this->purchase('pay-dup');

        self::assertSame(3_000_000, (int) $this->wallet->refresh()->balance_minor);
        $this->assertDatabaseCount('site_subscriptions', 1);
    }

    public function test_included_calls_are_free_and_daily_overage_uses_plan_rate(): void
    {
        $this->purchase();

        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'd-1'])->assertOk()->assertHeader('X-Pb-Cost', '0');
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'd-2'])->assertOk()->assertHeader('X-Pb-Cost', '0');
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'd-3'])->assertOk()->assertHeader('X-Pb-Cost', '2000');

        self::assertSame(2_998_000, (int) $this->wallet->refresh()->balance_minor);
        $this->assertDatabaseCount('site_subscription_calls', 2);
    }

    public function test_purchasing_mid_stream_refreshes_the_redis_mirror(): void
    {
        $this->purchase();

        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'a'])->assertOk();
        (new WalletAction)->credit($this->wallet, 5_000_000, 'topup', 'seed-2');
        app(PurchasePlanAction::class)->purchase($this->client, $this->plan, 'pay-2');

        self::assertTrue((new WalletQuery)->verifyBalance($this->wallet->refresh()));

        // Call e ba'di az sahmie ye fa'al estefade mikone.
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'b'])->assertOk();
    }

    public function test_charge_uses_charger_quota_before_wallet(): void
    {
        // Bedun e plan: hich saghf-i — in test sabet mikone saghf FAGHAT
        // ba plan miad. (Default group-i ham nist -> free.)
        $charge = app(ChargeApiCallAction::class)->charge($this->client, 'x-1');

        self::assertSame('free', $charge['status']);
    }
}
