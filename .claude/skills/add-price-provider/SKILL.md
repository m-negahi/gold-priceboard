---
name: add-price-provider
description: Ezafe kardan e yek provider e gheymat e jadid (wallgold, taline, hamrahgold, milli, melligold, ya har site e digar) be onvan e QuoteSource. Invoke kon har vaght manba' e gheymat e jadid ezafe mikoni ya yek source e mojud kharab shode.
---

# Ezafe kardan e provider e jadid

7 gam. **Hich kodum ro rad nakon** — makhsusan gam 4 va 5.

## Ghabl az shoru': payload ro bebin

```bash
curl -sS --max-time 30 --http1.1 --compressed \
  -H 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/140.0 Safari/537.36' \
  '<URL>' -o /tmp/probe.json -w 'HTTP %{http_code} size=%{size_download}\n'
```
Agar WordPress ast، `admin-ajax.php` ro bebin va esm e `action` ro az JS e plugin dar biar:
```bash
curl -sS '<SITE>' | grep -oE 'src="[^"]+\.js[^"]*"'   # ba'd file e plugin ro begir va donbal e action: bgard
```

Se soal ke **bayad** javab bedi ghabl az code zadan:
1. Gheymat **Rial** ast ya **Toman**? (ekhtelaf e 10× — chek kon ba tgju ye hamun lahze)
2. Timestamp dare? agar na، chetor mifahmim bayat shode?
3. Buy/sell e **vaghei** dare ya faghat yek gheymat? (aksar faghat yeki)

## Gam 1 — radif e `providers`

Seeder ya migration:
```php
Provider::create([
    'key' => 'wallgold',          // yekta, kebab/snake
    'driver' => 'wallgold',       // mitune ba key fargh kone (chand maghaze ru yek driver)
    'name' => 'WallGold', 'name_fa' => 'وال‌گلد',
    'website' => 'https://wallgold.ir',
    'is_active' => false,         // ⬅️ KHAMOOSH besaz, ba'd az test roshan kon
    'timeout' => 10,
    'upstream_unit' => 'toman',   // faghat DEFAULT — gam 3 ro bebin
    'options' => ['base' => '…', 'user_agent' => '…'],
]);
```

## Gam 2 — class e `QuoteSource`

`app/Modules/Pricing/Infrastructure/Sources/WallgoldQuoteSource.php`

```php
final readonly class WallgoldQuoteSource implements QuoteSourcePort
{
    public function __construct(private array $options, private int $timeout = 10) {}

    public function key(): string { return 'wallgold'; }

    /** @return array<int, RawQuoteDto> */
    public function fetch(): array { /* … */ }
}
```

**`QuoteSource` faghat motarjem ast.** Hich `Cache::`، hich Eloquent، hich enrich. Vorudi HTTP، khoruji `RawQuoteDto[]`. (Dalil tu skill e `pricing-architecture`.)

## Gam 3 — map e `{upstream key → symbol, side, unit}`

```php
private const MAP = [
    'geram18' => ['symbol' => Instrument::Gold18Gram, 'side' => Side::Last, 'unit' => UpstreamUnit::Rial],
    'ons'     => ['symbol' => Instrument::GoldOunce,  'side' => Side::Last, 'unit' => UpstreamUnit::None],
];
```

### 🔴 Tale ye shomare 1: unit **per-instrument** ast، na per-provider

`estjt` «انس طلا» ro **USD** mide va baghi 8 ta ro **Toman**. Agar `upstream_unit=toman` e provider-level ro ru ons e'mal koni → `$39,005` ke onghadr bavar-pazir hast ke az zir e cheshm rad beshe va **har point e Influx ro masmum kone**.
→ Unit **tu entry e map** mineshine. `providers.upstream_unit` faghat default ast vaghti entry chizi nagofte.

### Tale ye 2: label e Farsi ro fold kon
Agar map ba label e Farsi ast (HTML scrape)، **hargez** ba string e kham match nakon:
```php
$key = PersianText::fold($label);   // ي→ی، ك→ک، hazf e ZWNJ/tatweel، ۱۸→18
```
«طلای» ZWNJ dare، «۱۸» Farsi ast. **`Str::slug()` mamnu'** — Farsi ro kolan mindaze.
Label e unmapped → `Log::channel('pricing')->info('pricing.unmapped', [...])` ba byte haye kham، ta taghir e upstream tu yek tick ma'lum she.

