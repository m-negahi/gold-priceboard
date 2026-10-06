<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Saghf e per-IP ru endpoint haye OMUMI va `/login/*` (P10-11).
 *
 * Limit az config khunde mishe؛ inja kam mishe ta test sari' bashe. Har
 * limiter kelid e `{name}|{ip}` dare، pas IP e dige saghf e khodesh ro dare.
 */
final class PublicThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('board|203.0.113.7');
        RateLimiter::clear('board|203.0.113.8');
        RateLimiter::clear('tv|203.0.113.7');
        RateLimiter::clear('login|203.0.113.7');
    }

    public function test_board_json_is_limited_per_ip_and_other_ips_are_unaffected(): void
    {
        config()->set('pricing.throttle.board_per_minute', 3);

        for ($i = 0; $i < 3; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->getJson('/board.json')->assertOk();
        }

        $blocked = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->getJson('/board.json');
        $blocked->assertStatus(429);
        self::assertNotNull($blocked->headers->get('Retry-After'));

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])->getJson('/board.json')->assertOk();
    }

    public function test_tv_json_has_its_own_bucket(): void
    {
        config()->set('pricing.throttle.board_per_minute', 1);
        config()->set('pricing.throttle.tv_per_minute', 2);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->getJson('/board.json')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->getJson('/board.json')->assertStatus(429);

        // Satl e tv joda-st: hanuz baz ast.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->getJson('/tv.json')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->getJson('/tv.json')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->getJson('/tv.json')->assertStatus(429);
    }

    /** Laye ye biruni ye login: ghabl az inke be Action va limit haye OTP berese. */
    public function test_login_posts_are_limited_per_ip(): void
    {
        config()->set('otp.login_requests_per_minute', 2);

        // Phone e khali → 422 az validation، vali shomaresh e throttle anjam shode.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->postJson('/login/code', [])->assertStatus(422);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->postJson('/login/code', [])->assertStatus(422);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->postJson('/login/code', [])->assertStatus(429);
    }

    /** Default ha onghadr bala hastan ke poll e 15 saniye-i ye tablo hich vaght nakhore. */
    public function test_defaults_leave_room_for_normal_polling(): void
    {
        self::assertGreaterThanOrEqual(60, (int) config('pricing.throttle.board_per_minute'));
        self::assertGreaterThanOrEqual(60, (int) config('pricing.throttle.candles_per_minute'));
        self::assertGreaterThanOrEqual(30, (int) config('otp.login_requests_per_minute'));
    }
}
