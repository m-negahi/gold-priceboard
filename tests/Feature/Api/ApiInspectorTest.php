<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Application\Actions\ApiClientAction;
use App\Modules\Api\Domain\Services\HmacSignature;
use App\Modules\Api\Infrastructure\Monitoring\ApiRequestLog;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Inspector e API (P4-11) — LogApiRequest HAR call ro sabt mikone (hatta
 * 401 e auth)، va safhe ye panel request/response ro neshun mide.
 */
final class ApiInspectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->flushdb();
    }

    public function test_a_successful_call_is_logged_with_its_response(): void
    {
        $made = (new ApiClientAction)->create(User::factory()->create()->id, 'tala.ir');
        $client = $made['client'];

        $uri = '/api/v1/instruments';
        $ts = (string) time();
        $nonce = 'insp-1';
        $sig = HmacSignature::sign($made['secret'], HmacSignature::canonical('GET', $uri, $ts, $nonce, ''));

        $this->get($uri, [
            'Accept' => 'application/json',
            'X-Pb-Client' => $client->client_id,
            'X-Pb-Timestamp' => $ts,
            'X-Pb-Nonce' => $nonce,
            'X-Pb-Signature' => $sig,
        ])->assertOk();

        $logged = $this->app->make(ApiRequestLog::class)->recent(5);

        self::assertNotEmpty($logged);
        self::assertSame('/api/v1/instruments', $logged[0]['uri']);
        self::assertSame(200, $logged[0]['status']);
        self::assertStringContainsString('xau-18k-g-irr', (string) $logged[0]['response']);
        // Header haye request sabt shodan — vali signature kutah (nasht e nadare).
        self::assertSame($client->client_id, $logged[0]['request_headers']['X-Pb-Client']);
        self::assertStringEndsWith('…', $logged[0]['request_headers']['X-Pb-Signature']);
    }

    public function test_a_401_is_also_logged(): void
    {
        // Hich auth e dorost — bayad 401 bokhore VA sabt beshe (daghighan
        // chizi ke baraye debug e «chera 401 migiram» lazem e).
        $this->get('/api/v1/instruments', [
            'Accept' => 'application/json',
            'X-Pb-Client' => 'pb_nist',
            'X-Pb-Timestamp' => (string) time(),
            'X-Pb-Nonce' => 'x',
            'X-Pb-Signature' => 'bad',
        ])->assertUnauthorized();

        $logged = $this->app->make(ApiRequestLog::class)->recent(5);

        self::assertNotEmpty($logged);
        self::assertSame(401, $logged[0]['status']);
    }

    public function test_the_inspector_page_renders_logged_calls(): void
    {
        $this->app->make(ApiRequestLog::class)->push([
            'at' => now()->toIso8601String(),
            'method' => 'GET',
            'uri' => '/api/v1/prices',
            'status' => 402,
            'duration_ms' => 12,
            'client_id' => 'pb_demo',
            'response' => '{"message":"test"}',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('panel.monitor.api'))
            ->assertOk()
            ->assertSee('پایش API')
            ->assertSee('/api/v1/prices')
            ->assertSee('pb_demo');
    }

    public function test_the_inspector_page_has_an_honest_empty_state(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('panel.monitor.api'))
            ->assertOk()
            ->assertSee('هنوز فراخوانی‌ای ثبت نشده');
    }
}
