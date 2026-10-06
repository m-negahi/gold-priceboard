<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Sources;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Application\Dto\SourceRequestDto;
use App\Modules\Pricing\Application\Dto\SourceResultDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Enums\UpstreamUnit;
use App\Modules\Pricing\Domain\Ports\QuoteSourcePort;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Infrastructure\Support\NumberParser;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * talasea.ir / طلاسی (platform) — `GET https://api.talasea.ir/api/market/getGoldPrice`
 *
 * Pasokh (2026-10-06، faghat field haye marbut):
 *   {"price":"26689","change24h":"0.62","fee":0.01,"disableBuy":false,"disableSell":false,…}
 *
 * ── Chiz hayi ke tu data ye VAGHEI didim ─────────────────────────────
 * 1. 🔴 `price` TOMAN e har **MILLIGRAM** ast va STRING: `"26689"` → ×1000
 *    = 26,689,000 toman/g → ×10 = 266,890,000 rial/g (= nerkh
 *    `TALASEA_GOLD_18K`). Do zarib e joda: milligram→gram tu scale e
 *    parser، toman→rial tu `UpstreamUnit`.
 * 2. Hich timestamp-i tu body nist (`Cache-Control: no-store`) →
 *    `upstreamAt` null va tazegi az `payloadHash` miad (mesle estjt).
 *    History e timestamp-dar faghat ru `talasea.ir` (na `api.`) ast ke
 *    cookie-challenge e ArvanCloud dare — amdan estefade nashod.
 * 3. Yek gheymat (karmozd `fee` joda-st) → `Side::Last` ba `kind = platform`.
 * 4. Hich header e khassi lazem nist؛ `api.` subdomain challenge nadare.
 */
final readonly class TalaseaQuoteSource implements QuoteSourcePort
{
    public const KEY = 'talasea';

    private const DEFAULT_URL = 'https://api.talasea.ir/api/market/getGoldPrice';

    /** `price` be MILLIGRAM ast؛ scale 3 = ×1000 bedun e float. */
    private const MILLIGRAM_SCALE = 3;

    /** @param array<string, mixed> $options */
    public function __construct(
        private array $options = [],
        private int $timeout = 10,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function request(): SourceRequestDto
    {
        return new SourceRequestDto(
            method: 'GET',
            url: $this->url(),
            headers: ['User-Agent' => $this->userAgent(), 'Accept' => 'application/json'],
            timeout: $this->timeout,
        );
    }

    public function fetch(): SourceResultDto
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->acceptJson()
                ->get($this->url());
        } catch (Throwable $e) {
            throw new RuntimeException("talasea: request shekast khord — {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("talasea: pasokh e na-movafagh ({$response->status()}).");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('talasea: pasokh JSON nist.');
        }

        $body = $response->body();

        return $this->parse($payload)->withPayloadMeta(md5($body), strlen($body));
    }

    /**
     * @param  array<string, mixed>  $payload  body e kamel (ba `price`)
     */
    public function parse(array $payload): SourceResultDto
    {
        if (! array_key_exists('price', $payload)) {
            throw new RuntimeException('talasea: payload shekl e entezari nadare (`price` nist).');
        }

        $instrument = Instrument::Gold18Gram;
        $minor = $this->toMinorPerGram($payload['price']);

        if ($minor === null || $minor <= 0) {
            return new SourceResultDto(quotes: [], unmappedLabels: ['price']);
        }

        return new SourceResultDto(quotes: [
            new RawQuoteDto(
                providerKey: self::KEY,
                instrument: $instrument,
                side: Side::Last,
                price: new Price($minor, $instrument->quote()),
                // talasea timestamp nemide — tazegi az `payloadHash` miad.
                upstreamAt: null,
                upstreamChangePercent: is_numeric($payload['change24h'] ?? null) ? (float) $payload['change24h'] : null,
            ),
        ]);
    }

    /** toman/mg (string) → rial/g bedun e float. */
    private function toMinorPerGram(mixed $raw): ?int
    {
        if (! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
            return null;
        }

        // scale 3 = ×1000 = milligram → gram (ashar e ehtemali ye `"26689.5"`
        // ham hefz mishe)؛ ba'd toman → rial.
        $tomanPerGram = NumberParser::toMinor((string) $raw, [','], '.', self::MILLIGRAM_SCALE);

        return $tomanPerGram === null ? null : $tomanPerGram * UpstreamUnit::Toman->multiplier();
    }

    private function url(): string
    {
        return (string) ($this->options['url'] ?? self::DEFAULT_URL);
    }

    private function userAgent(): string
    {
        return (string) ($this->options['user_agent']
            ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36');
    }
}
