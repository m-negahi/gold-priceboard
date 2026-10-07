<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Sources;

use App\Modules\Common\Helpers\Jalali;
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
 * nerkh.io — `GET https://live.nerkh.io/api/prices`
 *
 * Shekl e payload (2026-10-06):
 *   {
 *     "gold":     {"GOLD18K": {"current": "26704500", "min": {"1hour","12hour","24hour"},
 *                              "max": {...}, "ch_24h_percent": 0.4,
 *                              "update": "1405-07-14 20:23:11"}, ...},
 *     "coin":     {"SEKE_EMAMI": {...}, ...},
 *     "currency": {"USD": {...}, ...},
 *     "crypto":   {...},
 *     "timestamp": 1791305682000, "date": "1405-07-14 20:24:42",
 *     "sparklines": {...}, "sparkTimestamps": {...}
 *   }
 *
 * ── Chiz hayi ke tu data ye VAGHEI didim ─────────────────────────────
 * 1. 🔴 Hame ye gheymat haye IRR **TOMAN**-and (`GOLD18K = 26,704,500` dar
 *    hali ke tgju ye hamun rooz 266-267M rial mide). ×10 ejbari.
 * 2. `OUNCE` va `OUNCE_SILVER` DOLLAR-and va bedun e ashar miyan (`"4164"`,
 *    `"61"`). Unit per-instrument ast — hamun dars e estjt.
 * 3. 🔴 `update` e har radif **JALALI** ast va bedun e offset:
 *    `"1405-07-14 20:23:11"` = vaght e Tehran. Bayad aval be miladi tabdil
 *    she va ba'd ba timezone e Tehran be UTC. `timestamp` e sath e bala
 *    (epoch ms) faghat fallback ast — un vaght e cache e khod e nerkh ast،
 *    na vaght e update e un symbol (90 saniye jadid-tar bud).
 * 4. Hich User-Agent ya header e khassi lazem nist (bedun e UA ham 200 dad،
 *    ~0.3s). `Cache-Control: max-age=30` — pas poll e zir e 30s bi-faide-ast.
 * 5. Feed gheymat e 18 ayar e chand PLATFORM + crypto + arz haye digar ro
 *    ham mide. Inja faghat radif haye MARJA' map shodan؛ baghi amdan
 *    unmapped NISTAN (jaye `missing` faghat kelid haye map-shode ye gom-shode
 *    gozaresh mishan — mesle tgju — vagarna har tick 40 label e bi-rabt log
 *    mishod). Item haye platform (`GOLD18K_STOCK`، …) 2026-10-07 ba darkhast
 *    e sahebe proje hazf shodan — platform ha ya API e khodeshun ro daran ya
 *    aslan nemiyan.
 *
 * `MESGHAL` (na `MAZANEH`) be `xau-mesghal-irr` map shode — tasmim e sahebe
 * proje (2026-10-06).
 */
