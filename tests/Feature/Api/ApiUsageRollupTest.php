<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Application\Actions\ApiClientAction;
use App\Modules\Api\Application\Queries\ApiUsageQuery;
use App\Modules\Api\Domain\Services\HmacSignature;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPriceGroup;
use App\Modules\Api\Infrastructure\Usage\RedisUsageCounter;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Application\Actions\WalletAction;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * P6-08 — usage e PAYDAR: shomarande ye ruzane ye Redis + rollup be
 * `api_usage_daily` + Query + chart tu safhe ye karbar.
 *
 * - `calls` az shomarande ye Redis (har call e data ke serve shod).
 * - `cost_minor` az LEDGER e PG (kasr ha − refund ha) — manba' e haghighat
 *   e pul، na yek shomarande ye movazi.
 * - Rooz = rooz e TEHRAN.
 */
final class ApiUsageRollupTest extends TestCase
{
    use RefreshDatabase;

    private const COST = 1_000;

    private User $owner;

    private ApiClient $client;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->flushdb();

        $group = ApiPriceGroup::query()->create([
            'key' => 'basic',
            'name' => 'پایه',
            'cost_per_call_minor' => self::COST,
            'is_default' => true,
        ]);

        $this->owner = User::factory()->create();
        $made = (new ApiClientAction)->create($this->owner->id, 'site', $group->id);
        $this->client = $made['client'];
        $this->secret = $made['secret'];

