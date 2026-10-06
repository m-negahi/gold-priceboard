<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Dto\UpstreamCallDto;
use App\Modules\Pricing\Infrastructure\Monitoring\RedisUpstreamCallLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Adapter e Redis e daftarche ye upstream (P4-14) — hamun olgu ye
 * `ApiRequestLog`: list e Redis، jadid tarin aval، saghf e sabet.
 */
final class RedisUpstreamCallLogTest extends TestCase
{
    private RedisUpstreamCallLog $log;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->flushdb();
        $this->log = new RedisUpstreamCallLog;
    }

    private function entry(string $provider = 'tgju', int $attempt = 1, bool $succeeded = true): UpstreamCallDto
    {
        return new UpstreamCallDto(
            providerKey: $provider,
            succeeded: $succeeded,
            at: CarbonImmutable::parse('2026-10-06T10:00:00Z'),
            method: 'GET',
            url: 'https://example.test/prices',
            status: $succeeded ? 200 : 503,
            latencyMs: 2_400,
            attempt: $attempt,
            error: $succeeded ? null : 'pasokh e na-movafagh (503).',
            symbolCount: $succeeded ? 16 : 0,
            bytes: 175_000,
        );
    }

    public function test_calls_round_trip_newest_first(): void
    {
        $this->log->push($this->entry(provider: 'tgju'));
        $this->log->push($this->entry(provider: 'estjt', succeeded: false));

        $calls = $this->log->recent(10);

        $this->assertCount(2, $calls);
        $this->assertSame('estjt', $calls[0]->providerKey);
        $this->assertSame('tgju', $calls[1]->providerKey);

        // Round-trip e KAMEL e field ha — na faghat kelid e provider.
        $this->assertFalse($calls[0]->succeeded);
        $this->assertSame(503, $calls[0]->status);
        $this->assertSame(2_400, $calls[0]->latencyMs);
        $this->assertSame('pasokh e na-movafagh (503).', $calls[0]->error);
        $this->assertSame(200, $calls[1]->status);
        $this->assertNull($calls[1]->error);
        $this->assertSame(16, $calls[1]->symbolCount);
        $this->assertSame(175_000, $calls[1]->bytes);
        $this->assertSame('2026-10-06T10:00:00+00:00', $calls[1]->at->toIso8601String());
    }

    public function test_the_list_is_trimmed_to_its_cap(): void
    {
        // Bedun e trim، list e Redis bi-saghf bozorg mishe — monitoring
        // nabayad khodesh tabdil be moshkel e hafeze beshe.
        for ($i = 0; $i < RedisUpstreamCallLog::MAX + 25; $i++) {
            $this->log->push($this->entry(attempt: $i));
        }

        $this->assertCount(RedisUpstreamCallLog::MAX, $this->log->recent(RedisUpstreamCallLog::MAX * 2));
    }

    public function test_a_malformed_entry_is_skipped_not_fatal(): void
    {
        $this->log->push($this->entry());
        Redis::connection()->lpush('pb:upstream:requests', 'in json nist{');

        $calls = $this->log->recent(10);

        $this->assertCount(1, $calls);
        $this->assertSame('tgju', $calls[0]->providerKey);
    }

    public function test_detail_fields_round_trip(): void
    {
        $this->log->push(new UpstreamCallDto(
            providerKey: 'tgju', succeeded: false, at: CarbonImmutable::parse('2026-10-06T10:00:00Z'),
            method: 'POST', url: 'https://example.test', status: 503,
            id: 'abc123',
            requestHeaders: ['User-Agent' => 'PriceBoard'],
            requestBody: ['action' => 'new_price'],
            responseHeaders: ['Retry-After' => '30'],
            responseBody: '{"error":"x"}',
            bodyTruncated: true,
        ));

        $call = $this->log->recent(1)[0];

        $this->assertSame('abc123', $call->id);
        $this->assertSame(['User-Agent' => 'PriceBoard'], $call->requestHeaders);
        $this->assertSame(['action' => 'new_price'], $call->requestBody);
        $this->assertSame(['Retry-After' => '30'], $call->responseHeaders);
        $this->assertSame('{"error":"x"}', $call->responseBody);
        $this->assertTrue($call->bodyTruncated);
    }

    public function test_an_invalid_utf8_body_does_not_silently_drop_the_entry(): void
    {
        // 🔴 `json_encode` ru UTF-8 e kharab `false` mide -> `(string) false`
        // = '' -> entry SAKET gom mishod.
        $this->log->push(new UpstreamCallDto(
            providerKey: 'tgju', succeeded: false, at: CarbonImmutable::parse('2026-10-06T10:00:00Z'),
            method: 'GET', url: 'https://example.test', responseBody: "bad \xC3\x28 bytes",
        ));

        $this->assertCount(1, $this->log->recent(5));
    }

    public function test_find_returns_one_call_by_id(): void
    {
        $this->log->push(new UpstreamCallDto(providerKey: 'tgju', succeeded: true, at: CarbonImmutable::now(), method: 'GET', url: 'u', id: 'one'));
        $this->log->push(new UpstreamCallDto(providerKey: 'estjt', succeeded: true, at: CarbonImmutable::now(), method: 'GET', url: 'u', id: 'two'));

        $this->assertSame('tgju', $this->log->find('one')?->providerKey);
        $this->assertNull($this->log->find('nist'));
    }

    public function test_an_empty_store_gives_an_empty_list(): void
    {
        $this->assertSame([], $this->log->recent(10));
    }
}
