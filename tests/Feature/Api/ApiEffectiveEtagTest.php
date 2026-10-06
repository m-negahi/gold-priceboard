<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Application\Actions\ApiClientAction;
use App\Modules\Api\Domain\Services\HmacSignature;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Pricing\Application\Actions\ManualPriceAction;
use App\Modules\Pricing\Application\Actions\SymbolPriceSettingsAction;
use App\Modules\Pricing\Application\Actions\SymbolPriorityAction;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceSnapshot;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class ApiEffectiveEtagTest extends TestCase
{
    use RefreshDatabase;

    private array $credentials;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        Redis::connection('prices')->flushdb();
        $this->credentials = (new ApiClientAction)->create(User::factory()->create()->id, 'Effective cache');
    }

    private function requestFeed(?string $etag = null): TestResponse
    {
        $uri = '/api/v1/effective';
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(12));
        $headers = [
            'Accept' => 'application/json',
            'X-Pb-Client' => $this->credentials['client']->client_id,
            'X-Pb-Timestamp' => $timestamp,
            'X-Pb-Nonce' => $nonce,
            'X-Pb-Signature' => HmacSignature::sign($this->credentials['secret'], HmacSignature::canonical('GET', $uri, $timestamp, $nonce, '')),
        ];

        if ($etag !== null) {
            $headers['If-None-Match'] = $etag;
        }

        return $this->get($uri, $headers);
    }

    private function snapshot(string $providerKey, int $minor, int $sort): void
    {
        $provider = Provider::query()->create([
            'key' => $providerKey, 'driver' => $providerKey, 'name' => $providerKey,
            'name_fa' => $providerKey, 'is_active' => true, 'sort' => $sort,
        ]);
        PriceSnapshot::query()->create([
            'provider_id' => $provider->id, 'symbol' => 'xau-18k-g-irr', 'side' => 'last',
            'quote_currency' => 'IRR', 'scale' => 0, 'raw_minor' => $minor,
            'change_pct' => 0, 'direction' => 'flat', 'quoted_at' => now(),
        ]);
    }

    public function test_manual_policy_and_price_edits_change_etag_without_an_ingest_tick(): void
    {
        $this->snapshot('tgju', 266_400_000, 10);
        $instrument = Instrument::from('xau-18k-g-irr');
        $this->app->make(ManualPriceAction::class)->setFromDisplay($instrument, '270000000', 'Admin');

        $first = $this->requestFeed()->assertOk()->assertJsonPath('data.0.manualOnly', false);
        $etag = $first->headers->get('ETag');
        $this->requestFeed($etag)->assertStatus(304);

        $this->app->make(SymbolPriceSettingsAction::class)->set($instrument, true, true, 'none');
        $manual = $this->requestFeed($etag)->assertOk()->assertJsonPath('data.0.provider', 'manual')
            ->assertJsonPath('data.0.manualOnly', true)->assertJsonPath('data.0.sell', 270_000_000);
        self::assertNotSame($etag, $manual->headers->get('ETag'));
        $manualEtag = $manual->headers->get('ETag');

        $this->app->make(ManualPriceAction::class)->setFromDisplay($instrument, '271000000', 'Admin');
        $updated = $this->requestFeed($manualEtag)->assertOk()->assertJsonPath('data.0.sell', 271_000_000);
        self::assertNotSame($manualEtag, $updated->headers->get('ETag'));
    }

    public function test_priority_changes_with_the_same_fallback_count_change_etag(): void
    {
        $this->snapshot('tgju', 266_400_000, 10);
        $this->snapshot('estjt', 266_500_000, 20);
        $first = $this->requestFeed()->assertOk()->assertJsonPath('data.0.provider', 'tgju');

        $this->app->make(SymbolPriorityAction::class)->set(Instrument::from('xau-18k-g-irr'), ['estjt', 'tgju']);
        $updated = $this->requestFeed($first->headers->get('ETag'))->assertOk()->assertJsonPath('data.0.provider', 'estjt');
        self::assertNotSame($first->headers->get('ETag'), $updated->headers->get('ETag'));
        $this->requestFeed($updated->headers->get('ETag'))->assertStatus(304);
    }
}
