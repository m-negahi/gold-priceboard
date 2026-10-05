<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Dto\ProviderConfigDto;
use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Application\Pipeline\Stages\FetchStage;
use App\Modules\Pricing\Application\Pipeline\TickContext;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Enums\StagePolicy;
use App\Modules\Pricing\Domain\Ports\QuoteSourcePort;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Fakes\FakeQuoteSource;
use Tests\Support\Fakes\FrozenClock;
use Tests\TestCase;

/**
 * 🔴 `FetchStage` — tanha Stage-i ke ba SHABAKE harf mizane.
 *
 * ── Chera in file lazem bud ──────────────────────────────────────────
 * `TickPipelineTest` (test e vahed، bedun e Laravel) `FetchStage` ro AMDAN
 * kenar mizare chun `Http::pool` facade mikhahe، va jaye un `$context->raw`
 * ro mostaghim por mikone. Natije: Stage-i ke BISHTARIN halat e shekast ro
 * dare (pasokh e non-2xx، body e non-JSON، khata ye ettesal، pasokh e
 * gom-shode az pool) hich test nadasht.
 *
 * In test ba `Http::fake()` daghighan hamun masir e pool ro mizane.
 */
final class FetchStageTest extends TestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new FrozenClock('2026-10-05T10:00:00Z');
        // Hich request e vaghei nabayad az in test birun bere.
        Http::preventStrayRequests();
    }

    private function quote(int $minor = 266_400_000, string $provider = 'tgju'): RawQuoteDto
    {
        return new RawQuoteDto(
            providerKey: $provider,
            instrument: Instrument::Gold18Gram,
            side: Side::Last,
            price: Price::irr($minor),
            upstreamAt: $this->clock->now()->subSeconds(5),
        );
    }

    /** @param array<int, QuoteSourcePort> $sources */
    private function fetch(array $sources): TickContext
    {
        $providers = [];

        foreach ($sources as $source) {
            $providers[$source->key()] = new ProviderConfigDto($source->key());
        }

        $context = new TickContext(
            at: $this->clock->tick(),
            sources: $sources,
            providers: $providers,
        );

        (new FetchStage)->handle($context);

        return $context;
    }

    // ── Policy ──────────────────────────────────────────────────────

    public function test_the_stage_is_isolated_and_never_throws(): void
    {
        // Shekast e yek provider nabayad tick ro bokoshe — asli tarin
        // khasiat e yek site e moghayese.
        $this->assertSame(StagePolicy::Isolated, (new FetchStage)->policy());
        $this->assertSame('fetch', (new FetchStage)->name());
    }

    // ── Masir e pool ────────────────────────────────────────────────

    public function test_a_pooled_source_is_fetched_and_parsed(): void
    {
        Http::fake(['example.test/*' => Http::response(['current' => ['geram18' => []]])]);

        $source = (new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://example.test/prices');

        $context = $this->fetch([$source]);

        $this->assertCount(1, $context->raw);
        $this->assertTrue($context->health['tgju']->succeeded);

        // `parse()` bayad payload e VAGHEI ro gerefte bashe، na yek array e
        // khali — vagarna masir e pool faghat zahiran test shode.
        $this->assertSame(['current' => ['geram18' => []]], $source->lastPayload());
        $this->assertSame(1, $source->parseCount());
        $this->assertSame(0, $source->fetchCount(), 'masir e pool nabayad `fetch()` ro seda bezane.');
    }

    public function test_payload_meta_comes_from_the_real_body(): void
    {
        $body = ['current' => ['geram18' => ['p' => '266,400,000']]];
        Http::fake(['example.test/*' => Http::response($body)]);

        $context = $this->fetch([
            (new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://example.test/prices'),
        ]);

        // `last_payload_hash` baraye provider hayi ke timestamp NEMIDAN
        // (estjt) tanha rah e tashkhis e «feed yakh zade» ast، pas bayad az
        // body e kham biad، na az chizi ke Source misaze.
        $this->assertSame(md5((string) json_encode($body)), $context->health['tgju']->payloadHash);
    }

    public function test_a_post_source_sends_its_form_body(): void
    {
        Http::fake(['estjt.test/*' => Http::response(['gold' => '<table></table>'])]);

        $this->fetch([
            (new FakeQuoteSource('estjt'))
                ->returns([$this->quote(provider: 'estjt')])
                ->posting('https://estjt.test/admin-ajax.php', ['action' => 'new_price']),
        ]);

        // estjt faghat ba `POST action=new_price` javab mide.
        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://estjt.test/admin-ajax.php'
            && $request['action'] === 'new_price');
    }

    public function test_unmapped_labels_reach_the_report(): void
    {
        Http::fake(['example.test/*' => Http::response(['current' => []])]);

        $context = $this->fetch([
            (new FakeQuoteSource('tgju'))
                ->returns([$this->quote()])
                ->unmapped(['طلای ۲۱ عیار', 'پلاتین'])
                ->pooled('https://example.test/prices'),
        ]);

        // Label e jadid tu upstream ya'ni yek symbol ke SAKET gom mishe —
        // tanha rah e didanesh hamin ast.
        $this->assertSame(['طلای ۲۱ عیار', 'پلاتین'], $context->unmapped['tgju']);
    }

    // ── Halat haye shekast ──────────────────────────────────────────

    public function test_a_non_successful_response_is_recorded_as_a_failure(): void
    {
        Http::fake(['example.test/*' => Http::response('nope', 503)]);

        $context = $this->fetch([
            (new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://example.test/prices'),
        ]);

        $this->assertSame([], $context->raw);
        $this->assertFalse($context->health['tgju']->succeeded);
        $this->assertStringContainsString('503', (string) $context->health['tgju']->error);
    }

    public function test_a_non_json_body_is_recorded_as_a_failure(): void
    {
        // Yek safhe ye khata ye HTML (ya safhe ye challenge e Cloudflare) —
        // HTTP 200 vali JSON nist.
        Http::fake(['example.test/*' => Http::response('<html>503 Service Unavailable</html>')]);

        $context = $this->fetch([
            (new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://example.test/prices'),
        ]);

        $this->assertSame([], $context->raw);
        $this->assertStringContainsString('JSON', (string) $context->health['tgju']->error);
    }

    public function test_a_connection_error_is_recorded_as_a_failure(): void
    {
        // `Http::pool` baraye request e shekaste، yek Throwable tu array
        // mizare jaye yek Response. Agar handle nashe، kol e tick miterke.
        Http::fake(['example.test/*' => static fn (): never => throw new ConnectionException('cURL error 28: timeout')]);

        $context = $this->fetch([
            (new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://example.test/prices'),
        ]);

        $this->assertFalse($context->health['tgju']->succeeded);
        $this->assertStringContainsString('timeout', (string) $context->health['tgju']->error);
    }

    public function test_a_source_that_throws_while_parsing_is_recorded_as_a_failure(): void
    {
        Http::fake(['example.test/*' => Http::response(['current' => []])]);

        $context = $this->fetch([
            (new FakeQuoteSource('tgju'))->fails('markup avaz shode')->pooled('https://example.test/prices'),
        ]);

        $this->assertSame([], $context->raw);
        $this->assertStringContainsString('markup avaz shode', (string) $context->health['tgju']->error);
    }

    // ── 🔴 Izole budan ──────────────────────────────────────────────

    /**
     * Asli tarin khasiat e yek site e moghayese: yek manba' e ghat' nabayad
     * kol e tablo ro khamoosh kone.
     */
    public function test_one_failing_provider_never_affects_the_others(): void
    {
        Http::fake([
            'broken.test/*' => Http::response('', 500),
            'healthy.test/*' => Http::response(['ok' => true]),
        ]);

        $context = $this->fetch([
            (new FakeQuoteSource('broken'))->returns([$this->quote(provider: 'broken')])->pooled('https://broken.test/p'),
            (new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://healthy.test/p'),
        ]);

        $this->assertCount(1, $context->raw);
        $this->assertSame('tgju', $context->raw[0]->providerKey);

        // Har do bayad tu report bashan — «nabud» ba «shekast khord» farg
        // dare va panel bayad har do ro bedune.
        $this->assertFalse($context->health['broken']->succeeded);
        $this->assertTrue($context->health['tgju']->succeeded);
    }

    public function test_a_connection_error_on_one_provider_leaves_the_other_intact(): void
    {
        Http::fake([
            'broken.test/*' => static fn (): never => throw new ConnectionException('cURL error 35: TLS'),
            'healthy.test/*' => Http::response(['ok' => true]),
        ]);

        $context = $this->fetch([
            (new FakeQuoteSource('broken'))->returns([$this->quote(provider: 'broken')])->pooled('https://broken.test/p'),
            (new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://healthy.test/p'),
        ]);

        $this->assertCount(1, $context->raw);
        $this->assertTrue($context->health['tgju']->succeeded);
    }

    /**
     * Shekast e MOVAGHAT: `FetchStage` hich halat e provider negah nemidare،
     * pas tick e ba'di yek shoru' e taze ast.
     *
     * In mohem ast chun `cURL error 35` (TLS handshake) ru endpoint haye
     * Irani etefagh mioftad va MOVAGHAT ast — nabayad be ghat'i ye
     * chand-tick-e tabdil beshe. (`P1-25` retry ro TU hamun tick ezafe
     * mikone؛ in test khasiat e bi-halat budan ro mikhkub mikone.)
     */
    public function test_a_provider_that_failed_last_tick_recovers_on_the_next(): void
    {
        Http::fake(['example.test/*' => Http::response(['ok' => true])]);

        $source = (new FakeQuoteSource('tgju'))
            ->returns([$this->quote()])
            ->failsOnce(message: 'cURL error 35: TLS connect error')
            ->pooled('https://example.test/p');

        $first = $this->fetch([$source]);
        $this->assertFalse($first->health['tgju']->succeeded);
        $this->assertStringContainsString('cURL error 35', (string) $first->health['tgju']->error);

        $second = $this->fetch([$source]);
        $this->assertTrue($second->health['tgju']->succeeded);
        $this->assertCount(1, $second->raw);
    }

    // ── Masir e mostaghim (bedun e HTTP) ────────────────────────────

    public function test_a_source_without_a_request_uses_the_direct_path(): void
    {
        // `MockQuoteSource` hich HTTP nadare — `Http::pool` baraye un
        // ma'ni nadare.
        $source = (new FakeQuoteSource('mock'))->returns([$this->quote(provider: 'mock')]);

        $context = $this->fetch([$source]);

        $this->assertCount(1, $context->raw);
        $this->assertSame(1, $source->fetchCount());
        $this->assertSame(0, $source->parseCount());
        Http::assertNothingSent();
    }

    public function test_pooled_and_direct_sources_run_in_the_same_tick(): void
    {
        Http::fake(['example.test/*' => Http::response(['ok' => true])]);

        $context = $this->fetch([
            (new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://example.test/p'),
            (new FakeQuoteSource('mock'))->returns([$this->quote(provider: 'mock')]),
        ]);

        $this->assertCount(2, $context->raw);
        $this->assertCount(2, $context->health);
    }

    public function test_a_failing_direct_source_does_not_throw(): void
    {
        $context = $this->fetch([
            (new FakeQuoteSource('mock'))->fails('clock e system aghab ast'),
        ]);

        $this->assertSame([], $context->raw);
        $this->assertFalse($context->health['mock']->succeeded);
    }

    // ── Latency ─────────────────────────────────────────────────────

    /**
     * ⚠️ `Http::fake` amar e curl nadare، pas inja latency `0` mishe — va
     * hamin DOROST ast: adad e sakhtegi nabayad sakhte beshe.
     *
     * Ghablan `microtime()` dor e `Http::pool` andaze gerefte mishod، ke
     * BI-MA'NI bud: pool ghablan tamum shode bud va faghat parse andaze
     * gerefte mishod — har provider «1ms» gozaresh mishod va monitoring e
     * panel dorugh migoft. Alan az `handlerStats()['total_time']` miad.
     * (Adad haye vaghei tu docblock e `FetchStage` sabt shodan: tgju 2.4–3.6s،
     * estjt 6.2–9.1s.)
     */
    public function test_latency_is_not_fabricated_when_the_transport_reports_nothing(): void
    {
        Http::fake(['example.test/*' => Http::response(['ok' => true])]);

        $context = $this->fetch([
            (new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://example.test/p'),
        ]);

        $this->assertSame(0, $context->health['tgju']->latencyMs);
    }

    public function test_a_failed_fetch_reports_zero_latency_instead_of_crashing(): void
    {
        Http::fake(['example.test/*' => static fn (): never => throw new ConnectionException('timeout')]);

        $context = $this->fetch([
            (new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://example.test/p'),
        ]);

        $this->assertSame(0, $context->health['tgju']->latencyMs);
    }

    // ── Halat e hich ────────────────────────────────────────────────

    public function test_no_sources_is_a_no_op(): void
    {
        $context = $this->fetch([]);

        $this->assertSame([], $context->raw);
        $this->assertSame([], $context->health);
        Http::assertNothingSent();
    }
}
