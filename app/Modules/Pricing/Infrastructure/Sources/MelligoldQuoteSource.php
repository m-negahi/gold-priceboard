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
use Carbon\CarbonImmutable;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * melligold.com / طلای ملی (platform) —
 * `GET https://melligold.com/api/v1/exchange/buy-sell-price/?symbol=XAU18`
 *
 * Pasokh (2026-10-07):
 *   {"message":"Success","data":{"price_buy":26553624,"price_sell":26553624,
 *     "difference_price_buy":648,"difference_price_sell":648,
 *     "lower_amounts":{...},"timestamp":1791352500}}
 *
 * ── Chiz hayi ke tu data ye VAGHEI didim ─────────────────────────────
 * 1. 🔴 Site posht e challenge e **ArvanCloud** ast: pasokh e avval 307 be
 *    HAMUN URL + `Set-Cookie` (`__arcsco*`). Bedun e cookie jar redirect
 *    halghe mishe (curl ba `-L` e khali 50 bar charkhid). `cookies: true`
 *    tu `SourceRequestDto` jar e per-request mide va dar talash e dovom
 *    200 migirim.
 * 2. 🔴 TOMAN e har GRAM e 18 ayar، integer: `26,553,624` (tgju ye hamun
 *    lahze ~26.7M toman bud) → ×10 rial.
 * 3. `price_buy` = gheymati ke KARBAR mikhare = ASK؛ `price_sell` =
 *    foroosh e karbar = BID (hamun gharardad e technogold — esm ha az did
 *    e karbar-and). Dar lahze ye barresi har do BARABAR budan؛ map e
 *    jodagane mimune ta age spread vaghei shod durost namayesh dade she.
 * 4. `timestamp` epoch e SANIYE ast (na millisecond) va ~2.5 sa'at aghab
 *    tar az `now` e hamun lahze bud — mesl e milli shab/ta'til yakh
 *    mizane؛ hamun override e freshness e platform ha tu seeder.
 * 5. Faghat `XAU18` javab mide (XAU24/SEKEH/… «منبع یافت نشد») — platform
 *    e ABSHODE ast (map be `xau-melted-g-irr`).
 */
final readonly class MelligoldQuoteSource implements QuoteSourcePort
{
    public const KEY = 'melligold';

    private const DEFAULT_URL = 'https://melligold.com/api/v1/exchange/buy-sell-price/?symbol=XAU18';

    /**
     * @var array<string, Side>
     */
    private const SIDES = [
        'price_buy' => Side::Ask,
        'price_sell' => Side::Bid,
    ];

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
            cookies: true,
        );
    }

    public function fetch(): SourceResultDto
    {
        try {
            // Jar e taze per-request — challenge e Arvan (docblock e bala).
            $response = Http::timeout($this->timeout)
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->withOptions(['cookies' => new CookieJar])
                ->acceptJson()
                ->get($this->url());
        } catch (Throwable $e) {
            throw new RuntimeException("melligold: request shekast khord — {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("melligold: pasokh e na-movafagh ({$response->status()}).");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('melligold: pasokh JSON nist.');
        }

        $body = $response->body();

        return $this->parse($payload)->withPayloadMeta(md5($body), strlen($body));
    }

    /**
     * @param  array<string, mixed>  $payload  body e kamel (ba `data.price_buy`/`price_sell`)
     */
    public function parse(array $payload): SourceResultDto
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new RuntimeException('melligold: payload shekl e entezari nadare (`data` nist).');
        }

        // 🔴 `XAU18` dar VAGHE' gheymat e moamele ye ABSHODE ast — melligold
        // platform e kharid/foroosh e abshode-ast (tasmim e sahebe proje،
        // 2026-10-07)، na gram e 18 ayar e zinati.
        $instrument = Instrument::GoldMeltedGram;
        $upstreamAt = $this->epochSecondsToUtc($data['timestamp'] ?? null);
        $quotes = [];
        $missing = [];

        foreach (self::SIDES as $field => $side) {
            $minor = $this->toMinor($data[$field] ?? null);

            if ($minor === null || $minor <= 0) {
                $missing[] = "data.{$field}";

                continue;
            }

            $quotes[] = new RawQuoteDto(
                providerKey: self::KEY,
                instrument: $instrument,
                side: $side,
                price: new Price($minor, $instrument->quote()),
                upstreamAt: $upstreamAt,
            );
        }

        return new SourceResultDto(quotes: $quotes, unmappedLabels: $missing);
    }

    /** toman/g (int) → rial/g. */
    private function toMinor(mixed $raw): ?int
    {
        if (! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
            return null;
        }

        $toman = NumberParser::toMinor((string) $raw, [','], null, 0);

        return $toman === null ? null : $toman * UpstreamUnit::Toman->multiplier();
    }

    /** Epoch e SANIYE (na ms — `1791352500` ≈ 2026). Sefr/na-adad → null. */
    private function epochSecondsToUtc(mixed $raw): ?CarbonImmutable
    {
        if (! is_numeric($raw) || (int) $raw <= 0) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) $raw, 'UTC');
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
