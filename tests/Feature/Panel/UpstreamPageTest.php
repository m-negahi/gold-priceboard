<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Pricing\Application\Actions\ProviderAction;
use App\Modules\Pricing\Application\Dto\ProviderInputDto;
use App\Modules\Pricing\Application\Dto\UpstreamCallDto;
use App\Modules\Pricing\Domain\Ports\UpstreamLogPort;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\Support\Fakes\InMemoryUpstreamCallLog;
use Tests\TestCase;

/**
 * Safhe ye `/dashboard/monitor/upstream` (P4-14) — daftarche ye call haye
 * MA be provider ha، na call e moshtari be ma.
 */
final class UpstreamPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Test haye adapter e Redis entry ja mizaran — bedun e flush،
        // empty-state hichvaght dide nemishe.
        Redis::connection()->flushdb();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function fakeLogWith(UpstreamCallDto ...$calls): InMemoryUpstreamCallLog
    {
        $log = new InMemoryUpstreamCallLog;

        foreach ($calls as $call) {
            $log->push($call);
        }

        $this->app->instance(UpstreamLogPort::class, $log);

        return $log;
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('panel.monitor.upstream'))->assertRedirect(route('login'));
    }

    public function test_the_page_shows_an_honest_empty_state(): void
    {
        $this->actingAs($this->admin())->get(route('panel.monitor.upstream'))
            ->assertOk()
            ->assertSee('هنوز فراخوانی‌ای ثبت نشده');
    }

    public function test_logged_calls_reach_the_page_with_provider_names(): void
    {
        (new ProviderAction)->create(ProviderInputDto::fromArray([
            'key' => 'tgju',
            'driver' => 'tgju',
            'name' => 'TGJU',
            'name_fa' => 'طلا و ارز',
            'is_active' => true,
        ]));

        $this->fakeLogWith(
            new UpstreamCallDto(
                providerKey: 'tgju',
                succeeded: false,
                at: CarbonImmutable::parse('2026-10-06T10:00:00Z'),
                method: 'GET',
                url: 'https://call1.example/prices',
                status: 503,
                latencyMs: 2_400,
                attempt: 2,
                error: 'pasokh e na-movafagh (503).',
            ),
            new UpstreamCallDto(
                providerKey: 'tgju',
                succeeded: true,
                at: CarbonImmutable::parse('2026-10-06T10:00:15Z'),
                method: 'GET',
                url: 'https://call2.example/prices',
                status: 200,
                latencyMs: 520,
                symbolCount: 16,
                bytes: 175_000,
            ),
        );

        $response = $this->actingAs($this->admin())->get(route('panel.monitor.upstream'))->assertOk();

        // Data ye har do call (URL ha ba'd az @js escape mishan، pas faghat
        // host e bedun e slash assert mishe) + esm e Farsi ye provider.
        $response->assertSee('call1.example');
        $response->assertSee('call2.example');
        $response->assertSee('503');
        $response->assertSee('pasokh e na-movafagh', false);
        $response->assertSee('طلا و ارز');
    }

    // ── P4-18: kholase + khata haye por-tekrar ──────────────────────

    public function test_the_list_shows_a_per_provider_summary_and_top_errors(): void
    {
        $this->fakeLogWith(
            new UpstreamCallDto(providerKey: 'estjt', succeeded: false, at: CarbonImmutable::parse('2026-10-06T10:00:00Z'), method: 'POST', url: 'https://e.test', error: 'cURL error 28: timed out after 20001 ms', id: 'a1'),
            new UpstreamCallDto(providerKey: 'estjt', succeeded: false, at: CarbonImmutable::parse('2026-10-06T10:00:15Z'), method: 'POST', url: 'https://e.test', error: 'cURL error 28: timed out after 20004 ms', id: 'a2'),
            new UpstreamCallDto(providerKey: 'estjt', succeeded: true, at: CarbonImmutable::parse('2026-10-06T10:00:30Z'), method: 'POST', url: 'https://e.test', status: 200, latencyMs: 8_000, id: 'a3'),
        );

        $this->actingAs($this->admin())->get(route('panel.monitor.upstream'))
            ->assertOk()
            // Kholase: 1 az 3 movafagh = 33%.
            ->assertViewHas('summary', fn (array $s): bool => $s['estjt']['success_rate'] === 33)
            // Do timeout e ba adad e motefavet YEK group-and.
            ->assertViewHas('topErrors', fn (array $e): bool => $e[0]['count'] === 2)
            ->assertSee('خطاهای پرتکرار');
    }

    // ── P4-20: jozyat e yek call (Telescope-vaar) ───────────────────

    public function test_the_detail_page_shows_request_and_response_payload(): void
    {
        $this->fakeLogWith(new UpstreamCallDto(
            providerKey: 'tgju',
            succeeded: false,
            at: CarbonImmutable::parse('2026-10-06T10:00:00Z'),
            method: 'GET',
            url: 'https://call1.example/prices',
            status: 503,
            latencyMs: 2_400,
            error: 'pasokh e na-movafagh (503).',
            id: 'deadbeef01',
            requestHeaders: ['User-Agent' => 'PriceBoard/1.0', 'Authorization' => '[redacted]'],
            responseHeaders: ['Retry-After' => '30'],
            responseBody: '{"error":"rate limited","retry":30}',
        ));

        $this->actingAs($this->admin())->get(route('panel.monitor.upstream.show', 'deadbeef01'))
            ->assertOk()
            ->assertSee('PriceBoard/1.0')
            ->assertSee('[redacted]')
            ->assertSee('Retry-After')
            // JSON pretty mishe (`": "` ba fasele) — va ESCAPE-shode: body e
            // upstream data ye gheyr-e-motabar e (XSS)، pas `assertSee` e
            // escape-dar.
            ->assertSee('"error": "rate limited"')
            ->assertDontSee('"error": "rate limited"', false)
            ->assertSee('pasokh e na-movafagh', false);
    }

    public function test_a_truncated_preview_shows_readable_persian_not_escapes(): void
    {
        // estjt `\u0627` mifreste va preview e 2KB JSON e NAGHES-e (pretty
        // nemishe) — vali matn e Farsi bayad khunda bashe.
        $this->fakeLogWith(new UpstreamCallDto(
            providerKey: 'estjt', succeeded: true, at: CarbonImmutable::parse('2026-10-06T10:00:00Z'),
            method: 'POST', url: 'https://e.test', status: 200, id: 'trunc01',
            responseBody: '{"gold":"<td>\\u0627\\u0646\\u0633</td>', bodyTruncated: true, bytes: 5_462,
        ));

        $this->actingAs($this->admin())->get(route('panel.monitor.upstream.show', 'trunc01'))
            ->assertOk()
            ->assertSee('انس')
            ->assertSee('فقط بخش اول بدنه');
    }

    public function test_the_list_links_each_row_to_its_detail_page(): void
    {
        $this->fakeLogWith(new UpstreamCallDto(
            providerKey: 'tgju', succeeded: true, at: CarbonImmutable::parse('2026-10-06T10:00:00Z'),
            method: 'GET', url: 'https://x.test', status: 200, id: 'feedc0de',
        ));

        $this->actingAs($this->admin())->get(route('panel.monitor.upstream'))
            ->assertOk()
            ->assertSee(route('panel.monitor.upstream.show', 'feedc0de'), false);
    }

    public function test_an_expired_id_gets_an_honest_404(): void
    {
        $this->fakeLogWith();

        $this->actingAs($this->admin())->get(route('panel.monitor.upstream.show', 'gone'))
            ->assertNotFound()
            ->assertSee('از دفترچه خارج شده');
    }
}
