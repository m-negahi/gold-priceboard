---
name: pricing-architecture
description: Ghanun haye me'mari ye module e Pricing — marz e Domain/Application/Infrastructure, Port & Adapter, Action/Query/Dto/Enum. Invoke kon ghabl az HAR taghiri tu app/Modules/Pricing/** ya vaghti module e jadid misazi.
---

# Me'mari ye Pricing — Ports & Adapters + CQRS-lite

## Chera in shekl

Module e `Pricing` e `~/Projects/hg_website` kar mikone vali driver esh mostaghim Redis mikhune، command esh chagh ast، va gheymat ro `unsignedBigInteger` e rial negah midare (ke `4140.19` ye ons toosh ja nemishe). Inja un eshtebah ha tekrar nemishan.

**Hadaf e asli**: kol e mantegh e gheymat bedun e Docker/Redis/Influx/PG test beshe.

## Se laye

```
Domain/          ← PURE PHP. hich Facade, hich Eloquent, hich Http::, hich config().
Application/     ← use-case ha. Domain ro seda mizane, ba Port ha harf mizane.
Infrastructure/  ← hame ye chiz e "biruni". tanha jayi ke Eloquent/Redis/Influx/Http hast.
```

### Jadval e «che laye-i mitune chi ro seda bezane»

| Laye | Mojaz be seda zadan |
|---|---|
| `Domain/ValueObjects`, `Domain/Enums` | faghat hamdige |
| `Domain/Services` | ValueObject، Enum |
| `Domain/Ports` | faghat Dto va ValueObject (interface e khali) |
| `Application/Actions` (write) | Port، Domain\Services، Dto، Action e digar، Query |
| `Application/Queries` (read) | Port، Dto — **faghat khundan، bedun e side effect** |
| `Application/Pipeline/Stages` | Port، Domain\Services، Dto |
| `Infrastructure/*` | har chi (Eloquent، Redis، Http، SDK) — **implement e Port** |
| `Livewire` / `Controller` / `Blade` | **faghat Action va Query** |

```php
// ❌ HARGEZ tu Livewire/Controller/Blade
Provider::where('is_active', true)->get();

// ✅
$this->providerQuery->active();
$this->providerAction->update($dto);
```

## Port ha

| Port | Adapter e fe'li | Ya'ni |
|---|---|---|
| `QuoteSourcePort` | `TgjuQuoteSource`, `EstjtQuoteSource`, `MockQuoteSource` | gereftan e gheymat az yek manba' e biruni |
| `SnapshotStorePort` | `EloquentSnapshotStore` | akharin gheymat (Postgres) |
| `HotCachePort` | `RedisHotCache` | gheymat e dagh |
| `TimeSeriesPort` | `InfluxTimeSeries` | tick + candle |
| `ClockPort` | `SystemClock` / `FrozenClock` | zaman — ta test determinist bashe |
| `UpstreamLogPort` | `RedisUpstreamCallLog` | daftarche ye call haye MA be provider ha (P4-14) — `push` hargez throw nemikone |

Port e jadid vaghti lazem ast ke `Application/` bekhad ba donya ye birun harf bezane. Marahel:
1. Interface tu `Domain/Ports/` (faghat Dto/VO tu signature).
2. Adapter tu `Infrastructure/`.
3. Fake tu `tests/Support/Fakes/`.
4. Bind tu `PricingServiceProvider`.
5. **Yek test ke bind ro assert kone** (hg_website yek bind e shekaste dasht ke kasi nafahmid).

## Ghanun haye sakht

1. **`Domain/` hich chiz e Laravel nemibine.** Agar `use Illuminate\…` tu `Domain/` didi، ghalat ast. Config be onvan e **argument** pass mishe، na `config()`.
2. **`QuoteSource` faghat motarjem ast.** Vorudi HTTP، khoruji `RawQuoteDto[]`. **Hich cache، hich DB، hich enrich.** (hg_website in ro shekast — `PwaDriver::enrich()` Redis minevisht.)
3. **Action = yek use-case e neveshtan. Query = yek use-case e khundan.** Yek Action do kar nemikone.
4. **Command nazok.** Faghat yek Action seda mizane va natije ro chap mikone. Hich mantegh.
5. **Eloquent faghat tu `Infrastructure/Persistence`.** CRUD e admin mitune rahat Eloquent bezane (`ProviderAction`)، vali **pipeline e gheymat faghat ba Port ha**.
6. `declare(strict_types=1)` + `final readonly` hame ja — be joz Model (Eloquent lazem dare mutable bashe) va Livewire component (property e public dare).
7. **Comment ha Finglish.**

## Pipeline — policy e shekast

Har Stage e'lam mikone `critical` ya `isolated` ast. In jaye try/catch haye parakande ro migire:

```php
// Application/Pipeline/Stages/PersistStage.php
public function policy(): StagePolicy { return StagePolicy::Isolated; }
```

- `Critical` → shekast = tick motevaghef.
- `Isolated` → shekast = `Log::warning` + sabt tu `TickReportDto` + **edame**.

**Hich chizi tu `Persist` nabayad critical bashe.** Influx down bashe، Redis va Postgres bayad kar konan.

`Log::warning` na `report()` — vagarna tu yek ghat'i ye yek-sa'ate Sentry ba 240 event e yeksan flood mishe.

## Gheymat — hargez float

```php
// Domain/ValueObjects/Price.php
new Price(minor: 266_400_000, quote: QuoteCurrency::IRR);   // 266,400,000 rial
new Price(minor: 41_401_900,  quote: QuoteCurrency::USD);   // 4140.19 dollar (scale 4)
```
`minor = major × 10^scale`. `IRR → scale 0`, `USD → scale 4`.
**Int e kham hargez az `Price` birun nemire.** `plus()` ru cross-currency `throw` mikone.

## Test

Har Port fake dare → `tests/Unit/Pricing/` kol e pipeline ro **bedun e Docker** mizane:

```php
$pipeline = new TickPipeline(
    sources:  [new FakeQuoteSource([$quote])],
    store:    new InMemorySnapshotStore(),
    cache:    new ArrayHotCache(),
    series:   new FakeTimeSeries(),
    clock:    new FrozenClock('2026-10-04 12:00:00'),
);
```
Agar baraye test kardan e yek mantegh majbur shodi DB bala biari، ya'ni un mantegh tu laye ye ghalat neshaste.

## Checklist e ghabl az commit

- [ ] `Domain/` hich `use Illuminate\…` nadare
- [ ] `QuoteSource` hich cache/DB nemibine
- [ ] Har Port e jadid: interface + adapter + fake + bind + **test e bind**
- [ ] Comment ha Finglish
- [ ] `vendor/bin/pint --dirty`
