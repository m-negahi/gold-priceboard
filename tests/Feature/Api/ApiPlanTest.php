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
 * Model e karbari ye P4-09: kharid e plan wallet ro sharj mikone،
 * masraf pay-as-you-go ba nerkh e plan kasr mishe، saghf e ruzane 429.
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

        $this->plan = ApiPlan::query()->create([
            'key' => 'basic-1200',
            'name' => 'پایه — ۱۲۰۰ فراخوانی در روز',
            'price_minor' => 5_000_000,
            'calls_per_day' => 3,
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

    public function test_buying_a_plan_credits_the_wallet_by_the_plan_price(): void
    {
        $this->purchase();

        self::assertSame(5_000_000, (int) $this->wallet->refresh()->balance_minor);
        self::assertSame($this->plan->id, $this->client->plan_id);
        $this->assertDatabaseHas('wallet_transactions', ['reason' => 'plan_purchase', 'amount_minor' => 5_000_000]);
    }

    public function test_a_gateway_retry_credits_only_once(): void
    {
        $this->purchase('pay-dup');
        $this->purchase('pay-dup');

        self::assertSame(5_000_000, (int) $this->wallet->refresh()->balance_minor, 'callback e tekrari do bar sharj nemikone.');
    }

    public function test_calls_use_the_plan_rate_and_hit_the_daily_cap(): void
    {
        $this->purchase();

        // 3 call e mojaz ba nerkh e 2000:
        foreach ([1, 2, 3] as $i) {
            $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => "d-{$i}"])->assertOk();
        }

        self::assertSame(5_000_000 - 3 * 2_000, (int) $this->wallet->refresh()->balance_minor);

        // Call e 4om: saghf e ruzane (429) — HICH kasr-i.
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'd-4'])->assertStatus(429);
        self::assertSame(5_000_000 - 3 * 2_000, (int) $this->wallet->refresh()->balance_minor);
    }

    public function test_purchasing_mid_stream_refreshes_the_redis_mirror(): void
    {
        $this->purchase();

        // Mirror ba yek call seed mishe...
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'a'])->assertOk();

        // ...kharid e plan e dovvom (sharj e bishtar) bayad mirror ro batel
        // kone ta mande ye jadid dide she.
        app(PurchasePlanAction::class)->purchase($this->client, $this->plan, 'pay-2');

        self::assertTrue((new WalletQuery)->verifyBalance($this->wallet->refresh()));

        // Call e ba'di ba mande ye JADID kar mikone (hich 402-i).
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
