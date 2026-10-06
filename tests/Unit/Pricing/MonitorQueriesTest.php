<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Application\Dto\UpstreamCallDto;
use App\Modules\Pricing\Application\Queries\TickReportQuery;
use App\Modules\Pricing\Application\Queries\UpstreamCallQuery;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\ArrayHotCache;
use Tests\Support\Fakes\InMemoryUpstreamCallLog;

/**
 * Kholase va filter-meta ye safhe haye monitor (P4-18) — mantegh e mahz ru
 * array، pas bedun e Redis/Laravel test mishe. Blade faghat namayesh mide.
 */
final class MonitorQueriesTest extends TestCase
{
    // ── Tick report ─────────────────────────────────────────────────

    /** @param array<int, array<string, mixed>> $rejections */
    private function report(
        string $at,
        array $providers,
        array $rejections = [],
        bool $skipped = false,
        array $unmapped = [],
    ): array {
        return [
            'at' => $at,
            'skipped' => $skipped,
            'skip_reason' => $skipped ? 'hich provider e fa\'ali nobat nadasht' : null,
            'accepted' => 10,
            'rejected' => count($rejections),
            'duration_ms' => 9_000,
            'providers' => $providers,
            'sinks' => [],
            'rejections' => $rejections,
            'unmapped' => $unmapped,
        ];
    }

    private function okProvider(int $latency, bool $retried = false): array
    {
        return ['succeeded' => true, 'latency_ms' => $latency, 'symbol_count' => 16, 'retried' => $retried, 'error' => null];
    }

    private function failedProvider(string $error): array
    {
        return ['succeeded' => false, 'latency_ms' => 0, 'symbol_count' => 0, 'retried' => false, 'error' => $error];
    }

    private function rejection(string $provider, string $symbol, string $category): array
    {
        return ['provider' => $provider, 'symbol' => $symbol, 'side' => 'last', 'reason' => $category.':x', 'category' => $category, 'minor' => 100];
    }

    private function ticks(): TickReportQuery
    {
        return new TickReportQuery(new ArrayHotCache);
    }

    public function test_the_tick_summary_is_per_provider(): void
    {
        $reports = [
            $this->report('2026-10-06T10:00:30Z', ['tgju' => $this->okProvider(1_000), 'estjt' => $this->failedProvider('cURL error 28')],
                [$this->rejection('tgju', 'ons', 'stale'), $this->rejection('tgju', 'silver', 'stale')]),
            $this->report('2026-10-06T10:00:00Z', ['tgju' => $this->okProvider(3_000, retried: true), 'estjt' => $this->okProvider(8_000)],
                [$this->rejection('estjt', 'xau-18k-g-irr', 'deviation')]),
        ];

        $summary = $this->ticks()->summarize($reports);

        self::assertSame(2, $summary['tgju']['ticks']);
        self::assertSame(100, $summary['tgju']['success_rate']);
        self::assertSame(2_000, $summary['tgju']['avg_latency_ms']);
        self::assertSame(3_000, $summary['tgju']['max_latency_ms']);
        self::assertSame(1, $summary['tgju']['retried']);
        self::assertSame(2, $summary['tgju']['rejections']);
        self::assertSame(['stale' => 2], $summary['tgju']['by_category']);

        self::assertSame(50, $summary['estjt']['success_rate']);
        self::assertSame(1, $summary['estjt']['failed']);
        // Latency e talash e shekaste (0) miangin ro paeen nemikeshe.
        self::assertSame(8_000, $summary['estjt']['avg_latency_ms']);
        self::assertSame('cURL error 28', $summary['estjt']['last_error']);
        self::assertSame('2026-10-06T10:00:30Z', $summary['estjt']['last_error_at']);
    }

    public function test_skipped_ticks_do_not_count_in_the_summary(): void
    {
        $summary = $this->ticks()->summarize([
            $this->report('2026-10-06T10:00:15Z', [], skipped: true),
            $this->report('2026-10-06T10:00:00Z', ['tgju' => $this->okProvider(1_000)]),
        ]);

        self::assertSame(['tgju'], array_keys($summary));
        self::assertSame(1, $summary['tgju']['ticks']);
    }

    public function test_rejection_rows_are_flat_and_carry_the_tick_time(): void
    {
        $rows = $this->ticks()->rejectionRows([
            $this->report('2026-10-06T10:00:30Z', ['tgju' => $this->okProvider(1)], [$this->rejection('tgju', 'ons', 'stale')]),
            $this->report('2026-10-06T10:00:00Z', ['estjt' => $this->okProvider(1)], [$this->rejection('estjt', 'silver', 'deviation')]),
        ]);

        self::assertCount(2, $rows);
        self::assertSame('2026-10-06T10:00:30Z', $rows[0]['at']);
        self::assertSame('tgju', $rows[0]['provider']);
        self::assertSame('estjt', $rows[1]['provider']);
        self::assertSame('deviation', $rows[1]['category']);
    }

