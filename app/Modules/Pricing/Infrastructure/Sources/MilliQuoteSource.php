<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Sources;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Application\Dto\SourceRequestDto;
use App\Modules\Pricing\Application\Dto\SourceResultDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Ports\QuoteSourcePort;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Infrastructure\Support\NumberParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * milli.gold (platform) — `GET https://milli.gold/api/v1/public/milli-price/external`
 *
 * Pasokh (2026-10-06):
 *   {"code":0,"message":"…","data":{"price18":265100,"date":"2026-10-06T20:40:00"}}
 *
 * ── Chiz hayi ke tu data ye VAGHEI didim ─────────────────────────────
 * 1. 🔴 `price18` RIAL e har **MILLIGRAM** ast، na gram: `265100` → ×1000 =
 *    265,100,000 rial/g (site khodesh «265,100,000 ریال / ۱ گرم» neshun
 *    mide va ba nerkh `MILLI_GOLD18K = 26,510,000` toman yeki-e).
 * 2. `date` ISO bedun e offset va **vaght e Tehran** ast (UTC 17:10 ↔
 *    `20:40`) — mesle tgju bayad ba timezone e Tehran khunde she. Gheymat
 *    har daghighe gerd mishe (`:40:00`).
 * 3. Yek gheymat: site sarih mige «بهای خرید و فروش طلا در میلی یکسان است»
 *    → `Side::Last` ba `kind = platform` (kharid/foroosh vaghei va barabar).
 * 4. Hich header e khassi lazem nist (curl e khali 200 dad).
 */
final readonly class MilliQuoteSource implements QuoteSourcePort
{
    public const KEY = 'milli';

    private const DEFAULT_URL = 'https://milli.gold/api/v1/public/milli-price/external';

    private const TIMEZONE = 'Asia/Tehran';

    /**
     * `price18` be MILLIGRAM ast؛ ba scale 3 parse mishe ta ×1000 bedun e
     * float anjam she (ashar e ehtemali ham hefz mishe).
     */
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
            throw new RuntimeException("milli: request shekast khord — {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("milli: pasokh e na-movafagh ({$response->status()}).");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('milli: pasokh JSON nist.');
        }

        $body = $response->body();

        return $this->parse($payload)->withPayloadMeta(md5($body), strlen($body));
    }

    /**
     * @param  array<string, mixed>  $payload  body e kamel (ba `data.price18`)
     */
    public function parse(array $payload): SourceResultDto
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data) || ! array_key_exists('price18', $data)) {
            // Shekl e payload avaz shode — shekast ast، na «bazaar baste».
            throw new RuntimeException('milli: payload shekl e entezari nadare (`data.price18` nist).');
        }

        $instrument = Instrument::Gold18Gram;
        $minor = $this->toMinorPerGram($data['price18']);

        if ($minor === null || $minor <= 0) {
            return new SourceResultDto(quotes: [], unmappedLabels: ['data.price18']);
        }

        return new SourceResultDto(quotes: [
            new RawQuoteDto(
                providerKey: self::KEY,
                instrument: $instrument,
                side: Side::Last,
                price: new Price($minor, $instrument->quote()),
                upstreamAt: $this->toUtc($data['date'] ?? null),
            ),
        ]);
    }

    /** rial/mg → rial/g (minor e IRR، scale 0) bedun e float. */
    private function toMinorPerGram(mixed $raw): ?int
    {
        if (! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
            return null;
        }

        // scale 3 = ×1000 = milligram → gram. Rial khodesh scale 0 dare.
        return NumberParser::toMinor((string) $raw, [','], '.', self::MILLIGRAM_SCALE);
    }

    /** `"2026-10-06T20:40:00"` (Tehran، bedun e offset) → UTC. */
    private function toUtc(mixed $raw): ?CarbonImmutable
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw, self::TIMEZONE)->utc();
        } catch (Throwable) {
            return null;
        }
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
