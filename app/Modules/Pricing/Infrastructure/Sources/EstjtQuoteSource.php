<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Sources;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Application\Dto\SourceResultDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Enums\UpstreamUnit;
use App\Modules\Pricing\Domain\Ports\QuoteSourcePort;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Infrastructure\Support\NumberParser;
use App\Modules\Pricing\Infrastructure\Support\PersianText;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * estjt.ir — `POST /wp-admin/admin-ajax.php` ba body e `action=new_price`
 *
 * Yek site e WordPress ast ba plugin e `tablotala`. Pasokh:
 *   {"gold": "<table>…</table>", "coin": "<table>…</table>"}
 *
 * ── Panj chiz ke tu data ye VAGHEI didim ─────────────────────────────
 * 1. 🔴 Bedun e User-Agent e browser VA HTTP/1.1, request **timeout**
 *    mikhore. Har do ejbari-an (test shod).
 * 2. 🔴 Gheymat ha TOMAN-and, na Rial. `طلا ۱۸ عیار = ۲۶٫۶۸۱٫۸۰۰` toman
 *    = 266,818,000 rial, ke ba tgju (266,400,000) michasbe. x10 ejbari.
 * 3. 🔴 Separator e hezargan `٫` (U+066B) ast — ke MA'NAN separator e
 *    ASHAR e Arabi-e. Policy e `NumberParser` har-source-jodagane ast
 *    daghighan be hamin dalil.
 * 4. 🔴 «انس طلا» ba `$` miad va DOLLAR ast, dar hali ke 8 radif e digar
 *    Toman-and. Pas `unit` bayad PER-INSTRUMENT bashe; yek
 *    `upstream_unit=toman` e sath e provider ons ro x10 mikone va
 *    `$39,005` dar miare — onghadr bavar-pazir ke az zir e cheshm rad beshe.
 * 5. Label ha Farsi-and va ZWNJ/raghame Farsi daran, pas match bayad ru
 *    shekl e `PersianText::fold()` bashe, na string e kham.
 *
 * estjt hich timestamp NEMIDE. Pas `upstreamAt` null ast va tazegi az
 * `payloadHash` fahmide mishe (hash e sabet + bazaar e baz = feed e yakh-zade).
 */