final readonly class NerkhQuoteSource implements QuoteSourcePort
{
    public const KEY = 'nerkh';

    private const DEFAULT_URL = 'https://live.nerkh.io/api/prices';

    private const TIMEZONE = 'Asia/Tehran';

    /**
     * Map e `section.KEY` e upstream be symbol e canonical.
     *
     * `unit` **per-entry** ast، na per-provider (bebin `UpstreamUnit`).
     *
     * @var array<string, array{section: string, symbol: Instrument, unit: UpstreamUnit}>
     */
    private const MAP = [
        'GOLD18K' => ['section' => 'gold', 'symbol' => Instrument::Gold18Gram, 'unit' => UpstreamUnit::Toman],
        'GOLD24K' => ['section' => 'gold', 'symbol' => Instrument::Gold24Gram, 'unit' => UpstreamUnit::Toman],
        'MESGHAL' => ['section' => 'gold', 'symbol' => Instrument::GoldMesghal, 'unit' => UpstreamUnit::Toman],
        // 🔴 `MAZANEH` = mazane ye ABSHODE (per MESGHAL) — tabdil be gram:
        // ÷4.3318 (4.6083 g × 705/750). Sehat: 115,677,000/4.3318 ≈ GOLD18K
        // e hamun lahze (26,704,500) ✓. (darkhast e sahebe proje، 2026-10-07)
        'MAZANEH' => ['section' => 'gold', 'symbol' => Instrument::GoldMeltedGram, 'unit' => UpstreamUnit::Toman, 'perMesghal' => true],

        // Jahani — ghablan USD-and، hich tabdili lazem nist.
        'OUNCE' => ['section' => 'gold', 'symbol' => Instrument::GoldOunce, 'unit' => UpstreamUnit::None],
        'OUNCE_SILVER' => ['section' => 'gold', 'symbol' => Instrument::SilverOunce, 'unit' => UpstreamUnit::None],

        'SEKE_EMAMI' => ['section' => 'coin', 'symbol' => Instrument::CoinEmami, 'unit' => UpstreamUnit::Toman],
        'SEKE_BAHAR' => ['section' => 'coin', 'symbol' => Instrument::CoinBahar, 'unit' => UpstreamUnit::Toman],
        'SEKE_NIM' => ['section' => 'coin', 'symbol' => Instrument::CoinHalf, 'unit' => UpstreamUnit::Toman],
        'SEKE_ROB' => ['section' => 'coin', 'symbol' => Instrument::CoinQuarter, 'unit' => UpstreamUnit::Toman],
        'SEKE_1G' => ['section' => 'coin', 'symbol' => Instrument::CoinGerami, 'unit' => UpstreamUnit::Toman],

        'USD' => ['section' => 'currency', 'symbol' => Instrument::UsdIrr, 'unit' => UpstreamUnit::Toman],
    ];

    /** @param array<string, mixed> $options */
    public function __construct(
        private array $options = [],
        private int $timeout = 10,
        private string $providerKey = self::KEY,
    ) {}

    public function key(): string
    {
        return $this->providerKey;
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
            throw new RuntimeException("nerkh: request shekast khord — {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("nerkh: pasokh e na-movafagh ({$response->status()}).");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('nerkh: pasokh JSON nist.');
        }

        $body = $response->body();

        return $this->parse($payload)->withPayloadMeta(md5($body), strlen($body));
    }

    /**
     * Parse e payload e ghablan-gerefte-shode.
     *
     * Hich facade seda nemizane ta ba PHPUnit e khali ru fixture test beshe.
     *
     * @param  array<string, mixed>  $payload  payload e kamel (ba `gold`/`coin`/`currency`)
     */
    public function parse(array $payload): SourceResultDto
    {
        $sections = array_filter(
            ['gold' => $payload['gold'] ?? null, 'coin' => $payload['coin'] ?? null, 'currency' => $payload['currency'] ?? null],
            is_array(...),
        );

        if ($sections === []) {
            // Shekl e payload avaz shode — shekast ast، na «bazaar baste».
            throw new RuntimeException('nerkh: payload shekl e entezari nadare (hich kodum az `gold`/`coin`/`currency` nist).');
        }

        $fallbackAt = $this->epochMillisToUtc($payload['timestamp'] ?? null);

        $quotes = [];
        $missing = [];

        foreach ($this->entries() as $upstreamKey => $entry) {
            $row = $sections[$entry['section']][$upstreamKey] ?? null;

            if (! is_array($row)) {
                // Kelid-i ke map karde budim dige nist — nerkh esm esh ro
                // avaz karde. Bayad ma'lum beshe.
                $missing[] = "{$entry['section']}.{$upstreamKey}";

                continue;
            }

            $instrument = $entry['symbol'];
            $quote = $instrument->quote();
            $perMesghal = (bool) ($entry['perMesghal'] ?? false);

            $minor = $this->fromMesghal($this->toMinor($row['current'] ?? null, $quote, $entry['unit']), $perMesghal);

            // `null` ya'ni «in radif ro nadide begir»، na «gheymat sefr ast».
            if ($minor === null || $minor <= 0) {
                continue;
            }

            $quotes[] = new RawQuoteDto(
                providerKey: $this->providerKey,
                instrument: $instrument,
                side: Side::Last,
                price: new Price($minor, $quote),
                upstreamAt: $this->jalaliToUtc($row['update'] ?? null) ?? $fallbackAt,
                // Band e 24 sa'ate — nazdik tarin chiz be `h`/`l` e tgju.
                dayHigh: $this->optionalPrice($row['max']['24hour'] ?? null, $quote, $entry['unit'], $perMesghal),
                dayLow: $this->optionalPrice($row['min']['24hour'] ?? null, $quote, $entry['unit'], $perMesghal),
                upstreamChangePercent: is_numeric($row['ch_24h_percent'] ?? null) ? (float) $row['ch_24h_percent'] : null,
            );
        }

        // Noghre ye gram-i (999/925) tu feed nist vali gheymat e mohasebati
        // az ons + dollar e hamin feed dar miad (darkhast e sahebe proje).
        $this->appendDerivedSilver($quotes);

        return new SourceResultDto(quotes: $quotes, unmappedLabels: $missing);
    }

    /**
     * 🔴 Noghre ye 999/925 — MOHASEBATI az ons e noghre (USD) × dollar:
     *
     *   rial/gram = (ons / 31.1034768) × dollar × khulus
     *   minor     = s × u × (khulus×1000) / 311,034,768   (bcmath، half-up)
     *
     * (makhraj = 31.1034768 g/oz × 10^4 e scale e USD × 1000 e khulus.)
     *
     * ⚠️ In gheymat e SPOT ast؛ bazaar e Iran premium dare — andaze-giri
     * ru fixture haye ham-zaman: tgju 999 ~4.7% balatar az spot e nerkh.
     * Admin mitune ba spread e panel ru nerkh jobran kone. Timestamp =
     * KOHNE-tar e do parent (mohafeze-kar baraye check e tazegi).
     *
     * @param  list<RawQuoteDto>  $quotes
     */
    private function appendDerivedSilver(array &$quotes): void
    {
        $silver = null;
        $usd = null;

        foreach ($quotes as $quote) {
            if ($quote->instrument === Instrument::SilverOunce) {
                $silver = $quote;
            } elseif ($quote->instrument === Instrument::UsdIrr) {
                $usd = $quote;
            }
        }

        if ($silver === null || $usd === null) {
            return;
        }

        $at = match (true) {
            $silver->upstreamAt === null => $usd->upstreamAt,
            $usd->upstreamAt === null => $silver->upstreamAt,
            default => $silver->upstreamAt->lessThan($usd->upstreamAt) ? $silver->upstreamAt : $usd->upstreamAt,
        };

        $product = bcmul((string) $silver->price->minor, (string) $usd->price->minor);

        foreach ([[Instrument::Silver999Gram, '999'], [Instrument::Silver925Gram, '925']] as [$instrument, $purityPerMille]) {
            // Half-up: nesf e makhraj ghabl az taghsim.
            $minor = (int) bcdiv(bcadd(bcmul($product, $purityPerMille), '155517384'), '311034768', 0);

            if ($minor <= 0) {
                continue;
            }

            $quotes[] = new RawQuoteDto(
                providerKey: $this->providerKey,
                instrument: $instrument,
                side: Side::Last,
                price: new Price($minor, $instrument->quote()),
                upstreamAt: $at,
            );
        }
    }

    /**
     * Policy e separator e nerkh.
     *
     * Meghdar ha string e adad e khales-and (`"26704500"`)، vali comma ro
     * baraye ehtiyat hezargan migirim. USD ashari mitune bashe (hala gerd
     * shode miad)، IRR hamishe sahih.
     */
    private function toMinor(mixed $raw, QuoteCurrency $quote, UpstreamUnit $unit): ?int
    {
        if (! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
            return null;
        }

        $decimal = $quote === QuoteCurrency::USD ? '.' : null;

        $minor = NumberParser::toMinor((string) $raw, [','], $decimal, $quote->scale());

        if ($minor === null) {
            return null;
        }

        return $minor * $unit->multiplier();
    }

    private function optionalPrice(mixed $raw, QuoteCurrency $quote, UpstreamUnit $unit, bool $perMesghal = false): ?Price
    {
        $minor = $this->fromMesghal($this->toMinor($raw, $quote, $unit), $perMesghal);

        return $minor === null || $minor <= 0 ? null : new Price($minor, $quote);
    }

    /** Mazane (per mesghal e abshode) → rial e har gram: ×10000/43318 (bcmath، half-up). */
    private function fromMesghal(?int $minor, bool $perMesghal): ?int
    {
        if ($minor === null || ! $perMesghal) {
            return $minor;
        }

        return (int) bcdiv(bcadd(bcmul((string) $minor, '10000'), '21659'), '43318', 0);
    }

    /**
     * `"1405-07-14 20:23:11"` (Jalali، vaght e Tehran، bedun e offset) → UTC.
     *
     * Tarikh ba `Jalali::toIso()` tabdil mishe ke rooz e na-mojud e mah ro
     * rad mikone (null)، pas yek `update` e kharab be timestamp e ghalat
     * tabdil nemishe — mioftim ru fallback.
     */
    private function jalaliToUtc(mixed $raw): ?CarbonImmutable
    {
        if (! is_string($raw) || preg_match('/^(\d{4}[-\/]\d{1,2}[-\/]\d{1,2})[ T](\d{1,2}:\d{2}(?::\d{2})?)$/', trim($raw), $m) !== 1) {
            return null;
        }

        $iso = Jalali::toIso($m[1]);

        if ($iso === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse("{$iso} {$m[2]}", self::TIMEZONE)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    private function epochMillisToUtc(mixed $raw): ?CarbonImmutable
    {
        if (! is_numeric($raw) || (int) $raw <= 0) {
            return null;
        }

        return CarbonImmutable::createFromTimestampMs((int) $raw, 'UTC');
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

    /** @return list<string> */
    public static function itemKeys(): array
    {
        return array_keys(self::MAP);
    }

    /** @return array<string, array{section: string, symbol: Instrument, unit: UpstreamUnit}> */
    private function entries(): array
    {
        if (! array_key_exists('item', $this->options)) {
            return self::MAP;
        }

        $item = $this->options['item'];

        if (! is_string($item) || ! isset(self::MAP[$item])) {
            throw new RuntimeException('nerkh: option e item e nashenas; hich radif-i parse nashod.');
        }

        return [$item => self::MAP[$item]];
    }
}
