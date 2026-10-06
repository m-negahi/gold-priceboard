<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * 🔴 DB e ZENDE ye connection — na config.
     *
     * Test e bala faghat CONFIG ro check mikard va sabz mimund dar hali ke
     * `flushdb()` e test ha Redis e DEV (db 0) ro pak mikard — session e
     * admin har ejra ye suite az bein mirafet (logout e mokarrar، 2026-10-06،
     * ba canary ta'eed shod). Dalil: `RedisManager` yek COPY az config ro
     * mozghe sakhtan migire va connection ro cache mikone، pas avaz kardan e
     * config ba'd az bootstrap ru connection e sakhte-shode asar nadasht.
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public static function isolatedRedisConnections(): array
    {
        return [
            'default (OTP، log ha، session e dev)' => ['default', 13],
            'prices (tablo)' => ['prices', 15],
            'cache (session e dev)' => ['cache', 14],
        ];
    }

    #[DataProvider('isolatedRedisConnections')]
    public function test_the_live_redis_connection_is_on_the_test_database(string $name, int $db): void
    {
        /** @var \Redis $client phpredis */
        $client = \Illuminate\Support\Facades\Redis::connection($name)->client();

        $this->assertSame($db, $client->getDbNum(), "connection e «{$name}» ru db e ZENDE ye ghalat ast — flushdb e test data ye dev ro pak mikone.");
    }

    public function test_telescope_storage_uses_only_the_isolated_test_database(): void
    {
        self::assertSame('sqlite', config('telescope.storage.database.connection'));
        self::assertSame(':memory:', DB::connection(config('telescope.storage.database.connection'))->getDatabaseName());
    }
}