### Tale ye 3: separator
Policy e `NumberParser` **har-source-jodagane** ast، na heuristic e global:

| Hālat | `$thousands` | `$decimal` |
|---|---|---|
| adad e sahih ba comma | `[',']` | `null` |
| ashari ba comma + noghte | `[',']` | `'.'` |
| Farsi ba `٫` (U+066B) | `['٫','٬',',']` | `null` |

`٫` *ma'nan* separator e **ashar** e Arabi-e vali site haye Irani onvan e **hezargan** estefade mikonan. Ghanun e global e «`٫` = ashar» `۱٫۲۳۴٫۵۶۷` ro mikone `1.234` → khata ye **۱,۰۰۰,۰۰۰ barabari**.

### Tale ye 4: HTML ro ba DOM parse kon
```php
$dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
$rows = (new DOMXPath($dom))->query('//tr[td[@class="name"]]');
```
**Na regex** — markup e WordPress avaz mishe.

### Tale ye 5: freshness
Har payload e upstream `ts` dare? → `Carbon::parse($ts, 'Asia/Tehran')->utc()` (aksar **bedun e offset**-and).
`ts` nadare? → `md5` e body tu `last_payload_hash`؛ bi-taghir >10 daghighe tu sa'at e bazaar = `degraded`، **vali tick edame mide**.
⚠️ tgju 963 symbol mide ke **faghat ~389 tash taze-an** (`zinc` ts=2021، `geram18_buy` ts=2018). Filter ru `ts` **ejbari**.

## Gam 4 — fixture e **vaghei** (ejbari)

```bash
curl -sS … '<URL>' -o tests/Fixtures/<provider>-<endpoint>.json
```
Fixture e dast-saz **mamnu'** — bug haye vaghei (whitespace، `\t`، ZWNJ، separator) faghat tu payload e vaghei hastan.

## Gam 5 — test baraye **har instrument** (ejbari)

```php
public function test_parses_every_instrument(): void
{
    $quotes = (new WallgoldQuoteSource([...]))->fetchFrom(
        json_decode(file_get_contents(__DIR__.'/../Fixtures/wallgold.json'), true)
    );
    $byKey = collect($quotes)->keyBy(fn ($q) => $q->symbol->value.':'.$q->side->value);

    // ⬅️ meghdar e DAGHIGH e minor — inja jaye bug e 10× ast
    $this->assertSame(266_818_000, $byKey['xau-18k-g-irr:last']->minor);
    $this->assertSame(QuoteCurrency::IRR, $byKey['xau-18k-g-irr:last']->quote);
}
```
**Sanity**: meghdar ro ba tgju ye hamun lahze moghayese kon. 18k e gram bayad hamhodud e `266,400,000` rial bashe. Agar `26,681,800` dar avordi، toman ro rial hesab kardi.

## Gam 6 — spread e default

Radif e `*` (wildcard) tu `provider_spreads` ba `buy_value = 0`, `sell_value = 0`. Admin ba'dan tu panel tanzim mikone.

## Gam 7 — ejra va check

```bash
docker compose -f docker-compose.local.yml exec octane php artisan pricing:tick
docker compose -f docker-compose.local.yml exec octane php artisan test --filter=Wallgold
```
Ba'd tu panel: `/dashboard/providers` → `health_state` سبز، `last_symbol_count` motabegh e entezar، `last_rejected_count` sefr.
**Tazegi**: `TickMonitor` ro bebin — agar symbol-i `stale` ya `out_of_band` khorde، ghabl az `is_active=true` kardan hall esh kon.

Ba'd az sabz shodan → `is_active = true`.

## Checklist

- [ ] Unit **tu map** ast na faghat ru provider
- [ ] Label haye Farsi `fold()` shodan
- [ ] Separator policy e in source moshakhas ast
- [ ] HTML ba DOM parse shode، na regex
- [ ] Fixture e **vaghei** tu `tests/Fixtures/`
- [ ] Test baraye **har** instrument ba meghdar e daghigh e minor
- [ ] Meghdar ba tgju moghayese shod (check e 10×)
- [ ] `pricing:tick` ejra shod va `last_rejected_count` sefr bud
- [ ] `is_active` faghat ba'd az sabz shodan roshan shod
