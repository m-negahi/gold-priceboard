<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Application\Actions\ApiClientAction;
use App\Modules\Api\Domain\Services\HmacSignature;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceSnapshot;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/** Feed e mo'asser e API (olaviat + fallback + alarm). MAJANI nist — charge nadare chun /effective zire ChargeApiCall e vali cost e default 0 ast. */
final class ApiEffectiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    public function test_effective_feed_returns_one_price_per_symbol_with_fallback_flag(): void
    {
        $provider = Provider::query()->create([
            'key' => 'tgju', 'driver' => 'tgju', 'name' => 'TGJU', 'name_fa' => 'ت', 'is_active' => true, 'sort' => 10,
        ]);
        PriceSnapshot::query()->create([
            'provider_id' => $provider->id, 'symbol' => 'xau-18k-g-irr', 'side' => 'last',
            'quote_currency' => 'IRR', 'scale' => 0, 'raw_minor' => 266_400_000,
            'change_pct' => 0, 'direction' => 'flat', 'quoted_at' => now(),
        ]);

        $made = (new ApiClientAction)->create(User::factory()->create()->id, 'c');
        $uri = '/api/v1/effective';
        $ts = (string) time();
        $nonce = 'eff-1';
        $sig = HmacSignature::sign($made['secret'], HmacSignature::canonical('GET', $uri, $ts, $nonce, ''));

        $res = $this->get($uri, [
            'Accept' => 'application/json',
            'X-Pb-Client' => $made['client']->client_id,
            'X-Pb-Timestamp' => $ts,
            'X-Pb-Nonce' => $nonce,
            'X-Pb-Signature' => $sig,
        ])->assertOk();

        $res->assertJsonPath('data.0.symbol', 'xau-18k-g-irr')
            ->assertJsonPath('data.0.provider', 'tgju')
            ->assertJsonPath('data.0.usingFallback', false)
            ->assertJsonPath('fallbackActive', false);
        self::assertSame('0', $res->headers->get('X-Pb-Fallback'));
    }
}
