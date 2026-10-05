<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Application\Dto\SourceRequestDto;
use App\Modules\Pricing\Application\Dto\SourceResultDto;
use App\Modules\Pricing\Domain\Ports\QuoteSourcePort;
use RuntimeException;

/**
 * Source e ghabl e barname-rizi — mitune gheymat bede ya amdan beterke.
 *
 * ── Do masir e `FetchStage` ─────────────────────────────────────────
 * `request()` e `null` -> masir e MOSTAGHIM (`fetch()`)، baraye source haye
 * bedun e HTTP mesl e mock.
 * `request()` e gheyr e null -> masir e POOL (`Http::pool` + `parse()`).
 *
 * 🔴 Default `null` ast، pas test e pipeline az pool rad nemishe (facade
 * nemikhahe). Vali un ya'ni masir e pool — jayi ke hame ye halat haye
 * shekast e shabake hastan — bedun e `pooled()` HICHVAGHT test nemishe.
 */
final class FakeQuoteSource implements QuoteSourcePort
{
    private int $fetchCount = 0;

    private int $parseCount = 0;

    private ?SourceRequestDto $request = null;

    /** @var array<int, string> */
    private array $unmapped = [];

    /** @var array<string, mixed>|null */
    private ?array $lastPayload = null;

    /** Shomaresh e shekast haye baghi-munde — `0` = dige nemiofte. */
    private int $failuresLeft = 0;

    /**
     * Payam e shekast e MOVAGHAT — joda az `$throwMessage` e daemi.
     *
     * 🔴 Avval hamun `$throwMessage` ro estefade kardam va `failsOnce()`
     * amalan DAEMI shod: ba'd az tamum shodan e `failuresLeft`، shart e
     * ba'di hanuz `$throwMessage` ro set mid va miterkid. Do mafhum e joda
     * («movaghat» va «hamishe») nabayad ru yek field bemunan.
     */
    private ?string $transientMessage = null;

    /** @param array<int, RawQuoteDto> $quotes */
    public function __construct(
        private readonly string $key = 'fake',
        private array $quotes = [],
        private ?string $throwMessage = null,
        private readonly ?string $payloadHash = null,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function request(): ?SourceRequestDto
    {
        return $this->request;
    }

    /** @param array<string, mixed> $payload */
    public function parse(array $payload): SourceResultDto
    {
        $this->parseCount++;
        $this->lastPayload = $payload;

        return $this->result();
    }

    public function fetch(): SourceResultDto
    {
        $this->fetchCount++;

        return $this->result();
    }

    private function result(): SourceResultDto
    {
        // Shekast e MOVAGHAT: `failsOnce()` yek bar miterke va ba'd salem
        // mishe — hamun chizi ke yek `cURL 35` e TLS ast، na yek ghat'i ye
        // vaghei.
        if ($this->failuresLeft > 0) {
            $this->failuresLeft--;

            throw new RuntimeException($this->transientMessage ?? 'cURL error 35: TLS connect error');
        }

        if ($this->throwMessage !== null) {
            throw new RuntimeException($this->throwMessage);
        }

        return new SourceResultDto(
            $this->quotes,
            $this->payloadHash,
            count($this->quotes),
            $this->unmapped,
        );
    }

    /** @param array<int, RawQuoteDto> $quotes */
    public function returns(array $quotes): self
    {
        $this->quotes = $quotes;
        $this->throwMessage = null;

        return $this;
    }

    public function fails(string $message = 'upstream javab nadad'): self
    {
        $this->throwMessage = $message;

        return $this;
    }

    /** Bordan e in source ru masir e `Http::pool`. */
    public function pooled(string $url = 'https://example.test/prices', string $method = 'GET'): self
    {
        $this->request = new SourceRequestDto(method: $method, url: $url, timeout: 5);

        return $this;
    }

    /** @param array<string, scalar> $body */
    public function posting(string $url, array $body): self
    {
        $this->request = new SourceRequestDto(method: 'POST', url: $url, body: $body, timeout: 5);

        return $this;
    }

    /**
     * Label hayi ke Source nashenakht.
     *
     * Bayad be `TickReportDto` berese: yek label e jadid tu upstream ya'ni
     * yek symbol ke SAKET gom mishe — tanha rah e didanesh hamin ast.
     *
     * @param  array<int, string>  $labels
     */
    public function unmapped(array $labels): self
    {
        $this->unmapped = $labels;

        return $this;
    }

    /** Yek (ya chand) shekast e movaghat، ba'd salem. */
    public function failsOnce(int $times = 1, ?string $message = null): self
    {
        $this->failuresLeft = $times;
        $this->transientMessage = $message;

        return $this;
    }

    public function fetchCount(): int
    {
        return $this->fetchCount;
    }

    public function parseCount(): int
    {
        return $this->parseCount;
    }

    /** @return array<string, mixed>|null */
    public function lastPayload(): ?array
    {
        return $this->lastPayload;
    }
}