final readonly class EstjtQuoteSource implements QuoteSourcePort
{
    public const KEY = 'estjt';

    private const DEFAULT_URL = 'https://www.estjt.ir/wp-admin/admin-ajax.php';

    /** Esm e action e plugin e `tablotala` (az JS e khod e site dar amad). */
    private const ACTION = 'new_price';

    /**
     * Map ba label e **FOLD-SHODE**.
     *
     * Kelid ha khoruji e `PersianText::fold()`-and: `ي`→`ی`, hazf e ZWNJ,
     * va raghame Farsi→Latin. Pas «طلا ۱۸ عیار» mishe «طلا 18 عیار».
     *
     * «مظنه تهران» amdan be `GoldMesghal` map shode: mazane va mesghal hamun
     * instrument-and, va in yek radif e moghayese ye VAGHEI ba tgju misaze
     * jaye do radif e yatim.
     *
     * @var array<string, array{symbol: Instrument, unit: UpstreamUnit}>
     */
    private const MAP = [
        // ⚠️ Tanha radif e DOLLARI tu in feed.
        'انس طلا' => ['symbol' => Instrument::GoldOunce, 'unit' => UpstreamUnit::None],

        'مظنه تهران' => ['symbol' => Instrument::GoldMesghal, 'unit' => UpstreamUnit::Toman],
        'طلا 18 عیار' => ['symbol' => Instrument::Gold18Gram, 'unit' => UpstreamUnit::Toman],
        'طلای 24 عیار' => ['symbol' => Instrument::Gold24Gram, 'unit' => UpstreamUnit::Toman],

        'سکه طرح جدید' => ['symbol' => Instrument::CoinEmami, 'unit' => UpstreamUnit::Toman],
        'سکه طرح قدیم' => ['symbol' => Instrument::CoinBahar, 'unit' => UpstreamUnit::Toman],
        'نیم سکه' => ['symbol' => Instrument::CoinHalf, 'unit' => UpstreamUnit::Toman],
        'ربع سکه' => ['symbol' => Instrument::CoinQuarter, 'unit' => UpstreamUnit::Toman],
        'سکه گرمی' => ['symbol' => Instrument::CoinGerami, 'unit' => UpstreamUnit::Toman],
    ];

    /** Separator haye hezargan e in site. `٫` = U+066B, `٬` = U+066C. */
    private const THOUSANDS = ['٫', '٬', ','];

    /** @param array<string, mixed> $options */
    public function __construct(
        private array $options = [],
        private int $timeout = 10,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function fetch(): SourceResultDto
    {
        $url = (string) ($this->options['url'] ?? self::DEFAULT_URL);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    // Har se ta lazem-and — bedun e inha server javab nemide.
                    'User-Agent' => $this->userAgent(),
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer' => (string) ($this->options['referer'] ?? 'https://www.estjt.ir/'),
                ])
                // 🔴 HTTP/1.1 ejbari: ba negotiation e default request
                // timeout mikhore.
                ->withOptions(['version' => '1.1'])
                ->asForm()
                ->post($url, ['action' => self::ACTION]);
        } catch (Throwable $e) {
            throw new RuntimeException("estjt: request shekast khord — {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("estjt: pasokh e na-movafagh ({$response->status()}).");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('estjt: pasokh JSON nist.');
        }

        $body = $response->body();

        return $this->parse($payload)->withPayloadMeta(md5($body), strlen($body));
    }

    /**
     * Parse e payload e ghablan-gerefte-shode (baraye test ru fixture).
     *
     * Hich facade seda nemizane ta ghabl e unit test bashe.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parse(array $payload): SourceResultDto
    {
        $quotes = [];
        $unmapped = [];

        // Har do fragment — `gold` va `coin`. Agar yeki nabud, baghi bayad
        // hamchenan kar kone.
        foreach (['gold', 'coin'] as $section) {
            $html = $payload[$section] ?? null;

            if (! is_string($html) || trim($html) === '') {
                continue;
            }

            foreach ($this->scrape($html) as $label => $rawPrice) {
                $entry = self::MAP[$label] ?? null;

                if ($entry === null) {
                    // Label e nashenas = markup avaz shode ya radif e jadid.
                    // Saket dur rikhte nemishe — pipeline log esh mikone ta
                    // taghir e upstream tu yek tick ma'lum beshe.
                    $unmapped[] = $label;

                    continue;
                }

                $instrument = $entry['symbol'];
                $quote = $instrument->quote();
                $minor = $this->toMinor($rawPrice, $quote, $entry['unit']);

                if ($minor === null || $minor <= 0) {
                    continue;
                }

                $quotes[] = new RawQuoteDto(
                    providerKey: self::KEY,
                    instrument: $instrument,
                    side: Side::Last,
                    price: new Price($minor, $quote),
                    // estjt timestamp nemide — tazegi az `payloadHash` miad.
                    upstreamAt: null,
                );
            }
        }

        return new SourceResultDto(quotes: $quotes, unmappedLabels: $unmapped);
    }

    /**
     * Estekhraj e radif ha az yek fragment e HTML.
     *
     * ⚠️ Ba `DOMXPath` ast va **na regex**: markup e WordPress avaz mishe va
     * yek regex saket chizi ro az dast mide jaye inke beshkane.
     *
     * @return array<string, string> label e fold-shode => gheymat e kham
     */
    private function scrape(string $html): array
    {
        $dom = new DOMDocument;

        // `@` chun markup e WordPress hamishe valid nist va warning mide.
        // Prefix e XML encoding lazem ast vagarna UTF-8 kharab mishe.
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($dom);
        $rows = [];

        foreach ($xpath->query('//tr[td[@class="name"]]') ?: [] as $tr) {
            if (! $tr instanceof DOMElement) {
                continue;
            }

            $name = $xpath->query('.//td[@class="name"]', $tr)?->item(0);
            $price = $xpath->query('.//td[@class="price"]', $tr)?->item(0);

            if ($name === null || $price === null) {
                continue;
            }

            $label = PersianText::fold(trim($name->textContent));

            if ($label !== '') {
                $rows[$label] = trim($price->textContent);
            }
        }

        return $rows;
    }

    /**
     * Hame ye gheymat haye in site adad e SAHIH-and (`$decimal === null`),
     * pas har noghte-i faghat mitune hezargan bashe.
     *
     * Alamat e arz dasti pak mishe: `NumberParser` amdan `$` ro rad mikone
     * (meghdar e gheyr e adadi bayad `null` bede, na inke saket nadide
     * gerefte she), pas Source — ke midune in site `$` mizare — khodesh un
     * ro bar midare.
     */
    private function toMinor(string $raw, QuoteCurrency $quote, UpstreamUnit $unit): ?int
    {
        $cleaned = str_replace(['$', '﷼', 'تومان', 'ریال'], '', $raw);

        $minor = NumberParser::toMinor($cleaned, self::THOUSANDS, null, $quote->scale());

        if ($minor === null) {
            return null;
        }

        return $minor * $unit->multiplier();
    }

    private function userAgent(): string
    {
        return (string) ($this->options['user_agent']
            ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36');
    }

    /** @return array<int, string> */
    public static function mappedLabels(): array
    {
        return array_keys(self::MAP);
    }
}
