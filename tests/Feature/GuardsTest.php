<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 🔴 Mohafez: test ha HARGEZ nabayad be database e dev dast bezanan.
 *
 * Container e `octane` `DB_CONNECTION=pgsql` va `DB_DATABASE=priceboard` ro
 * be onvan e env e VAGHEI ye OS export mikone. Env e OS be sorat e default
 * bar `phpunit.xml` BARANDE mishe — va `RefreshDatabase` un vaght database e
 * dev ro `migrate:fresh` mikone.
 *
 * `force="true"` tu `phpunit.xml` jologir e un ast. In test un mohafez ro
 * negahbani mikone: agar ruzi kasi `force` ro bardare، inja fail mishe —
 * na ba'd az inke hame ye data ye dev rafte.
 */
final class GuardsTest extends TestCase
{
    public function test_tests_never_touch_the_dev_database(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_the_connection_in_use_is_really_sqlite(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertNotSame('priceboard', DB::connection()->getDatabaseName());
    }

    public function test_cache_and_queue_are_not_the_shared_services(): void
    {
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame('array', config('session.driver'));
    }

    /**
     * Redis e gheymat bayad ru database e 15 bashe، na 0.
     * `RedisHotCacheTest` `flushdb()` mizane — ru db e 0 un tablo ye dev ro
     * pak mikone.
     */
    public function test_price_redis_is_isolated_from_dev(): void
    {
        $this->assertSame('15', (string) config('database.redis.prices.database'));
    }
}
