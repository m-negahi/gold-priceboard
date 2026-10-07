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
 * wallgold.ir / وال‌گلد (platform) — `GET https://api.wallgold.ir/api/v1/price`
 * ba `symbol` + `side` (Bearer token — doc: developers.wallgold.ir).
 *
 * Pasokh (2026-10-07):
 *   {"result":{"price":"26530000","priceExpiresAt":"…","currentTime":"…",
 *     "ttl":86358.6},"message":"عملیات با موفقیت انجام شد","success":true}
 *
 * ── Chiz hayi ke tu data ye VAGHEI didim ─────────────────────────────
 * 1. Do bazaar (az `GET /api/v1/markets`): `GLD_18C_750TMN` (esman 18
 *    ayar vali dar vaghe' ABSHODE ye moamelati → `xau-melted-g-irr`) va
 *    `SLV_925TMN` (noghre 925). Gheymat TOMAN e har gram، integer e
 *    string — ×10 rial.
 * 2. 🔴 `side` ejbari-e va har (symbol، side) yek REQUEST e joda-st →
 *    `request()` null mide va `fetch()` khodesh 4 call mizane (masir e
 *    direct e FetchStage؛ har call ~150ms، kol < 1s).
 * 3. 🔴 Ma'ni ye side az did e BAZAAR ast (mesl e `_buy` e tgju):
 *    andaze-giri: noghre `BUY 497,000 < SELL 498,000` → `BUY` = kharid
 *    e platform az karbar = **bid**؛ `SELL` = foroosh be karbar = **ask**.
 * 4. `currentTime` sa'at e SERVER e (hamishe alan) va `priceExpiresAt`
 *    yek TTL e ~24h ast — hich kodum «zaman e update» nistan →
 *    `upstreamAt` null va tazegi az `payloadHash` (mesl e platform haye
 *    bedun e timestamp).
 * 5. API key tu HEADER mire (na URL) — exception ha chizi lo nemidan.
 *    Key az env (`WALLGOLD_API_KEY`) miad؛ hich vaght tu seeder/DB/git.
 */
final readonly class WallgoldQuoteSource implements QuoteSourcePort
{
    public const KEY = 'wallgold';

    private const DEFAULT_BASE = 'https://api.wallgold.ir';

    /**
     * @var array<string, Instrument>
     */
    private const MARKETS = [
        // 🔴 Mesl e BAGHI ye platform ha، «18 ayar» e wallgold dar vaghe'
        // gheymat e MOAMELE ye ABSHODE ast (tasmim e sahebe proje،
        // 2026-10-07: «hame ye platform ha 18 ayareshun abshode ye
        // moamelati-e»).
        'GLD_18C_750TMN' => Instrument::GoldMeltedGram,
        'SLV_925TMN' => Instrument::Silver925Gram,
    ];

    /**
     * @var array<string, Side>
     */
    private const SIDES = [
        'BUY' => Side::Bid,
        'SELL' => Side::Ask,
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

    /** Chand request e joda lazem ast — pool e tak-request ma'ni nadare. */
    public function request(): ?SourceRequestDto
    {
        return null;
    }

    public function fetch(): SourceResultDto
    {
        $apiKey = $this->apiKey();
        $payloads = [];
        $hashes = '';
        $size = 0;

        foreach (array_keys(self::MARKETS) as $market) {
            foreach (array_keys(self::SIDES) as $side) {
                try {
                    $response = Http::timeout($this->timeout)
                        ->withToken($apiKey)
                        ->acceptJson()
                        ->get($this->base().'/api/v1/price', ['symbol' => $market, 'side' => $side]);
                } catch (Throwable $e) {
                    throw new RuntimeException("wallgold: request shekast khord — {$e->getMessage()}", previous: $e);
                }

                if (! $response->successful()) {
                    throw new RuntimeException("wallgold: pasokh e na-movafagh ({$response->status()}) baraye {$market}/{$side}.");
                }

                $payload = $response->json();

                if (! is_array($payload)) {
                    throw new RuntimeException("wallgold: pasokh JSON nist ({$market}/{$side}).");
                }

                $payloads[$market][$side] = $payload;
                $body = $response->body();
                $hashes .= md5($body);
                $size += strlen($body);
            }
        }

        return $this->parse($payloads)->withPayloadMeta(md5($hashes), $size);
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $payloads  `[market][side] => body`
     */
    public function parse(array $payloads): SourceResultDto
    {
        if ($payloads === []) {
            throw new RuntimeException('wallgold: hich payload-i nist.');
        }

        $quotes = [];
        $missing = [];

        foreach (self::MARKETS as $market => $instrument) {
            foreach (self::SIDES as $sideKey => $side) {
                $body = $payloads[$market][$sideKey] ?? null;

                if (! is_array($body) || ($body['success'] ?? null) !== true) {
                    $missing[] = "{$market}.{$sideKey}";

                    continue;
                }

                $minor = $this->toMinor($body['result']['price'] ?? null);

                if ($minor === null || $minor <= 0) {
                    $missing[] = "{$market}.{$sideKey}.price";

                    continue;
                }

                $quotes[] = new RawQuoteDto(
                    providerKey: self::KEY,
                    instrument: $instrument,
                    side: $side,
                    price: new Price($minor, $instrument->quote()),
                    // Hich timestamp e update-i nemide (docblock 4).
                    upstreamAt: null,
                );
            }
        }

        return new SourceResultDto(quotes: $quotes, unmappedLabels: $missing);
    }

    /** toman/g (string e integer) → rial/g. */
    private function toMinor(mixed $raw): ?int
    {
        if (! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
            return null;
        }

        $toman = NumberParser::toMinor((string) $raw, [','], null, 0);

        return $toman === null ? null : $toman * UpstreamUnit::Toman->multiplier();
    }

    private function apiKey(): string
    {
        $key = (string) ($this->options['api_key'] ?? config('pricing.wallgold.api_key', ''));

        if ($key === '') {
            throw new RuntimeException('wallgold: API key tanzim nashode (WALLGOLD_API_KEY).');
        }

        return $key;
    }

    private function base(): string
    {
        return rtrim((string) ($this->options['base'] ?? self::DEFAULT_BASE), '/');
    }
}
