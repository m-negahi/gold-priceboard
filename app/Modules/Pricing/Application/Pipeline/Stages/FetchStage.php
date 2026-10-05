<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Pipeline\Stages;

use App\Modules\Pricing\Application\Dto\ProviderHealthDto;
use App\Modules\Pricing\Application\Dto\SourceRequestDto;
use App\Modules\Pricing\Application\Dto\SourceResultDto;
use App\Modules\Pricing\Application\Pipeline\Stage;
use App\Modules\Pricing\Application\Pipeline\TickContext;
use App\Modules\Pricing\Domain\Enums\StagePolicy;
use App\Modules\Pricing\Domain\Ports\QuoteSourcePort;
use App\Modules\Pricing\Domain\Services\TransientFailure;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gereftan e gheymat az hame ye provider ha — HAMZAMAN.
 *
 * ── 🔴 Chera hamzaman (andaze-giri ye vaghei، na hads) ──────────────
 * `scripts/fetch-benchmark.php` ru endpoint haye zende، 3 ejra:
 *
 *   poshte-sar-e-ham : 12.1s / 12.8s / 13.1s
 *   hamzaman         :  6.3s /  8.4s /  9.1s
 *   (tgju 2.4–3.6s، estjt 6.2–9.1s)
 *
 * Budget e tick 15s ast. Poshte-sar-e-ham ba HAMIN DO provider ham ~13s
 * migire — ya'ni ba provider e SEVOM az budget rad mikone va halghe ye
 * worker aghab mimune. Hamzaman، kond tarin provider tool e tick ro
 * moshakhas mikone، na jam' e hamashun.
 *
 * ── Izole budan ─────────────────────────────────────────────────────
 * Har provider try/catch e KHODESH ro dare. Shekast e yeki hargez be baghi
 * nemirese — in asli tarin khasiat e yek site e moghayese ast: yek manba' e
 * ghat' nabayad kol e tablo ro khamoosh kone.
 *
 * ── Retry (P1-25) ───────────────────────────────────────────────────
 * Faghat shekast haye GOZARA ye sath e ettesal (`TransientFailure`) va
 * faghat HAMZAMAN: hame ye provider haye shekast-khorde tu YEK pool e dovom
 * takrar mishan، na poshte sar e ham.
 *
 * 🔴 Timeout e talash e dovom be budget e BAGHI-MUNDE clamp mishe. Bedun e
 * un، retry mitune tick ro az grid rad kone — ya'ni baraye jobran e yek
 * surakh tu YEK series، yek surakh tu HAME ye series misazim (chun
 * `Http::pool` montazer e hame mimune va halghe ye worker slot e ba'di ro ja
 * mindaze).
 */
