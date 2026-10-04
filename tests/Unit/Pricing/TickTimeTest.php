<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Domain\ValueObjects\TickTime;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * `TickTime` paye ye idempotency e kol e write path ast: hoviyat e yek point
 * tu Influx `(measurement, tagset, field, timestamp)` ast, pas agar timestamp
 * floor nashe, har replay e spool yek radif e TAKRARI misaze jaye overwrite.
 */
final class TickTimeTest extends TestCase
{
    public function test_floors_to_the_fifteen_second_grid(): void
    {
        // 12:21:33 -> 12:21:30
        $at = CarbonImmutable::parse('2026-10-04T12:21:33Z');

        self::assertSame('2026-10-04T12:21:30+00:00', TickTime::fromCarbon($at)->toString());
    }

    public function test_every_moment_inside_a_window_maps_to_the_same_tick(): void
    {
        $base = CarbonImmutable::parse('2026-10-04T12:21:30Z');

        $timestamps = [];
        for ($second = 0; $second < 15; $second++) {
            $timestamps[] = TickTime::fromCarbon($base->addSeconds($second))->timestamp();
        }

        self::assertCount(1, array_unique($timestamps));
    }

    public function test_next_window_produces_a_different_tick(): void
    {
        $base = CarbonImmutable::parse('2026-10-04T12:21:30Z');

        self::assertNotSame(
            TickTime::fromCarbon($base)->timestamp(),
            TickTime::fromCarbon($base->addSeconds(15))->timestamp(),
        );
    }

    public function test_grid_size_is_configurable(): void
    {
        $at = CarbonImmutable::parse('2026-10-04T12:21:33Z');

        self::assertSame('2026-10-04T12:21:00+00:00', TickTime::fromCarbon($at, 60)->toString());
    }

    public function test_exposes_milliseconds_for_the_chart(): void
    {
        $tick = TickTime::fromTimestamp(1_791_104_490);

        self::assertSame(1_791_104_490_000, $tick->timestampMs());
    }

    // ── lag ──────────────────────────────────────────────────────

    public function test_lag_is_the_distance_behind_upstream(): void
    {
        $tick = TickTime::fromTimestamp(1_791_104_490);
        $upstream = CarbonImmutable::createFromTimestampUTC(1_791_104_478);

        self::assertSame(12, $tick->lagSecondsFrom($upstream));
    }

    /** Provider-i ke timestamp nemide (estjt) lag esh sefr hesab mishe. */
    public function test_lag_is_zero_when_upstream_has_no_timestamp(): void
    {
        self::assertSame(0, TickTime::now()->lagSecondsFrom(null));
    }

    /** `lagSecondsFrom` hargez manfi nemide — baraye metric e Influx. */
    public function test_lag_never_goes_negative(): void
    {
        $tick = TickTime::fromTimestamp(1_791_104_490);
        $future = CarbonImmutable::createFromTimestampUTC(1_791_104_600);

        self::assertSame(0, $tick->lagSecondsFrom($future));
    }

    /**
     * ...vali nesakhe ELAMATDAR bayad manfi bede, chun `ts` e ayande ya'ni
     * clock skew ya timestamp e kharab e upstream va bayad rad she.
     */
    public function test_signed_lag_reveals_a_future_upstream_timestamp(): void
    {
        $tick = TickTime::fromTimestamp(1_791_104_490);
        $future = CarbonImmutable::createFromTimestampUTC(1_791_104_600);

        self::assertSame(-110, $tick->signedLagSecondsFrom($future));
    }

    public function test_now_is_aligned_to_the_grid(): void
    {
        self::assertSame(0, TickTime::now()->timestamp() % 15);
    }
}
