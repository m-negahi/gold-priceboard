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
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * ecogold.ir / اکوگلد (platform) — `GET https://backend.ecogold.ir/api/prices/otc`
 *
 * Shekl e payload (2026-11-02):
 *   {"data":[{"symbol":"SILVER999-IRT","sell_price":"465000.00000000",
 *             "buy_price":"473000.00000000","change":"3.50","signature":"<JWT>",
 *             "valid_at":"…Z","time_now":"…Z","created_at":"2026-11-02T08:51:34.288458Z"}, …]}
 *
 * ── Chiz hayi ke tu data ye VAGHEI didim ─────────────────────────────
 * 1. 🔴 Gheymat ha **TOMAN**-and (`-IRT`) ba 8 raghame ashar e sefr:
 *    `GOLD18 = 25781000.00000000` → 257,810,000 rial. `decimal = '.'`
 *    EJBARI ast — ba `null` noghte separator e hezargan hesab mishe va
 *    adad 10^8 barabar mishe.
 * 2. 🔴 Jofte VAGHEI az did e karbar: `buy_price` = karbar mikhare = **ASK**
 *    (balatar)؛ `sell_price` = **BID**. Andaze-giri: noghre 473,000 / 465,000.
 * 3. `created_at` ISO ba `Z` = zaman e sakht e gheymat (har ~daghighe).
 *    `valid_at` = engheza ye `signature` (~2 daghighe ba'd)، `time_now` =
 *    sa'at e server — hich kodum zaman e gheymat nistan.
 * 4. Moghayese ba tgju ye hamun lahze: 18 ayar 0.2%، sekke 0.1–1.7%،
 *    noghre 999 1–2.7% zir e tgju — martabe/vahed dorost ast.
 * 5. 🔴 «18 ayar» e platform ABSHODE ye moamelati-e (hamun tasmim e
 *    melligold/wallgold/tlyn) → `xau-melted-g-irr`. `COINFULL` = emami
 *    (ba `sekee` e tgju mikhune، na bahar).
 * 6. `signature` (JWT e khod e ecogold baraye OTC) va `change` amdan
 *    estefade nemishan. Hich header e khassi lazem nist (~0.2s).
 */
final readonly class EcogoldQuoteSource implements QuoteSourcePort
{
    public const KEY = 'ecogold';

    private const DEFAULT_URL = 'https://backend.ecogold.ir/api/prices/otc';

    /**
     * @var array<string, array{symbol: Instrument, unit: UpstreamUnit}>
     */
    private const MAP = [
        'GOLD18-IRT' => ['symbol' => Instrument::GoldMeltedGram, 'unit' => UpstreamUnit::Toman],
        'COINFULL-IRT' => ['symbol' => Instrument::CoinEmami, 'unit' => UpstreamUnit::Toman],
        'COINHALF-IRT' => ['symbol' => Instrument::CoinHalf, 'unit' => UpstreamUnit::Toman],
        'COINQUARTER-IRT' => ['symbol' => Instrument::CoinQuarter, 'unit' => UpstreamUnit::Toman],
        'SILVER999-IRT' => ['symbol' => Instrument::Silver999Gram, 'unit' => UpstreamUnit::Toman],
    ];

    /** @var array<string, Side> field e upstream => side e ma */
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
            throw new RuntimeException("ecogold: request shekast khord — {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("ecogold: pasokh e na-movafagh ({$response->status()}).");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('ecogold: pasokh JSON nist.');
        }

        $body = $response->body();

        return $this->parse($payload)->withPayloadMeta(md5($body), strlen($body));
    }

    /**
     * @param  array<string, mixed>  $payload  body e kamel (ba `data[]`)
     */
    public function parse(array $payload): SourceResultDto
    {
        $list = $payload['data'] ?? null;

        if (! is_array($list)) {
            throw new RuntimeException('ecogold: payload shekl e entezari nadare (`data` nist).');
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

            if ($row === null) {
                $missing[] = $upstreamKey;

                continue;
            }

            $instrument = $entry['symbol'];
            $quote = $instrument->quote();
            $at = $this->toUtc($row['created_at'] ?? null);

            foreach (self::SIDES as $field => $side) {
                $minor = $this->toMinor($row[$field] ?? null, $entry['unit']);

                if ($minor === null || $minor <= 0) {
                    $missing[] = "{$upstreamKey}.{$field}";

                    continue;
                }

                $quotes[] = new RawQuoteDto(
                    providerKey: self::KEY,
                    instrument: $instrument,
                    side: $side,
                    price: new Price($minor, $quote),
                    upstreamAt: $at,
                );
            }
        }

        return new SourceResultDto(quotes: $quotes, unmappedLabels: $missing);
    }

    /** Toman ba ashar e sefr → toman e sahih (scale 0) → rial (×10). */
    private function toMinor(mixed $raw, UpstreamUnit $unit): ?int
    {
        if (! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
            return null;
        }

        $toman = NumberParser::toMinor((string) $raw, [','], '.', 0);

        return $toman === null ? null : $toman * $unit->multiplier();
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