        $wallets = new WalletAction;
        $wallets->credit($wallets->forOwner($this->owner->id), 1_000_000, 'topup', 'seed');
    }

    private function signedPrices(): TestResponse
    {
        $uri = '/api/v1/prices';
        // `time()` e PHP ba `travelTo` jabeja nemishe — saat e Carbon (ke
        // ApiHmacAuth ham mikhune).
        $timestamp = (string) CarbonImmutable::now()->getTimestamp();
        $nonce = bin2hex(random_bytes(8));

        return $this->get($uri, [
            'Accept' => 'application/json',
            'X-Pb-Client' => $this->client->client_id,
            'X-Pb-Timestamp' => $timestamp,
            'X-Pb-Nonce' => $nonce,
            'X-Pb-Signature' => HmacSignature::sign($this->secret, HmacSignature::canonical('GET', $uri, $timestamp, $nonce, '')),
        ]);
    }

    private function runRollup(string $date): void
    {
        $this->artisan('api:usage:rollup', ['--date' => $date, '--days' => 1])->assertSuccessful();
    }

    /** @return array{calls: int, cost_minor: int}|null */
    private function storedRow(string $day, ?int $clientPk = null): ?array
    {
        $row = DB::table('api_usage_daily')
            ->where('api_client_id', $clientPk ?? $this->client->id)
            ->whereDate('day', $day)
            ->first();

        return $row === null ? null : ['calls' => (int) $row->calls, 'cost_minor' => (int) $row->cost_minor];
    }

    public function test_a_served_data_call_is_counted_for_the_tehran_day(): void
    {
        // 21:00 UTC = 00:30 e ROOZ E BA'D e Tehran.
        $this->travelTo(CarbonImmutable::parse('2026-10-05 21:00:00', 'UTC'));

        $this->signedPrices()->assertOk();
        $this->signedPrices()->assertOk();

        $counts = app(RedisUsageCounter::class)->forDay('2026-10-06');
        $this->assertSame(2, $counts[$this->client->id] ?? null);
        $this->assertSame([], app(RedisUsageCounter::class)->forDay('2026-10-05'));
    }

    public function test_the_rollup_stores_calls_and_net_ledger_cost(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 08:00:00', 'Asia/Tehran'));

        $this->signedPrices()->assertOk();
        $this->signedPrices()->assertOk();
        $this->signedPrices()->assertOk();
        // Yek refund e dasti ru yeki az call ha (mesl e 304).
        $wallets = new WalletAction;
        $wallets->refund($wallets->forOwner($this->owner->id), self::COST, 'x-ref', ['client_id' => $this->client->client_id]);

        $this->runRollup('2026-10-06');

        $this->assertSame(['calls' => 3, 'cost_minor' => 2 * self::COST], $this->storedRow('2026-10-06'));
    }

    public function test_the_rollup_is_idempotent(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 08:00:00', 'Asia/Tehran'));
        $this->signedPrices()->assertOk();

        $this->runRollup('2026-10-06');
        $this->runRollup('2026-10-06');

        $this->assertSame(1, DB::table('api_usage_daily')->count());
        $this->assertSame(['calls' => 1, 'cost_minor' => self::COST], $this->storedRow('2026-10-06'));
    }

    /** 🔴 Redis pak shod (evict/restart): calls e zakhire-shode sefr NEMISHE؛ hazine az ledger dobare dorost mishe. */
    public function test_a_lost_redis_counter_does_not_zero_the_stored_calls(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 08:00:00', 'Asia/Tehran'));
        $this->signedPrices()->assertOk();
        $this->signedPrices()->assertOk();
        $this->runRollup('2026-10-06');

        Redis::connection()->flushdb();
        $this->runRollup('2026-10-06');

        $this->assertSame(['calls' => 2, 'cost_minor' => 2 * self::COST], $this->storedRow('2026-10-06'));
    }

    public function test_ledger_rows_are_bucketed_by_the_tehran_day(): void
    {
        // 23:50 e Tehran e 5-om -> rooz e 5؛ 00:10 e Tehran e 6-om -> rooz e 6.
        $this->travelTo(CarbonImmutable::parse('2026-10-05 23:50:00', 'Asia/Tehran'));
        $this->signedPrices()->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-10-06 00:10:00', 'Asia/Tehran'));
        $this->signedPrices()->assertOk();
        $this->signedPrices()->assertOk();

        $this->artisan('api:usage:rollup', ['--date' => '2026-10-06', '--days' => 2])->assertSuccessful();

        $this->assertSame(['calls' => 1, 'cost_minor' => self::COST], $this->storedRow('2026-10-05'));
        $this->assertSame(['calls' => 2, 'cost_minor' => 2 * self::COST], $this->storedRow('2026-10-06'));
    }

    public function test_the_query_returns_a_zero_filled_window_for_the_owner_only(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 08:00:00', 'Asia/Tehran'));
        $this->signedPrices()->assertOk();
        $this->runRollup('2026-10-06');

        // Karbar e digar ba usage — nabayad tu natije biad.
        $other = (new ApiClientAction)->create(User::factory()->create()->id, 'other')['client'];
        DB::table('api_usage_daily')->insert([
            'api_client_id' => $other->id, 'day' => '2026-10-06', 'calls' => 500, 'cost_minor' => 9_999,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $usage = app(ApiUsageQuery::class)->dailyForUser($this->owner->id, 7);

        $this->assertCount(7, $usage['days']);
        $this->assertSame('2026-09-30', $usage['days'][0]['day']);
        $this->assertSame('2026-10-06', $usage['days'][6]['day']);
        $this->assertSame(1, $usage['days'][6]['calls']);
        $this->assertSame(0, $usage['days'][0]['calls']);
        $this->assertSame(1, $usage['totalCalls']);
        $this->assertSame(self::COST, $usage['totalCostMinor']);
    }

    public function test_deleted_sites_still_count_in_the_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 08:00:00', 'Asia/Tehran'));
        $this->signedPrices()->assertOk();
        $this->runRollup('2026-10-06');
        $this->client->delete(); // soft delete

        $this->assertSame(1, app(ApiUsageQuery::class)->dailyForUser($this->owner->id, 7)['totalCalls']);
    }

    public function test_the_user_page_shows_the_usage_chart(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 08:00:00', 'Asia/Tehran'));
        $this->signedPrices()->assertOk();
        $this->runRollup('2026-10-06');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('panel.users.show', $this->owner->id))
            ->assertOk()
            ->assertSee('data-usage-chart', false)
            ->assertSee('مصرف API')
            // Hame meghdar ha be rial namayesh dade mishan.
            ->assertSee('data-usage-total-calls="1"', false)
            ->assertSee('data-usage-total-cost="1,000"', false);
    }

    public function test_the_rollup_is_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(static fn ($event): bool => str_contains((string) $event->command, 'api:usage:rollup'));

        $this->assertCount(1, $events);
    }
}
