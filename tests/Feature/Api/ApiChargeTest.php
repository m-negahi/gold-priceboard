<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Application\Actions\ApiClientAction;
use App\Modules\Api\Domain\Services\HmacSignature;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPriceGroup;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Application\Actions\WalletAction;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Persistence\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Billing e API (P4-05/P4-06/P4-08): debit e atomic، idempotency، refund
 * e 304/error، fail-closed.
 */
final class ApiChargeTest extends TestCase
{
    use RefreshDatabase;

    private const COST = 1_000;

    private ApiClient $client;

    private string $secret;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        // Mirror haye Redis az run haye ghabli nabayad in test ro aludeh konan.
        Redis::connection()->flushdb();

        $group = ApiPriceGroup::query()->create([
            'key' => 'basic',
            'name' => 'پایه',
            'cost_per_call_minor' => self::COST,
            'is_default' => true,
        ]);

        $user = User::factory()->create();
        $made = (new ApiClientAction)->create($user->id, 'test', $group->id);
        $this->client = $made['client'];
        $this->secret = $made['secret'];

        $this->wallet = (new WalletAction)->forOwner($user->id);
        (new WalletAction)->credit($this->wallet, 10_000, 'topup', 'seed');
    }

    /** @param array<string, string> $headers */
    private function signed(string $uri, array $headers = []): TestResponse
    {
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(8));

        // `getJson` body ye `[]` mifreste va emza ro mishkune — `get` e bi-body.
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

    public function test_a_successful_data_call_debits_the_wallet_once(): void
    {
        $response = $this->signed('/api/v1/prices')->assertOk();

        self::assertSame((string) self::COST, $response->headers->get('X-Pb-Cost'));
        self::assertSame(9_000, (int) $this->wallet->refresh()->balance_minor);
        $this->assertDatabaseHas('wallet_transactions', ['reason' => 'api_call', 'amount_minor' => self::COST]);
    }

    /** 🔴 P4-08: kasr e DOBARE ye yek request mamnu'. */
    public function test_a_stable_request_id_is_charged_exactly_once(): void
    {
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'req-77'])->assertOk();
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'req-77'])->assertOk();

        self::assertSame(9_000, (int) $this->wallet->refresh()->balance_minor, 'faghat yek kasr.');
        self::assertSame(
            1,
            WalletTransaction::query()->where('reason', 'api_call')->count(),
        );

        // Mirror e Redis ham nabayad do bar kam shode bashe — call e ba'di
        // ba mande ye vaghei kar mikone.
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'req-78'])->assertOk();
        self::assertSame(8_000, (int) $this->wallet->refresh()->balance_minor);
    }

    /** 🔴 P4-08: 304 (poll e bi-taghir) pul nadare. */
    public function test_an_etag_304_is_refunded(): void
    {
        $first = $this->signed('/api/v1/prices')->assertOk();
        $etag = (string) $first->headers->get('ETag');
        self::assertNotSame('', $etag);

        $second = $this->signed('/api/v1/prices', ['If-None-Match' => $etag]);

        $second->assertStatus(304);
        self::assertSame('0', $second->headers->get('X-Pb-Cost'));

        // Yek debit + yek refund -> mande faghat yek call kam shode.
        self::assertSame(9_000, (int) $this->wallet->refresh()->balance_minor);
        self::assertSame(1, WalletTransaction::query()->where('reason', 'refund')->count());
    }

    public function test_an_error_response_is_refunded(): void
    {
        $this->signed('/api/v1/prices/nist-hamchin-namadi')->assertNotFound();

        self::assertSame(10_000, (int) $this->wallet->refresh()->balance_minor, 'khata pul nadare.');
    }

    /** 🔴 P4-08: bedun e e'tebar service nemidim. */
    public function test_an_empty_wallet_gets_402_and_no_data(): void
    {
        // 10 call e movafagh mande ro sefr mikone.
        for ($i = 0; $i < 10; $i++) {
            $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => "drain-{$i}"])->assertOk();
        }

        $this->signed('/api/v1/prices')->assertStatus(402);
        self::assertSame(0, (int) $this->wallet->refresh()->balance_minor);
    }

    public function test_a_blocked_wallet_gets_403(): void
    {
        (new WalletAction)->block($this->wallet);

        $this->signed('/api/v1/prices')->assertForbidden();
        self::assertSame(10_000, (int) $this->wallet->refresh()->balance_minor);
    }

    public function test_the_wallet_endpoint_is_free_and_reports_the_ledger_truth(): void
    {
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'one'])->assertOk();

        $response = $this->signed('/api/v1/wallet')->assertOk();

        self::assertNull($response->headers->get('X-Pb-Cost'), 'wallet endpoint charge nadare.');
        self::assertSame(9_000, $response->json('data.balanceMinor'));
        self::assertSame('api_call', $response->json('data.ledger.0.reason'));
    }

    /** 🔴 P4-08: Redis down -> FAIL-CLOSED (503)، na service e majani. */
    public function test_redis_down_means_no_service(): void
    {
        // Mirror ro ghabl az ghat'i seed NAKON — reserve bayad Redis bekhad.
        // `RedisManager` config ro moghe'e BOOT copy mikone، pas taghir e
        // config be tanha-i ghat'i nemisaze — khod e facade mock mishe.
        Redis::shouldReceive('connection')
            ->andThrow(new \RuntimeException('redis ghat\' ast (test)'));

        $response = $this->signed('/api/v1/prices');

        // Nonce store (cache e array) salem e، pas auth rad mishe؛ charge
        // be Redis mikhore va bayad 503 bede.
        $response->assertStatus(503);
        self::assertSame(10_000, (int) $this->wallet->refresh()->balance_minor);
    }
}
