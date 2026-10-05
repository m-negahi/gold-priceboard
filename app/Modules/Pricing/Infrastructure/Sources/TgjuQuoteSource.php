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
 * tgju.org — `GET https://call1.tgju.org/ajax.json`
 *
 * Shekl e payload:
 *   {"current": {"<key>": {"p","h","l","d","dp","dt","t","ts"}}, ...}
 *
 * ── Chiz hayi ke tu data ye VAGHEI didim ─────────────────────────────
 * 1. `p` string ast ba comma: `"266,400,000"` — va ba'zi vaght ba `\t` e
 *    jolosh: `"\t8520000"` (`mesghal_buy`).
 * 2. Az 963 symbol, faghat ~389 ta taze-an. `zinc` az 2021, `geram18_buy`
 *    az 2018. Ma faghat 16 kelid ro map mikonim pas aksar e un ashghal
 *    aslan be ma nemirese — vali bebin nokte ye 4.
 * 3. `tgju_gold_irg18` + `_buy` TANHA jofte bid/ask e zende ast. Baghi ye
 *    `*_buy` ha morde-an.
 * 4. 🔴 `ons`, `silver`, `copperp` va `gerami` tu hamin feed faghat RUZANE
 *    be-rooz mishan (`ts` = nime-shab e rooz e ghabl), na zende. Pas
 *    `freshness_seconds` e in chahar ta bayad tu `provider_spreads` baraye
 *    tgju override beshe (~1 rooz), vagarna har tick rad mishan.
 *    In dalil e vojud e un soton ast.
 *
 * Hame ye gheymat haye IRR be RIAL-and (bar khalaf e estjt).
 * `ts` be vaght e Tehran ast va **bedun e offset** miad.
 */
