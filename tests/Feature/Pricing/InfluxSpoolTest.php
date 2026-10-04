<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Infrastructure\TimeSeries\InfluxSpool;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Spool jayi-e ke tick mire vaghti Influx down ast.
 *
 * Emn budanesh be yek chiz vabaste ast: timestamp e floor-shode. Chun
 * hoviyat e point tu Influx `(tagset, timestamp)` ast, replay OVERWRITE
 * mikone na duplicate — un dar `InfluxTimeSeriesTest` test shode.
 */
final class InfluxSpoolTest extends TestCase
{
    private InfluxSpool $spool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->spool = new InfluxSpool(maxLines: 100);

        try {
            Redis::connection('prices')->ping();
        } catch (\Throwable) {
            $this->markTestSkipped('redis-prices dar dastres nist.');
        }

        $this->spool->clear();
    }

    protected function tearDown(): void
    {
        $this->spool->clear();

        parent::tearDown();
    }

    /** @return array<int, string> */
    private function lines(int $count, string $prefix = 'price'): array
    {
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $lines[] = "{$prefix},provider=tgju,symbol=xau-18k-g-irr value={$i} 1791104490";
        }

        return $lines;
    }

    public function test_starts_empty(): void
    {
        $this->assertSame(0, $this->spool->size());
        $this->assertSame([], $this->spool->pull());
    }

    public function test_pushes_and_pulls_in_order(): void
    {
        $this->spool->push($this->lines(3));

        $this->assertSame(3, $this->spool->size());
        $this->assertSame($this->lines(3), $this->spool->pull());
    }

    /** `pull` bardasht ast — khat haye replay-shode nabayad dobare biyan. */
    public function test_pulling_removes_the_lines(): void
    {
        $this->spool->push($this->lines(3));
        $this->spool->pull();

        $this->assertSame(0, $this->spool->size());
    }

    public function test_pulls_in_batches(): void
    {
        $this->spool->push($this->lines(10));

        $this->assertCount(4, $this->spool->pull(4));
        $this->assertSame(6, $this->spool->size());
        $this->assertCount(6, $this->spool->pull(100));
    }

    public function test_batches_accumulate_across_pushes(): void
    {
        $this->spool->push($this->lines(2, 'a'));
        $this->spool->push($this->lines(2, 'b'));

        $pulled = $this->spool->pull();

        $this->assertCount(4, $pulled);
        $this->assertStringStartsWith('a,', $pulled[0]);
        $this->assertStringStartsWith('b,', $pulled[2]);
    }

    /**
     * 🔴 Saghf ejbari-e: bedun esh yek ghat'i ye tulani ye Influx Redis ro
     * por mikone — va chun `redis-prices` `noeviction` ast, un ya'ni
     * neveshtan e GHEYMAT ham shekast mikhore. Yek ghat'i ye farei tabdil
     * mishe be yek ghat'i ye kamel.
     */
    public function test_caps_the_spool_and_drops_the_oldest(): void
    {
        $this->spool->push($this->lines(150));

        $this->assertSame(100, $this->spool->size());

        $pulled = $this->spool->pull();

        // Ghadimi tarin ha oftadan — jadid tarin ha mundan.
        $this->assertStringContainsString('value=50 ', $pulled[0]);
        $this->assertStringContainsString('value=149 ', $pulled[99]);
    }

    public function test_cap_applies_across_multiple_pushes(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->spool->push($this->lines(50));
        }

        $this->assertSame(100, $this->spool->size());
    }

    public function test_pushing_nothing_is_a_no_op(): void
    {
        $this->assertTrue($this->spool->push([]));
        $this->assertSame(0, $this->spool->size());
    }

    public function test_clear_empties_the_spool(): void
    {
        $this->spool->push($this->lines(5));
        $this->spool->clear();

        $this->assertSame(0, $this->spool->size());
    }

    /** Spool khodesh mekanism e jobran e kharabi-e — nabayad tick ro bokoshe. */
    public function test_push_reports_failure_instead_of_throwing(): void
    {
        $this->assertTrue($this->spool->push($this->lines(1)));
    }
}
