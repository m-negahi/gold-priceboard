<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Application\Actions\ApiClientAction;
use App\Modules\Api\Application\Actions\ChargeApiCallAction;
use App\Modules\Api\Application\Actions\PurchasePlanAction;
use App\Modules\Api\Domain\Services\HmacSignature;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPlan;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPriceGroup;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Application\Actions\WalletAction;
use App\Modules\Wallet\Application\Queries\WalletQuery;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Persistence\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
    /**
     * 🔴 Compensation e PAYDAR (checkpoint e 2026-10-06): age lahze ye
     * refund DB dar dastres nabashe (inja: wallet block shode → refund
     * exception mide)، pul e customer nabayad gom she — entry tu saf e
     * Redis mimune va `api:refunds:drain` ba'dan replay esh mikone.
     */
    public function test_a_failed_refund_is_queued_and_replayed_by_the_drain_command(): void
    {
        $charger = app(ChargeApiCallAction::class);
        $wallets = new WalletAction;

        $charge = $charger->charge($this->client, 'ref-pending-refund');
        self::assertSame('ok', $charge['status']);

        // Shekast e refund (mesl e ghat'i ye PG): block e wallet har write
        // ro rad mikone.
        $wallets->block($this->wallet);
        $charger->refundCall($this->client, 'ref-pending-refund', $charge['cost']);

        // Hich radif e refund sabt nashode vali entry tu saf e paydar hast.
        $this->assertDatabaseMissing('wallet_transactions', ['reason' => 'refund', 'reference' => 'refund:ref-pending-refund']);
        self::assertSame(1, (int) Redis::connection()->llen('api:refunds:pending'));

        // Ta DB "bar nagarde"، drain entry ro negah midare (requeue).
        $this->artisan('api:refunds:drain')->assertFailed();
        self::assertCount(1, (array) Redis::connection()->lrange('api:refunds:pending', 0, -1));

        // DB bargasht → replay، radif e refund sabt، saf khali. Idempotent:
        // ejra ye dovom hich radif e jadid nemisaze.
        $wallets->unblock($this->wallet);
        $this->artisan('api:refunds:drain')->assertSuccessful();
        $this->assertDatabaseHas('wallet_transactions', ['reason' => 'refund', 'reference' => 'refund:ref-pending-refund']);
        self::assertSame(0, (int) Redis::connection()->llen('api:refunds:pending'));
        self::assertSame(10_000, (new WalletQuery)->findForOwner($this->client->user_id)->balance_minor);

        $this->artisan('api:refunds:drain')->assertSuccessful();
        self::assertSame(1, DB::table('wallet_transactions')->where('reason', 'refund')->where('reference', 'refund:ref-pending-refund')->count());
    }

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

    public function test_a_retry_after_the_last_credit_replays_the_same_response(): void
    {
        (new WalletAction)->debit($this->wallet, 9000, 'adjustment', 'leave-one-call');
        $first = $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'last-credit'])->assertOk();
        $second = $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'last-credit'])->assertOk();
        self::assertSame($first->getContent(), $second->getContent());
        $second->assertHeader('X-Pb-Replayed', '1')->assertHeader('X-Pb-Cost', '0');
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'last-credit'])->assertOk()->assertHeader('X-Pb-Replayed', '1');
        self::assertSame(0, $this->wallet->refresh()->balance_minor);
    }

    public function test_reusing_an_id_with_another_request_cannot_refund_a_successful_call(): void
    {
        $first = $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'same-id'])->assertOk();
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'same-id', 'If-None-Match' => $first->headers->get('ETag')])->assertConflict();
        $this->signed('/api/v1/prices/missing', ['X-Pb-Request-Id' => 'same-id'])->assertConflict();
        self::assertSame(9000, $this->wallet->refresh()->balance_minor);
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_request_ids_are_scoped_to_each_client_of_the_same_owner(): void
    {
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'same-id'])->assertOk();
        $made = (new ApiClientAction)->create($this->client->user_id, 'second', $this->client->price_group_id);
        $this->client = $made['client'];
        $this->secret = $made['secret'];
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'same-id'])->assertOk();
        self::assertSame(8000, $this->wallet->refresh()->balance_minor);
    }

    public function test_lost_replay_cache_does_not_serve_new_data_for_an_old_debit(): void
    {
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'lost-cache'])->assertOk();
        Cache::flush();
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'lost-cache'])->assertConflict();
        self::assertSame(9000, $this->wallet->refresh()->balance_minor);
    }

    public function test_lost_replay_cache_does_not_reuse_an_included_subscription_call(): void
    {
        $plan = ApiPlan::query()->create([
            'key' => 'included-replay', 'name' => 'Included', 'price_minor' => 0,
            'duration_days' => 30, 'calls_included' => 1, 'calls_per_day' => 1,
            'cost_per_call_minor' => 500, 'is_active' => true,
        ]);
        app(PurchasePlanAction::class)->purchase($this->client, $plan, 'purchase-replay');

        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'included-old'])->assertOk()->assertHeader('X-Pb-Cost', '0');
        $this->assertDatabaseCount('site_subscription_calls', 1);
        Cache::flush();
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'included-old'])->assertConflict();
        $this->assertDatabaseCount('site_subscription_calls', 1);
    }

    public function test_long_request_ids_fit_the_ledger_even_when_refunded(): void
    {
        $this->signed('/api/v1/prices/missing', ['X-Pb-Request-Id' => str_repeat('a', 128)])->assertNotFound();
        foreach (WalletTransaction::whereIn('reason', ['api_call', 'refund'])->get() as $row) {
            self::assertLessThanOrEqual(64, strlen($row->reference));
        }
        self::assertSame(10000, $this->wallet->refresh()->balance_minor);
    }

    public function test_an_in_flight_request_is_not_charged_again_and_releases_its_lock(): void
    {
        $reference = 'api:'.substr(hash('sha256', $this->client->client_id.'|busy'), 0, 48);
        $lock = Cache::lock('api:replay:'.$reference.':lock', 120);
        self::assertTrue($lock->get());
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'busy'])->assertConflict();
        self::assertSame(10000, $this->wallet->refresh()->balance_minor);
        $lock->release();
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'busy'])->assertOk();
    }

    public function test_a_blocked_wallet_cannot_replay_saved_data(): void
    {
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'blocked-later'])->assertOk();
        (new WalletAction)->block($this->wallet);
        $this->signed('/api/v1/prices', ['X-Pb-Request-Id' => 'blocked-later'])->assertForbidden();
    }

    public function test_a_refunded_error_replays_without_another_debit_or_credit(): void
    {
        $first = $this->signed('/api/v1/prices/missing', ['X-Pb-Request-Id' => 'failed-once'])->assertNotFound();
        $second = $this->signed('/api/v1/prices/missing', ['X-Pb-Request-Id' => 'failed-once'])->assertNotFound();
        self::assertSame($first->getContent(), $second->getContent());
        $second->assertHeader('X-Pb-Replayed', '1');
        self::assertSame(10000, $this->wallet->refresh()->balance_minor);
        $this->assertDatabaseCount('wallet_transactions', 3);
    }

    public function test_replaying_the_effective_feed_preserves_its_fallback_header(): void
    {
        $first = $this->signed('/api/v1/effective', ['X-Pb-Request-Id' => 'effective-retry'])->assertOk();
        $second = $this->signed('/api/v1/effective', ['X-Pb-Request-Id' => 'effective-retry'])->assertOk();

        $first->assertHeader('X-Pb-Fallback', '0');
        $second->assertHeader('X-Pb-Fallback', '0')->assertHeader('X-Pb-Replayed', '1');
        self::assertSame($first->getContent(), $second->getContent());
        self::assertSame($first->headers->get('ETag'), $second->headers->get('ETag'));
        self::assertSame(9000, $this->wallet->refresh()->balance_minor);
    }
}
