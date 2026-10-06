<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Infrastructure\Cache\RedisHotCache;
use Illuminate\Support\Facades\Redis;
use Tests\Support\Fakes\ArrayHotCache;
use Tests\TestCase;

/**
 * Gharardad e daftarche ye tick report ru HAR DO implementation (dars e
 * P1-26: fake bayad gharardad ro emza kone، na nosخه ye mehrabun tar):
 * jadid tarin AVVAL، saghf e 50.
 */
final class TickReportLedgerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection('prices')->flushdb();
    }

    /** @return array<int, HotCachePort> */
    private function implementations(): array
    {
        return [new RedisHotCache, new ArrayHotCache];
    }

    public function test_newest_report_comes_first(): void
    {
        foreach ($this->implementations() as $cache) {
            $cache->putTickReport(['at' => 'A', 'accepted' => 1]);
            $cache->putTickReport(['at' => 'B', 'accepted' => 2]);

            $reports = $cache->tickReports();

            self::assertSame('B', $reports[0]['at'], $cache::class);
            self::assertSame('A', $reports[1]['at'], $cache::class);
        }
    }

    public function test_the_ledger_is_capped_at_fifty(): void
    {
        foreach ($this->implementations() as $cache) {
            for ($i = 1; $i <= 60; $i++) {
                $cache->putTickReport(['at' => "T{$i}"]);
            }

            $all = $cache->tickReports(100);

            self::assertCount(50, $all, $cache::class);
            self::assertSame('T60', $all[0]['at'], $cache::class);
            self::assertSame('T11', $all[49]['at'], $cache::class.' — ghadimi tarin ha bayad bioftan.');
        }
    }

    public function test_limit_is_respected(): void
    {
        foreach ($this->implementations() as $cache) {
            for ($i = 0; $i < 5; $i++) {
                $cache->putTickReport(['at' => (string) $i]);
            }

            self::assertCount(3, $cache->tickReports(3), $cache::class);
        }
    }
}
