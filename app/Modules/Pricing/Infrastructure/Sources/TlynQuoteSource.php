<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Sources;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Application\Dto\SourceRequestDto;
use App\Modules\Pricing\Application\Dto\SourceResultDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Enums\UpstreamUnit;
use App\Modules\Pricing\Domain\Ports\QuoteSourcePort;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Infrastructure\Support\NumberParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * tlyn.ir / طلایین (platform) — `GET https://price.tlyn.ir/api/v1/price`
 *
 * Shekl e payload (2026-10-06):
 *   {"prices":[{"symbol":"GOLD18","title":"گرم طلا عیار ۱۸","status":"enabled",
 *               "price":{"sell":26891,"buy":26677,"highest":26917,"lowest":26582},
 *               "date":{"utc_timestamp":"2026-10-06T18:48:33.162009Z", "jdatetime":…}}, …]}
 *
 * ── Chiz hayi ke tu data ye VAGHEI didim ─────────────────────────────
 * 1. 🔴 Gheymat haye IRR be **HEZAR TOMAN**-and: `GOLD18 = 26891` →
 *    26,891,000 toman/g → 268,910,000 rial. Do zarib: ×1000 (scale 3 e
 *    parser) va ×10 (toman→rial). `XAU` DOLLAR e ons ast، bedun e tabdil.
 * 2. 🔴 Jofte VAGHEI az did e platform: `sell` = platform miforushe =
 *    karbar mikhare = **ASK** (balatar)؛ `buy` = **BID**. Sekke ha buy=sell
 *    daran (yek gheymat) — har do side ba yek meghdar miad.
 * 3. `date.utc_timestamp` ISO ba `Z` — tanha timestamp e ghabel e e'temad:
 *    `jdatetime` e sekke ha `2647-12-27` neshun midad (bug e khod e site).
 * 4. `status` faghat ru ba'zi radif ha hast؛ `disabled` rad mishe، nabud =
 *    fa'al.
 * 5. `GOLD18_INTL` / `GOLD_BUBBLE_*` mohasebati-and (ons×dollar، habab) —
 *    amdan map nashodan؛ gheymat e bazaar nistan.
 * 6. Hich header e khassi lazem nist (bedun e UA ham 200، ~0.25s).
 */
final readonly class TlynQuoteSource implements QuoteSourcePort
{
    public const KEY = 'tlyn';

    private const DEFAULT_URL = 'https://price.tlyn.ir/api/v1/price';

    /** IRR ha be hezar toman-and: scale 3 = ×1000 bedun e float. */
    private const THOUSAND_SCALE = 3;

    /**
     * @var array<string, array{symbol: Instrument, unit: UpstreamUnit}>
     */
    private const MAP = [
        // 🔴 `GOLD18` dar VAGHE' gheymat e moamele ye ABSHODE ast، na gram e
        // 18 ayar e zinati — tlyn platform e kharid/foroosh e abshode-ast
        // (tasmim e sahebe proje، 2026-10-07).
        'GOLD18' => ['symbol' => Instrument::GoldMeltedGram, 'unit' => UpstreamUnit::Toman],
        'GOLD_MITHQAL17' => ['symbol' => Instrument::GoldMesghal, 'unit' => UpstreamUnit::Toman],
        'SEKEH_E' => ['symbol' => Instrument::CoinEmami, 'unit' => UpstreamUnit::Toman],
        'SEKEH_NIM' => ['symbol' => Instrument::CoinHalf, 'unit' => UpstreamUnit::Toman],
        'SEKEH_ROB' => ['symbol' => Instrument::CoinQuarter, 'unit' => UpstreamUnit::Toman],
        // Jahani — dollar e ons، bedun e tabdil.
        'XAU' => ['symbol' => Instrument::GoldOunce, 'unit' => UpstreamUnit::None],
    ];

    /** @var array<string, Side> field e upstream => side e ma */
    private const SIDES = [
        'sell' => Side::Ask,
        'buy' => Side::Bid,
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
            throw new RuntimeException("tlyn: request shekast khord — {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("tlyn: pasokh e na-movafagh ({$response->status()}).");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('tlyn: pasokh JSON nist.');
        }

        $body = $response->body();

        return $this->parse($payload)->withPayloadMeta(md5($body), strlen($body));
    }

    /**
     * @param  array<string, mixed>  $payload  body e kamel (ba `prices[]`)
     */
    public function parse(array $payload): SourceResultDto
    {
        $list = $payload['prices'] ?? null;

        if (! is_array($list)) {
            throw new RuntimeException('tlyn: payload shekl e entezari nadare (`prices` nist).');
        }

        $bySymbol = [];

        foreach ($list as $row) {
            if (is_array($row) && is_string($row['symbol'] ?? null)) {
                $bySymbol[$row['symbol']] = $row;
            }
        }

        $quotes = [];
        $missing = [];

        foreach (self::MAP as $upstreamKey => $entry) {
            $row = $bySymbol[$upstreamKey] ?? null;

            if ($row === null || ($row['status'] ?? 'enabled') === 'disabled' || ! is_array($row['price'] ?? null)) {
                $missing[] = $upstreamKey;

                continue;
            }

            $instrument = $entry['symbol'];
            $quote = $instrument->quote();
            $at = $this->toUtc($row['date']['utc_timestamp'] ?? null);
            $high = $this->optionalPrice($row['price']['highest'] ?? null, $quote, $entry['unit']);
            $low = $this->optionalPrice($row['price']['lowest'] ?? null, $quote, $entry['unit']);

            foreach (self::SIDES as $field => $side) {
                $minor = $this->toMinor($row['price'][$field] ?? null, $quote, $entry['unit']);

                if ($minor === null || $minor <= 0) {
                    continue;
                }

                $quotes[] = new RawQuoteDto(
                    providerKey: self::KEY,
                    instrument: $instrument,
                    side: $side,
                    price: new Price($minor, $quote),
                    upstreamAt: $at,
                    dayHigh: $high,
                    dayLow: $low,
                );
            }
        }

        return new SourceResultDto(quotes: $quotes, unmappedLabels: $missing);
    }

    /**
     * IRR: hezar toman → toman (scale 3) → rial (×10). USD: dollar e sahih
     * ya ashari، scale e khod e arz.
     */
    private function toMinor(mixed $raw, QuoteCurrency $quote, UpstreamUnit $unit): ?int
    {
        if (! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
            return null;
        }

        if ($quote === QuoteCurrency::USD) {
            return NumberParser::toMinor((string) $raw, [','], '.', $quote->scale());
        }

        $toman = NumberParser::toMinor((string) $raw, [','], '.', self::THOUSAND_SCALE);

        return $toman === null ? null : $toman * $unit->multiplier();
    }

    private function optionalPrice(mixed $raw, QuoteCurrency $quote, UpstreamUnit $unit): ?Price
    {
        $minor = $this->toMinor($raw, $quote, $unit);

        return $minor === null || $minor <= 0 ? null : new Price($minor, $quote);
    }

    private function toUtc(mixed $raw): ?CarbonImmutable
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw)->utc();
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

    /** @return array<int, string> */
    public static function mappedKeys(): array
    {
        return array_keys(self::MAP);
    }
}
