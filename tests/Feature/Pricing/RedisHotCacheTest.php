<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Dto\ProviderHealthDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\Enums\Direction;
use App\Modules\Pricing\Domain\Enums\HealthState;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Infrastructure\Cache\RedisHotCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Ru Redis e VAGHEI ejra mishe (`redis-prices`), ru database e 15 ke tu
 * `phpunit.xml` majbur shode — vagarna test tablo ye dev ro pak mikone.
 */
final class RedisHotCacheTest extends TestCase
{
    private RedisHotCache $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new RedisHotCache;

        if (! $this->cache->isAvailable()) {
            $this->markTestSkipped('redis-prices dar dastres nist.');
        }

        Redis::connection('prices')->flushdb();
    }

    protected function tearDown(): void
    {
        if ($this->cache->isAvailable()) {
            Redis::connection('prices')->flushdb();
        }

        parent::tearDown();
    }

    private function snapshot(
        string $providerKey = 'tgju',
        int $minor = 266_400_000,
        Instrument $instrument = Instrument::Gold18Gram,
        Side $side = Side::Last,
    ): SnapshotDto {
        return new SnapshotDto(
            providerKey: $providerKey,
            instrument: $instrument,
            side: $side,
            price: new Price($minor, $instrument->quote()),
            quotedAt: CarbonImmutable::parse('2026-10-04T12:21:30Z'),
            previous: new Price($minor - 100_000, $instrument->quote()),
            dayOpen: new Price($minor - 1_000_000, $instrument->quote()),
            dayHigh: new Price($minor + 2_000_000, $instrument->quote()),
            dayLow: new Price($minor - 3_000_000, $instrument->quote()),
            changePercent: 1.58,
            direction: Direction::Up,
            upstreamAt: CarbonImmutable::parse('2026-10-04T12:21:18Z'),
            upstreamLagSeconds: 12,
        );
    }

    // ── tablo ────────────────────────────────────────────────────

    public function test_board_starts_empty(): void
    {
        $this->assertSame([], $this->cache->board());
    }

    public function test_writes_and_reads_the_board(): void
    {
        $this->cache->putBoard([$this->snapshot()]);

        $board = $this->cache->board();

        $this->assertCount(1, $board);
        $this->assertSame(266_400_000, $board['tgju:xau-18k-g-irr:last']->price->minor);
    }

    public function test_round_trip_preserves_every_field(): void
    {
        $original = $this->snapshot();
        $this->cache->putBoard([$original]);

        $loaded = $this->cache->board()[$original->key()];

        $this->assertSame($original->providerKey, $loaded->providerKey);
        $this->assertSame($original->instrument, $loaded->instrument);
        $this->assertSame($original->side, $loaded->side);
        $this->assertTrue($original->price->equals($loaded->price));
        $this->assertTrue($original->previous->equals($loaded->previous));
        $this->assertTrue($original->dayHigh->equals($loaded->dayHigh));
        $this->assertTrue($original->dayLow->equals($loaded->dayLow));
        $this->assertSame($original->changePercent, $loaded->changePercent);
        $this->assertSame($original->direction, $loaded->direction);
        $this->assertSame($original->upstreamLagSeconds, $loaded->upstreamLagSeconds);
        $this->assertSame(
            $original->quotedAt->toIso8601String(),
            $loaded->quotedAt->toIso8601String(),
        );
    }

    public function test_board_holds_many_providers_and_symbols(): void
    {
        $this->cache->putBoard([
            $this->snapshot('tgju', 266_400_000),
            $this->snapshot('estjt', 266_818_000),
            $this->snapshot('tgju', 2_753_400_000, Instrument::CoinEmami),
        ]);

        $this->assertCount(3, $this->cache->board());
    }

    /**
     * 🔴 Ghanun e shomare yek: kelid haye gheymat BEDUN e TTL.
     *
     * Hamrah e `noeviction` ru `redis-prices`, in ya'ni gheymat hargez
     * khod-be-khod nemire. Agar ruzi kasi yek `expire` ezafe kone, in test
     * fail mikone — va hamin hadaf esh ast.
     */
    public function test_price_keys_never_expire(): void
    {
        $this->cache->putBoard([$this->snapshot()]);

        $connection = Redis::connection('prices');

        // ⚠️ Prefix ro DASTI ezafe nakon — client e Laravel khodesh un ro
        // mizare. Ba prefix e dasti be yek kelid e khiali eshare mikoni va
        // test saket be dalil e ghalat pass mishe.
        //
        // `-1` ya'ni kelid hast vali TTL nadare. `-2` ya'ni kelid nist.
        $this->assertSame(-1, $connection->ttl('pb:board'));
        $this->assertSame(-1, $connection->ttl('pb:board:v'));
    }

    /** USD ba scale 4 bayad salem bargarde — na round beshe be dollar e sahih. */
    public function test_usd_scale_survives_the_round_trip(): void
    {
        $this->cache->putBoard([$this->snapshot('tgju', 41_401_900, Instrument::GoldOunce)]);

        $loaded = array_values($this->cache->board())[0];

        $this->assertSame(4, $loaded->price->quote->scale());
        $this->assertSame(4140.19, $loaded->price->toFloat());
    }

    public function test_writing_nothing_is_a_no_op(): void
    {
        $this->cache->putBoard([]);

        $this->assertSame([], $this->cache->board());
        $this->assertSame(0, $this->cache->boardVersion());
    }

    // ── version ──────────────────────────────────────────────────

    public function test_version_starts_at_zero_and_increments(): void
    {
        $this->assertSame(0, $this->cache->boardVersion());

        $this->cache->putBoard([$this->snapshot()]);
        $this->assertSame(1, $this->cache->boardVersion());

        $this->cache->putBoard([$this->snapshot(minor: 267_000_000)]);
        $this->assertSame(2, $this->cache->boardVersion());
    }

    /**
     * Version baraye ETag e `/api/board.json` ast: poll e bi-taghir bayad
     * 304 bar gardune, na kol e payload ro dobare befreste.
     */
    public function test_version_is_stable_between_writes(): void
    {
        $this->cache->putBoard([$this->snapshot()]);

        $this->assertSame($this->cache->boardVersion(), $this->cache->boardVersion());
    }

    // ── salamati ─────────────────────────────────────────────────

    public function test_stores_health_per_provider(): void
    {
        $now = CarbonImmutable::parse('2026-10-04T12:21:30Z');

        $this->cache->putHealth(new ProviderHealthDto('tgju', true, $now, latencyMs: 180, symbolCount: 16));
        $this->cache->putHealth(new ProviderHealthDto(
            'estjt', false, $now, error: 'timeout', consecutiveFailures: 4,
        ));

        $health = $this->cache->health();

        $this->assertCount(2, $health);
        $this->assertTrue($health['tgju']->succeeded);
        $this->assertSame(180, $health['tgju']->latencyMs);
        $this->assertSame(16, $health['tgju']->symbolCount);
        $this->assertSame('timeout', $health['estjt']->error);
    }

    public function test_health_state_is_derived_from_failure_count(): void
    {
        $now = CarbonImmutable::parse('2026-10-04T12:21:30Z');

        $this->cache->putHealth(new ProviderHealthDto('a', true, $now, consecutiveFailures: 0));
        $this->cache->putHealth(new ProviderHealthDto('b', false, $now, consecutiveFailures: 4));
        $this->cache->putHealth(new ProviderHealthDto('c', false, $now, consecutiveFailures: 12));

        $health = $this->cache->health();

        $this->assertSame(HealthState::Healthy, $health['a']->state());
        $this->assertSame(HealthState::Degraded, $health['b']->state());
        $this->assertSame(HealthState::Down, $health['c']->state());
    }

    public function test_health_overwrites_the_previous_entry(): void
    {
        $now = CarbonImmutable::parse('2026-10-04T12:21:30Z');

        $this->cache->putHealth(new ProviderHealthDto('tgju', false, $now, error: 'timeout'));
        $this->cache->putHealth(new ProviderHealthDto('tgju', true, $now));

        $health = $this->cache->health();

        $this->assertCount(1, $health);
        $this->assertTrue($health['tgju']->succeeded);
        $this->assertNull($health['tgju']->error);
    }

    public function test_health_starts_empty(): void
    {
        $this->assertSame([], $this->cache->health());
    }

    // ── taab-avari ───────────────────────────────────────────────

    public function test_reports_availability_without_throwing(): void
    {
        $this->assertTrue($this->cache->isAvailable());
    }

    /**
     * Shabih-sazi ye evict shodan: Redis salem ast vali kelid rafte.
     * Reader bayad array e khali begire (va ba'd be Postgres fallback kone),
     * na inke exception bokhore.
     */
    public function test_missing_key_returns_empty_instead_of_throwing(): void
    {
        $this->cache->putBoard([$this->snapshot()]);
        Redis::connection('prices')->flushdb();

        $this->assertSame([], $this->cache->board());
    }

    /** JSON e kharab nabayad tablo ro beterkune. */
    public function test_corrupt_payload_returns_empty(): void
    {
        Redis::connection('prices')->set('pb:board', 'in json nist }{');

        $this->assertSame([], $this->cache->board());
    }

    /** Radif-i ba symbol e nashenas rad mishe, baghi ye tablo salem mimune. */
    public function test_unknown_symbol_row_is_skipped_not_fatal(): void
    {
        $good = $this->snapshot();

        $this->cache->putBoard([$good]);

        $payload = json_decode((string) Redis::connection('prices')->get('pb:board'), true);
        $this->assertCount(1, $payload, 'payload bayad vaghe\'an khunde shode bashe.');

        $payload['tgju:symbol-e-khiali:last'] = [
            'p' => 'tgju', 's' => 'symbol-e-khiali', 'sd' => 'last', 'q' => 'IRR', 'v' => 1,
        ];
        Redis::connection('prices')->set('pb:board', json_encode($payload));

        $board = $this->cache->board();

        $this->assertCount(1, $board);
        $this->assertArrayHasKey($good->key(), $board);
    }
}
