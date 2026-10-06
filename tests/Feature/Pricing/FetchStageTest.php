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
use Tests\Support\Fakes\InMemoryUpstreamCallLog;
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
    private function fetch(array $sources, ?FetchStage $stage = null): TickContext
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

        // `retryDelayMs: 0` — vagarna har test e retry 300ms be suite ezafe
        // mikone. Khod e delay jodagane test mishe.
        ($stage ?? new FetchStage(maxAttempts: 2, retryDelayMs: 0))->handle($context);

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

    // ── 🔴 Retry e shekast haye GOZARA (P1-25) ──────────────────────

    /**
     * 🔴 Yek `cURL 35` e tanha bayad TU HAMUN tick jobran beshe.
     *
     * Andaze-giri shode: `tgju` gah-gah ba `cURL error 35: TLS connect
     * error` miofte. Un khata handshake ast va tu ~100-500ms miofte، pas
     * talash e dobare amalan majani-e. Bedun e retry، un provider baraye un
     * tick kamelan gom mishe — ya'ni yek surakh e 15 saniye-i tu series ash.
     */
    public function test_a_transient_tls_failure_is_absorbed_within_the_same_tick(): void
    {
        Http::fake(['example.test/*' => Http::response(['ok' => true])]);

        $source = (new FakeQuoteSource('tgju'))
            ->returns([$this->quote()])
            ->failsOnce(message: 'cURL error 35: SSL connect error')
            ->pooled('https://example.test/p');

        $context = $this->fetch([$source]);

        $this->assertTrue($context->health['tgju']->succeeded);
        $this->assertCount(1, $context->raw);

        // 🔴 `attempts` bayad sabt beshe: provider-i ke HAR tick retry
        // mikhahe dar hale kharab shodan ast، va «salem» e sade un
        // degradation ro penhan mikone.
        $this->assertSame(2, $context->health['tgju']->attempts);
        $this->assertTrue($context->health['tgju']->toArray()['retried']);
    }

    public function test_a_single_attempt_configuration_disables_retry(): void
    {
        Http::fake(['example.test/*' => Http::response(['ok' => true])]);

        $context = $this->fetch(
            [(new FakeQuoteSource('tgju'))
                ->returns([$this->quote()])
                ->failsOnce(message: 'cURL error 35: SSL connect error')
                ->pooled('https://example.test/p')],
            new FetchStage(maxAttempts: 1),
        );

        $this->assertFalse($context->health['tgju']->succeeded);
        $this->assertSame(1, $context->health['tgju']->attempts);
    }

    /**
     * 🔴 Timeout (`cURL 28`) retry NEMISHE — mohem tarin tasmim e in task.
     *
     * Timeout ya'ni ma budget e KAMEL e un provider ro ghablan kharj kardim
     * (`estjt` 20 saniye). Retry kardanesh un ro DO barabar mikone، va chun
     * `Http::pool` montazer e HAME mimune، baraye jobran e yek surakh tu YEK
     * series yek surakh tu HAME ye series misazim.
     */
    public function test_a_timeout_is_never_retried(): void
    {
        $calls = 0;
        Http::fake(['example.test/*' => function () use (&$calls): never {
            $calls++;
            throw new ConnectionException('cURL error 28: Operation timed out');
        }]);

        $context = $this->fetch([
            (new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://example.test/p'),
        ]);

        $this->assertSame(1, $calls, 'timeout nabayad takrar beshe.');
        $this->assertFalse($context->health['tgju']->succeeded);
        $this->assertSame(1, $context->health['tgju']->attempts);
    }

    /**
     * Khata ye parse / markup e avaz-shode retry NEMISHE: talash e dobare
     * DAGHIGHAN hamun javab ro mide. In bayad DIDE she، na takrar.
     */
    public function test_a_parse_failure_is_never_retried(): void
    {
        Http::fake(['example.test/*' => Http::response(['current' => []])]);

        $source = (new FakeQuoteSource('tgju'))
            ->fails('markup avaz shode')
            ->pooled('https://example.test/p');

        $context = $this->fetch([$source]);

        $this->assertSame(1, $source->parseCount(), 'parse nabayad do bar seda beshe.');
        $this->assertSame(1, $context->health['tgju']->attempts);
    }

    public function test_an_http_error_status_is_never_retried(): void
    {
        $calls = 0;
        Http::fake(['example.test/*' => function () use (&$calls) {
            $calls++;

            // 500 gozara-st vali 300ms ba'd hamun javab ro mide، va hazine
            // ash yek round-trip e kamel ast (6-9s baraye estjt).
            return Http::response('', 500);
        }]);

        $context = $this->fetch([
            (new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://example.test/p'),
        ]);

        $this->assertSame(1, $calls);
        $this->assertSame(1, $context->health['tgju']->attempts);
    }

    /**
     * Retry ham HAMZAMAN ast: do provider e shekast-khorde tu YEK pool e
     * dovom takrar mishan، na poshte sar e ham. Vagarna retry khodesh
     * tabdil mishe be hamun fetch e seri ke `Http::pool` baraye hazf esh
     * sakhte shode.
     */
    public function test_several_transient_failures_are_retried_together(): void
    {
        Http::fake([
            'a.test/*' => Http::response(['ok' => true]),
            'b.test/*' => Http::response(['ok' => true]),
        ]);

        $context = $this->fetch([
            (new FakeQuoteSource('tgju'))->returns([$this->quote()])
                ->failsOnce(message: 'cURL error 35: SSL connect error')->pooled('https://a.test/p'),
            (new FakeQuoteSource('estjt'))->returns([$this->quote(provider: 'estjt')])
                ->failsOnce(message: 'cURL error 56: recv failure')->pooled('https://b.test/p'),
        ]);

        $this->assertCount(2, $context->raw);
        $this->assertSame(2, $context->health['tgju']->attempts);
        $this->assertSame(2, $context->health['estjt']->attempts);
    }

    public function test_only_the_failing_provider_is_retried(): void
    {
        $healthyCalls = 0;
        Http::fake([
            'broken.test/*' => Http::response(['ok' => true]),
            'healthy.test/*' => function () use (&$healthyCalls) {
                $healthyCalls++;

                return Http::response(['ok' => true]);
            },
        ]);

        $context = $this->fetch([
            (new FakeQuoteSource('broken'))->returns([$this->quote(provider: 'broken')])
                ->failsOnce(message: 'cURL error 7: Failed to connect')->pooled('https://broken.test/p'),
            (new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://healthy.test/p'),
        ]);

        // Provider e salem nabayad dobare seda beshe — un yek round-trip e
        // bi-dalil e ezafi-e.
        $this->assertSame(1, $healthyCalls);
        $this->assertSame(1, $context->health['tgju']->attempts);
        $this->assertSame(2, $context->health['broken']->attempts);
        $this->assertCount(2, $context->raw);
    }

    public function test_a_still_failing_provider_reports_the_last_error(): void
    {
        Http::fake(['example.test/*' => static fn (): never => throw new ConnectionException('cURL error 35: SSL connect error')]);

        $context = $this->fetch([
            (new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://example.test/p'),
        ]);

        $this->assertFalse($context->health['tgju']->succeeded);
        $this->assertSame(2, $context->health['tgju']->attempts, 'bayad do bar talash shode bashe.');
        $this->assertStringContainsString('cURL error 35', (string) $context->health['tgju']->error);
    }

    /**
     * 🔴 Retry nabayad tick ro az grid rad kone.
     *
     * Ba budget e kuchik، hich retry-i nabayad ettefagh bioftad: baraye
     * jobran e yek surakh tu YEK series، yek surakh tu HAME ye series
     * sakhtan ghalat ast (`Http::pool` montazer e hame mimune va halghe ye
     * worker slot e ba'di ro ja mindaze).
     */
    public function test_retry_is_skipped_when_the_tick_budget_is_exhausted(): void
    {
        $calls = 0;
        Http::fake(['example.test/*' => function () use (&$calls): never {
            $calls++;
            throw new ConnectionException('cURL error 35: SSL connect error');
        }]);

        $context = $this->fetch(
            [(new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://example.test/p')],
            // Budget e 500ms: ja-i baraye talash e dovom nist.
            new FetchStage(maxAttempts: 2, retryDelayMs: 0, budgetMs: 500),
        );

        $this->assertSame(1, $calls, 'ba budget e tamum-shode nabayad retry beshe.');
        $this->assertFalse($context->health['tgju']->succeeded);
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

    // ── 🔴 Daftarche ye upstream (P4-14) ────────────────────────────

    /** Stage ba daftarche — baraye test haye in bakhsh. */
    private function loggedStage(InMemoryUpstreamCallLog $log, int $maxAttempts = 2): FetchStage
    {
        return new FetchStage(maxAttempts: $maxAttempts, retryDelayMs: 0, upstreamLog: $log);
    }

    public function test_a_successful_pooled_call_is_logged(): void
    {
        Http::fake(['example.test/*' => Http::response(['ok' => true])]);
        $log = new InMemoryUpstreamCallLog;

        $this->fetch(
            [(new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://example.test/prices')],
            $this->loggedStage($log),
        );

        $this->assertCount(1, $log->calls);
        $call = $log->calls[0];
        $this->assertSame('tgju', $call->providerKey);
        $this->assertTrue($call->succeeded);
        $this->assertSame(200, $call->status);
        $this->assertSame('GET', $call->method);
        $this->assertSame('https://example.test/prices', $call->url);
        $this->assertSame(1, $call->attempt);
        $this->assertSame(1, $call->symbolCount);
        $this->assertNull($call->error);
        $this->assertSame(strlen((string) json_encode(['ok' => true])), $call->bytes);
    }

    public function test_a_failed_pooled_call_is_logged_with_status_and_error(): void
    {
        Http::fake(['example.test/*' => Http::response('nope', 503)]);
        $log = new InMemoryUpstreamCallLog;

        $this->fetch(
            [(new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://example.test/prices')],
            $this->loggedStage($log),
        );

        $this->assertCount(1, $log->calls);
        $this->assertFalse($log->calls[0]->succeeded);
        $this->assertSame(503, $log->calls[0]->status);
        $this->assertStringContainsString('503', (string) $log->calls[0]->error);
    }

    public function test_a_connection_error_is_logged_without_a_status(): void
    {
        // Hich pasokh e HTTP nayumade — status e sakhtegi nabayad sakhte she.
        Http::fake(['example.test/*' => static fn (): never => throw new ConnectionException('cURL error 28: timeout')]);
        $log = new InMemoryUpstreamCallLog;

        $this->fetch(
            [(new FakeQuoteSource('tgju'))->returns([$this->quote()])->pooled('https://example.test/p')],
            $this->loggedStage($log),
        );

        $this->assertCount(1, $log->calls);
        $this->assertNull($log->calls[0]->status);
        $this->assertStringContainsString('timeout', (string) $log->calls[0]->error);
    }

    public function test_a_parse_failure_is_logged_with_its_http_status(): void
    {
        // In daghighan halati-e ke daftarche barash sakhte shode: provider
        // 200 mide vali markup avaz shode — status 200 + succeeded=false.
        Http::fake(['example.test/*' => Http::response(['current' => []])]);
        $log = new InMemoryUpstreamCallLog;

        $this->fetch(
            [(new FakeQuoteSource('tgju'))->fails('markup avaz shode')->pooled('https://example.test/p')],
            $this->loggedStage($log),
        );

        $this->assertCount(1, $log->calls);
        $this->assertFalse($log->calls[0]->succeeded);
        $this->assertSame(200, $log->calls[0]->status);
        $this->assertSame(0, $log->calls[0]->symbolCount);
        $this->assertStringContainsString('markup avaz shode', (string) $log->calls[0]->error);
    }

    public function test_every_retry_attempt_gets_its_own_entry(): void
    {
        // Health faghat NATIJE ye akhar ro dare؛ daftarche bayad har talash
        // ro joda neshun bede — vagarna «har tick retry mikhore» dide nemishe.
        Http::fake(['example.test/*' => Http::response(['ok' => true])]);
        $log = new InMemoryUpstreamCallLog;

        $this->fetch(
            [(new FakeQuoteSource('tgju'))
                ->returns([$this->quote()])
                ->failsOnce(message: 'cURL error 35: SSL connect error')
                ->pooled('https://example.test/p')],
            $this->loggedStage($log),
        );

        $this->assertCount(2, $log->calls);
        // Jadid tarin aval: [0] talash e dovom (movafagh)، [1] talash e aval.
        $this->assertSame(2, $log->calls[0]->attempt);
        $this->assertTrue($log->calls[0]->succeeded);
        $this->assertSame(1, $log->calls[1]->attempt);
        $this->assertFalse($log->calls[1]->succeeded);
    }

    public function test_direct_sources_are_not_logged(): void
    {
        // Mock/manual HTTP nadaran — tu daftarche ye «call be provider»
        // ja-i nadaran.
        $log = new InMemoryUpstreamCallLog;

        $this->fetch(
            [(new FakeQuoteSource('mock'))->returns([$this->quote(provider: 'mock')])],
            $this->loggedStage($log),
        );

        $this->assertSame([], $log->calls);
    }
}