final readonly class TgjuQuoteSource implements QuoteSourcePort
{
    public const KEY = 'tgju';

    private const DEFAULT_URL = 'https://call1.tgju.org/ajax.json';

    /**
     * Map e kelid e upstream be symbol e canonical.
     *
     * `unit` **per-entry** ast, na per-provider: tgju gheymat haye IRR ro
     * Rial mide va gheymat haye jahani ro USD. Yek meghdar e sath e provider
     * yeki az in do dasteh ro kharab mikone.
     *
     * @var array<string, array{symbol: Instrument, side: Side, unit: UpstreamUnit}>
     */
    private const MAP = [
        'geram18' => ['symbol' => Instrument::Gold18Gram, 'side' => Side::Last, 'unit' => UpstreamUnit::Rial],
        'geram24' => ['symbol' => Instrument::Gold24Gram, 'side' => Side::Last, 'unit' => UpstreamUnit::Rial],

        // Tanha jofte bid/ask e vaghei. `_buy` = kharid = bid.
        'tgju_gold_irg18' => ['symbol' => Instrument::GoldMeltedGram, 'side' => Side::Ask, 'unit' => UpstreamUnit::Rial],
        'tgju_gold_irg18_buy' => ['symbol' => Instrument::GoldMeltedGram, 'side' => Side::Bid, 'unit' => UpstreamUnit::Rial],

        'mesghal' => ['symbol' => Instrument::GoldMesghal, 'side' => Side::Last, 'unit' => UpstreamUnit::Rial],
        'sekee' => ['symbol' => Instrument::CoinEmami, 'side' => Side::Last, 'unit' => UpstreamUnit::Rial],
        'sekeb' => ['symbol' => Instrument::CoinBahar, 'side' => Side::Last, 'unit' => UpstreamUnit::Rial],
        'nim' => ['symbol' => Instrument::CoinHalf, 'side' => Side::Last, 'unit' => UpstreamUnit::Rial],
        'rob' => ['symbol' => Instrument::CoinQuarter, 'side' => Side::Last, 'unit' => UpstreamUnit::Rial],
        'gerami' => ['symbol' => Instrument::CoinGerami, 'side' => Side::Last, 'unit' => UpstreamUnit::Rial],

        'silver_999' => ['symbol' => Instrument::Silver999Gram, 'side' => Side::Last, 'unit' => UpstreamUnit::Rial],
        'silver_925' => ['symbol' => Instrument::Silver925Gram, 'side' => Side::Last, 'unit' => UpstreamUnit::Rial],

        // Jahani — ghablan USD-and, hich tabdili lazem nist.
        'ons' => ['symbol' => Instrument::GoldOunce, 'side' => Side::Last, 'unit' => UpstreamUnit::None],
        'silver' => ['symbol' => Instrument::SilverOunce, 'side' => Side::Last, 'unit' => UpstreamUnit::None],
        'copperp' => ['symbol' => Instrument::CopperPound, 'side' => Side::Last, 'unit' => UpstreamUnit::None],

        'price_dollar_rl' => ['symbol' => Instrument::UsdIrr, 'side' => Side::Last, 'unit' => UpstreamUnit::Rial],
    ];

    private const TIMEZONE = 'Asia/Tehran';

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
            url: (string) ($this->options['url'] ?? self::DEFAULT_URL),
            headers: ['User-Agent' => $this->userAgent(), 'Accept' => 'application/json'],
            timeout: $this->timeout,
        );
    }

    public function fetch(): SourceResultDto
    {
        $url = (string) ($this->options['url'] ?? self::DEFAULT_URL);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->acceptJson()
                ->get($url);
        } catch (Throwable $e) {
            throw new RuntimeException("tgju: request shekast khord — {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("tgju: pasokh e na-movafagh ({$response->status()}).");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('tgju: pasokh JSON nist.');
        }

        $body = $response->body();

        return $this->parse($payload)->withPayloadMeta(md5($body), strlen($body));
    }

    /**
     * Parse e payload e ghablan-gerefte-shode.
     *
     * Jodā az `fetch()` ast ta test betune ru fixture e vaghei ejra beshe
     * bedun e hich request e shabake-i.
     *
     * Hich facade seda nemizane ta ghabl e unit test bashe.
     *
     * @param  array<string, mixed>  $payload  payload e kamel (ba kelid e `current`)
     */
    public function parse(array $payload): SourceResultDto
    {
        $current = $payload['current'] ?? null;

        if (! is_array($current)) {
            // Shekl e payload avaz shode — in yek shekast ast, na «hich
            // gheymati nabud». Pipeline bayad provider ro na-salem bebine.
            throw new RuntimeException('tgju: payload shekl e entezari nadare (`current` nist).');
        }

        $quotes = [];
        $missing = [];

        foreach (self::MAP as $upstreamKey => $entry) {
            $row = $current[$upstreamKey] ?? null;

            if (! is_array($row)) {
                // Kelid-i ke map karde budim dige tu payload nist — ya'ni
                // tgju esm esh ro avaz karde. Bayad ma'lum beshe.
                $missing[] = $upstreamKey;

                continue;
            }

            $instrument = $entry['symbol'];
            $quote = $instrument->quote();

            $minor = $this->toMinor($row['p'] ?? null, $quote, $entry['unit']);

            // Gheymat e ghabl e parse ya sefr saket rad mishe: `null` ya'ni
            // «in radif ro nadide begir», na «gheymat sefr ast».
            if ($minor === null || $minor <= 0) {
                continue;
            }

            $quotes[] = new RawQuoteDto(
                providerKey: self::KEY,
                instrument: $instrument,
                side: $entry['side'],
                price: new Price($minor, $quote),
                upstreamAt: $this->toUtc($row['ts'] ?? null),
                dayHigh: $this->optionalPrice($row['h'] ?? null, $quote, $entry['unit']),
                dayLow: $this->optionalPrice($row['l'] ?? null, $quote, $entry['unit']),
                upstreamChangePercent: isset($row['dp']) ? (float) $row['dp'] : null,
            );
        }

        return new SourceResultDto(quotes: $quotes, unmappedLabels: $missing);
    }

    /**
     * Policy e separator e tgju.
     *
     * IRR -> adad e sahih ast, pas noghte faghat mitune hezargan bashe.
     * USD -> ashari ast (`"4,140.19"`), pas noghte ma'ni dare.
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

    private function optionalPrice(mixed $raw, QuoteCurrency $quote, UpstreamUnit $unit): ?Price
    {
        $minor = $this->toMinor($raw, $quote, $unit);

        return $minor === null || $minor <= 0 ? null : new Price($minor, $quote);
    }

    /**
     * `ts` e tgju `"2026-10-04 12:21:30"` ast — **bedun e offset**.
     *
     * Bayad be onvan e vaght e Tehran khunde she va ba'd be UTC tabdil.
     * Agar be onvan e UTC khunde she, gheymat 3.5 saat «az ayande» be nazar
     * mirese va har check e tazegi gij mishe.
     */
    private function toUtc(mixed $ts): ?CarbonImmutable
    {
        if (! is_string($ts) || trim($ts) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($ts, self::TIMEZONE)->utc();
        } catch (Throwable) {
            return null;
        }
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
