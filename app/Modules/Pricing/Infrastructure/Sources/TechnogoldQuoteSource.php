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
 * technogold.gold / تکنوگلد (platform) —
 * `GET https://api2.technogold.gold/customer/tradeables/only-price/{id}`
 *
 * Pasokh (2026-10-06):
 *   {"succeed":true,"message":"…","results":{"sell_price":26423328,"buy_price":26756115}}
 *
 * ── Chiz hayi ke tu data ye VAGHEI didim ─────────────────────────────
 * 1. `{id}` = tradeable e «طلا» = `1` (tanha item e `GET /customer/tradeables`).
 *    Ba option e `tradeable_id` avaz mishe.
 * 2. 🔴 TOMAN e har GRAM، integer: `buy_price 26,756,115` → ×10 rial. (Site:
 *    «قیمت لحظه‌ای خرید هر گرم طلای ۱۸ عیار: 26,755,110 تومان».)
 * 3. 🔴 Jofte bid/ask e VAGHEI — tanha platform e in se ta:
 *      `buy_price`  = gheymati ke KARBAR mikhare  = ASK (balatar)
 *      `sell_price` = gheymati ke karbar miforushe = BID (paeen tar)
 *    Hamun gharardad e tgju (`_buy` = kharid e bazaar az karbar = bid) —
 *    vali inja esm ha az did e karbar-and، pas bar'aks map mishan. Agar
 *    bid > ask dar biad، map eshtebah shode؛ test esh hast.
 *    nerkh `TECHNOGOLD_GOLD_18K` ro az `buy_price` migire.
 * 4. Timestamp tu body nist → `upstreamAt` null، tazegi az `payloadHash`.
 * 5. Hich header e khassi lazem nist؛ `api2.` challenge e Arvan nadare.
 */
final readonly class TechnogoldQuoteSource implements QuoteSourcePort
{
    public const KEY = 'technogold';

    private const DEFAULT_BASE = 'https://api2.technogold.gold';

    private const DEFAULT_TRADEABLE_ID = 1;

    /**
     * @var array<string, Side>
     */
    private const SIDES = [
        'buy_price' => Side::Ask,
        'sell_price' => Side::Bid,
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
            throw new RuntimeException("technogold: request shekast khord — {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("technogold: pasokh e na-movafagh ({$response->status()}).");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('technogold: pasokh JSON nist.');
        }

        $body = $response->body();

        return $this->parse($payload)->withPayloadMeta(md5($body), strlen($body));
    }

    /**
     * @param  array<string, mixed>  $payload  body e kamel (ba `results.buy_price`/`sell_price`)
     */
    public function parse(array $payload): SourceResultDto
    {
        $results = $payload['results'] ?? null;

        if (! is_array($results) || ($payload['succeed'] ?? null) !== true) {
            throw new RuntimeException('technogold: payload shekl e entezari nadare (`succeed`/`results` nist).');
        }

        $instrument = Instrument::Gold18Gram;
        $quotes = [];
        $missing = [];

        foreach (self::SIDES as $field => $side) {
            $minor = $this->toMinor($results[$field] ?? null);

            if ($minor === null || $minor <= 0) {
                $missing[] = "results.{$field}";

                continue;
            }

            $quotes[] = new RawQuoteDto(
                providerKey: self::KEY,
                instrument: $instrument,
                side: $side,
                price: new Price($minor, $instrument->quote()),
                // technogold timestamp nemide — tazegi az `payloadHash` miad.
                upstreamAt: null,
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

    private function url(): string
    {
        if (isset($this->options['url'])) {
            return (string) $this->options['url'];
        }

        $base = rtrim((string) ($this->options['base'] ?? self::DEFAULT_BASE), '/');
        $id = (int) ($this->options['tradeable_id'] ?? self::DEFAULT_TRADEABLE_ID);

        return "{$base}/customer/tradeables/only-price/{$id}";
    }

    private function userAgent(): string
    {
        return (string) ($this->options['user_agent']
            ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36');
    }
}