final readonly class FetchStage implements Stage
{
    /** Kamtar az in vaght e baghi-munde، retry arzesh nadare. */
    private const MIN_RETRY_BUDGET_MS = 1_500;

    public function __construct(
        /** `1` = bedun e retry. */
        private int $maxAttempts = 2,
        private int $retryDelayMs = 300,
        /** Budget e kol e fetch — ma'mulan hamun grid e tick. */
        private int $budgetMs = 15_000,
    ) {}

    public function name(): string
    {
        return 'fetch';
    }

    public function policy(): StagePolicy
    {
        // Shekast e yek provider nabayad tick ro bokoshe. Khod e Stage
        // hargez throw nemikone — har provider dakhel e try/catch ast.
        return StagePolicy::Isolated;
    }

    public function handle(TickContext $context): void
    {
        $startedAt = microtime(true);

        /** @var array<string, QuoteSourcePort> $pooled */
        $pooled = [];
        /** @var array<string, QuoteSourcePort> $direct */
        $direct = [];

        foreach ($context->sources as $source) {
            if ($source->request() === null) {
                // Source e bedun e HTTP (mock) — pool ma'ni nadare.
                $direct[$source->key()] = $source;

                continue;
            }

            $pooled[$source->key()] = $source;
        }

        $pending = $pooled;
        $attempt = 1;
        $timeoutCap = null;

        while ($pending !== []) {
            $responses = $this->sendConcurrently($pending, $timeoutCap);

            /** @var array<string, QuoteSourcePort> $retryable */
            $retryable = [];

            foreach ($pending as $key => $source) {
                if (! $this->consume($context, $source, $responses[$key] ?? null, $attempt)) {
                    $retryable[$key] = $source;
                }
            }

            if ($retryable === [] || $attempt >= $this->maxAttempts) {
                break;
            }

            // Budget: chand ms baghi munde ba'd az in ke sabr konim?
            $remaining = $this->budgetMs - $this->elapsed($startedAt) - $this->retryDelayMs;

            if ($remaining < self::MIN_RETRY_BUDGET_MS) {
                // Vaght nist. Shekast e sabt-shode sar e ja ash mimune.
                break;
            }

            if ($this->retryDelayMs > 0) {
                usleep($this->retryDelayMs * 1_000);
            }

            // 🔴 Timeout e talash e dovom be vaght e baghi-munde clamp mishe،
            // pas retry zaten NEMITUNE az budget rad kone.
            $timeoutCap = max(1, intdiv($remaining, 1_000));
            $attempt++;
            $pending = $retryable;
        }

        foreach ($direct as $source) {
            $this->consumeDirect($context, $source);
        }
    }

    /**
     * @param  array<string, QuoteSourcePort>  $sources
     * @return array<string, mixed>
     */
    private function sendConcurrently(array $sources, ?int $timeoutCap = null): array
    {
        if ($sources === []) {
            return [];
        }

        return Http::pool(function (Pool $pool) use ($sources, $timeoutCap): array {
            $requests = [];

            foreach ($sources as $key => $source) {
                /** @var SourceRequestDto $request */
                $request = $source->request();

                $timeout = $timeoutCap === null
                    ? $request->timeout
                    : min($request->timeout, $timeoutCap);

                $pending = $pool->as($key)
                    ->timeout($timeout)
                    ->withHeaders($request->headers);

                if ($request->httpVersion !== null) {
                    // estjt bedun e in timeout mikhore.
                    $pending = $pending->withOptions(['version' => $request->httpVersion]);
                }

                $requests[] = $request->isPost()
                    ? $pending->asForm()->post($request->url, $request->body)
                    : $pending->get($request->url);
            }

            return $requests;
        });
    }

    /**
     * @return bool `false` ya'ni shekast e GOZARA bud — arzesh e retry dare.
     */
    private function consume(
        TickContext $context,
        QuoteSourcePort $source,
        mixed $response,
        int $attempt = 1,
    ): bool {
        // ⚠️ `microtime()` inja BI-MA'NI-st: `Http::pool` ghablan tamum shode
        // va ma faghat darim javab ro parse mikonim. Latency ro bayad az
        // khod e transfer gereft (`total_time` e curl)، vagarna har provider
        // «1ms» gozaresh mishe va monitoring e panel dorugh mige.
        $latencyMs = $this->transferMs($response);

        try {
            if ($response instanceof Throwable) {
                throw $response;
            }

            if ($response === null) {
                throw new \RuntimeException('hich pasokhi az pool nayumad.');
            }

            if (! $response->successful()) {
                throw new \RuntimeException("pasokh e na-movafagh ({$response->status()}).");
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                throw new \RuntimeException('pasokh JSON nist.');
            }

            $body = $response->body();

            $this->accept(
                $context,
                $source,
                $source->parse($payload)->withPayloadMeta(md5($body), strlen($body)),
                $latencyMs,
                $attempt,
            );

            return true;
        } catch (Throwable $e) {
            $this->fail($context, $source, $e, $latencyMs, $attempt);

            return ! TransientFailure::isRetryable($e);
        }
    }

    /** Source e bedun e HTTP — mostaghim `fetch()`. */
    private function consumeDirect(TickContext $context, QuoteSourcePort $source): void
    {
        $startedAt = microtime(true);

        try {
            $result = $source->fetch();
            $this->accept($context, $source, $result, $this->elapsed($startedAt));
        } catch (Throwable $e) {
            $this->fail($context, $source, $e, $this->elapsed($startedAt));
        }
    }

    private function accept(
        TickContext $context,
        QuoteSourcePort $source,
        SourceResultDto $result,
        int $latencyMs,
        int $attempt = 1,
    ): void {
        foreach ($result->quotes as $quote) {
            $context->raw[] = $quote;
        }

        if ($result->unmappedLabels !== []) {
            $context->unmapped[$source->key()] = $result->unmappedLabels;
        }

        $context->health[$source->key()] = new ProviderHealthDto(
            providerKey: $source->key(),
            succeeded: true,
            attemptedAt: CarbonImmutable::createFromTimestampUTC($context->at->timestamp()),
            latencyMs: $latencyMs,
            symbolCount: $result->count(),
            payloadHash: $result->payloadHash,
            attempts: $attempt,
        );
    }

    private function fail(
        TickContext $context,
        QuoteSourcePort $source,
        Throwable $e,
        int $latencyMs,
        int $attempt = 1,
    ): void {
        $context->health[$source->key()] = new ProviderHealthDto(
            providerKey: $source->key(),
            succeeded: false,
            attemptedAt: CarbonImmutable::createFromTimestampUTC($context->at->timestamp()),
            latencyMs: $latencyMs,
            error: mb_substr($e->getMessage(), 0, 480),
            attempts: $attempt,
        );
    }

    /**
     * Tool e vaghei ye transfer az amar e curl.
     *
     * `Http::pool` zaman e har request ro jodagane bar nemigardune، pas
     * `total_time` e khod e handler tanha manba' e daghigh ast.
     */
    private function transferMs(mixed $response): int
    {
        if ($response === null || $response instanceof Throwable) {
            return 0;
        }

        try {
            $stats = $response->handlerStats();
        } catch (Throwable) {
            return 0;
        }

        return (int) round(((float) ($stats['total_time'] ?? 0)) * 1000);
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