    public function test_filter_meta_marks_problems_and_collects_keys(): void
    {
        $query = $this->ticks();

        $clean = $query->filterMeta($this->report('t', ['tgju' => $this->okProvider(1)]));
        self::assertFalse($clean['problem']);
        self::assertSame(['tgju'], $clean['providers']);

        $failed = $query->filterMeta($this->report('t', ['tgju' => $this->okProvider(1), 'estjt' => $this->failedProvider('x')]));
        self::assertTrue($failed['problem']);
        self::assertSame(['estjt'], $failed['failed']);

        $rejected = $query->filterMeta($this->report('t', ['tgju' => $this->okProvider(1)], [$this->rejection('tgju', 'ons', 'stale')]));
        self::assertTrue($rejected['problem']);
        self::assertSame(['ons'], $rejected['symbols']);
        self::assertSame(['stale'], $rejected['categories']);

        $unmapped = $query->filterMeta($this->report('t', ['tgju' => $this->okProvider(1)], unmapped: ['tgju' => ['پلاتین']]));
        self::assertTrue($unmapped['problem'], 'label e na-shenakhte ham moshkel e.');
    }

    public function test_filter_options_are_unique_and_sorted(): void
    {
        $rows = $this->ticks()->rejectionRows([
            $this->report('t2', ['tgju' => $this->okProvider(1)], [$this->rejection('tgju', 'silver', 'stale'), $this->rejection('tgju', 'ons', 'stale')]),
            $this->report('t1', ['estjt' => $this->okProvider(1)], [$this->rejection('estjt', 'ons', 'deviation')]),
        ]);

        $options = $this->ticks()->filterOptions($rows);

        self::assertSame(['ons', 'silver'], $options['symbols']);
        self::assertSame(['deviation', 'stale'], $options['categories']);
    }

    // ── Upstream ────────────────────────────────────────────────────

    private function upstreamCall(string $provider, bool $ok, int $latency, int $attempt = 1, ?string $error = null, string $at = '2026-10-06T10:00:00Z'): UpstreamCallDto
    {
        return new UpstreamCallDto(
            providerKey: $provider,
            succeeded: $ok,
            at: CarbonImmutable::parse($at),
            method: 'GET',
            url: 'https://example.test',
            status: $ok ? 200 : null,
            latencyMs: $latency,
            attempt: $attempt,
            error: $error,
        );
    }

    private function upstream(UpstreamCallDto ...$calls): UpstreamCallQuery
    {
        $log = new InMemoryUpstreamCallLog;

        // Fake jadid tarin ro aval mizare — pas be tartib e zamani push kon.
        foreach (array_reverse($calls) as $call) {
            $log->push($call);
        }

        return new UpstreamCallQuery($log);
    }

    public function test_the_upstream_summary_is_per_provider(): void
    {
        $query = $this->upstream(
            $this->upstreamCall('estjt', false, 0, error: 'cURL error 28: Operation timed out after 20001 milliseconds', at: '2026-10-06T10:00:30Z'),
            $this->upstreamCall('estjt', true, 13_000, attempt: 2, at: '2026-10-06T10:00:15Z'),
            $this->upstreamCall('tgju', true, 1_000),
            $this->upstreamCall('tgju', true, 3_000),
        );

        $summary = $query->summarize($query->recent(), slowMs: 12_000);

        self::assertSame(4, $summary['estjt']['calls'] + $summary['tgju']['calls']);
        self::assertSame(50, $summary['estjt']['success_rate']);
        self::assertSame(1, $summary['estjt']['retries']);
        self::assertSame(1, $summary['estjt']['slow']);
        self::assertSame(13_000, $summary['estjt']['avg_latency_ms']);
        self::assertSame('2026-10-06T10:00:30+00:00', $summary['estjt']['last_error_at']);
        self::assertSame(100, $summary['tgju']['success_rate']);
        self::assertSame(2_000, $summary['tgju']['avg_latency_ms']);
        self::assertSame(0, $summary['tgju']['slow']);
    }

    public function test_upstream_filter_meta(): void
    {
        $query = $this->upstream();

        $meta = $query->filterMeta(
            ['provider' => 'estjt', 'succeeded' => false, 'attempt' => 2, 'latency_ms' => 13_000, 'url' => 'https://E.test', 'error' => 'cURL 28', 'status' => null],
            slowMs: 12_000,
        );

        self::assertSame('estjt', $meta['p']);
        self::assertFalse($meta['ok']);
        self::assertTrue($meta['retry']);
        self::assertTrue($meta['slow']);
        // Lowercase + «قطع» baraye call e bedun e pasokh — search e «قطع» kar kone.
        self::assertStringContainsString('https://e.test', $meta['text']);
        self::assertStringContainsString('قطع', $meta['text']);
    }

    public function test_top_errors_group_messages_that_differ_only_in_long_numbers(): void
    {
        $query = $this->upstream(
            $this->upstreamCall('estjt', false, 0, error: 'cURL error 28: Operation timed out after 20001 milliseconds'),
            $this->upstreamCall('estjt', false, 0, error: 'cURL error 28: Operation timed out after 20004 milliseconds'),
            $this->upstreamCall('tgju', false, 0, error: 'pasokh e na-movafagh (503).'),
        );

        $errors = $query->topErrors($query->recent());

        self::assertCount(2, $errors);
        // Por-tekrar tarin aval.
        self::assertSame('estjt', $errors[0]['provider']);
        self::assertSame(2, $errors[0]['count']);
        // Status e 3-raghami (503) NABAYAD normalize she — ma'ni dare.
        self::assertStringContainsString('503', $errors[1]['message']);
    }
}
