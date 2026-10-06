<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Dto\ProviderHealthDto;
use App\Modules\Pricing\Application\Queries\ProviderHealthQuery;
use App\Modules\Pricing\Domain\Enums\HealthState;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\ArrayHotCache;
use Tests\TestCase;

/**
 * Gharardad e merge: `state`/`consecutiveFailures` az DB (manba' e
 * haghighat e shomaresh)، field haye lahze-i (latency/symbol/error) az hot
 * cache vaghti hast؛ Redis ghat' bashe DB be tanha-i javab mide.
 */
final class ProviderHealthQueryTest extends TestCase
{
    use RefreshDatabase;

    private function provider(array $overrides = []): Provider
    {
        return Provider::query()->create(array_merge([
            'key' => 'tgju',
            'driver' => 'tgju',
            'name' => 'TGJU',
            'name_fa' => 'تی‌جی‌جی‌یو',
            'is_active' => true,
            'health_state' => HealthState::Degraded->value,
            'consecutive_failures' => 3,
            'last_latency_ms' => 900,
            'last_symbol_count' => 12,
            'last_error' => 'khata ye ghadimi az DB',
        ], $overrides));
    }

    public function test_live_fields_overlay_the_db_but_state_stays_db(): void
    {
        $this->provider();

        $cache = new ArrayHotCache;
        $cache->putHealth(new ProviderHealthDto(
            providerKey: 'tgju',
            succeeded: true,
            attemptedAt: CarbonImmutable::parse('2026-10-06 10:00:00'),
            latencyMs: 55,
            symbolCount: 16,
            attempts: 2,
        ));

        $rows = (new ProviderHealthQuery($cache))->overview();

        self::assertCount(1, $rows);
        $row = $rows[0];

        // Lahze-i az HOT:
        self::assertSame(55, $row['latencyMs']);
        self::assertSame(16, $row['symbolCount']);
        self::assertTrue($row['retried']);
        self::assertNull($row['error'], 'hot e movafagh yani khata ye ghadimi ye DB nabayad neshun dade she.');

        // 🔴 Shomaresh/state az DB — hot DTO per-tick ast va
        // `consecutiveFailures` esh hamishe 0 (state esh dorugh e «healthy»
        // mishod).
        self::assertSame(HealthState::Degraded, $row['state']);
        self::assertSame(3, $row['consecutiveFailures']);
    }

    public function test_without_hot_cache_the_db_answers_alone(): void
    {
        $this->provider();

        $cache = new ArrayHotCache;
        $cache->breakIt();

        $rows = (new ProviderHealthQuery($cache))->overview();

        self::assertCount(1, $rows);
        self::assertSame(900, $rows[0]['latencyMs']);
        self::assertSame('khata ye ghadimi az DB', $rows[0]['error']);
        self::assertSame(HealthState::Degraded, $rows[0]['state']);
    }

    public function test_inactive_providers_are_listed_too(): void
    {
        $this->provider(['is_active' => false]);

        $rows = (new ProviderHealthQuery(new ArrayHotCache))->overview();

        // Panel bayad provider e khamoosh ro HAM bebine (roshan kardanesh
        // az hamin ja shoru' mishe) — faghat flag esh ma'lum bashe.
        self::assertCount(1, $rows);
        self::assertFalse($rows[0]['isActive']);
    }
}
