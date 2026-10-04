# Architecture — PriceBoard

Amigh tar az `app/Modules/CLAUDE.md`. Inja **chera**-ye tasmim ha neveshte shode، ta kasi ba hoosn e niyat chizi ro «sade» nakone ke dalil dasht.

---

## 1. Chera Ports & Adapters

Projeh ye marja' (`~/Projects/hg_website`) yek module e `Pricing` dare ke kar mikone vali:

| Moshkel | Natije ye amali |
|---|---|
| `PriceProvider` ham `fetch()` dasht ham `series()` ham `name()` | har driver e jadid majbur bud `series()` ro ham piade kone، hatta vaghti upstream tarikhche nadasht |
| `PwaDriver::enrich()` mostaghim Redis mikhund va minevesht | driver ghabl e test bedun e Redis nabud، va «motarjem» be «store» tabdil shode bud |
| `PricingTickCommand` 8 ta mas'uliat dasht | har bug e taab-avari bayad tu un yek file peyda mishod |
| `price_quotes.symbol` unique bud | bo'd e provider aslan vojud nadasht — baraye site e **moghayese** ghabl e estefade |

Ports & Adapters har chahar ta ro hall mikone: `Domain` nemidune Influx vojud dare، `Application` faghat ba interface harf mizane، va `Infrastructure` tanha jayi-e ke `Http::`/Eloquent/Redis hast.

**Mi'yar e amali**: agar baraye test kardan e yek mantegh majbur shodi Docker bala biari، un mantegh tu laye ye ghalat neshaste.

---

## 2. Se store، se mas'uliat

```
                   ┌──────────────┐
   upstream  ──▶   │  TickPipeline │
                   └───────┬──────┘
                           │  (tartib: Redis → PG → Influx)
          ┌────────────────┼────────────────┐
          ▼                ▼                ▼
    ┌──────────┐    ┌────────────┐   ┌─────────────┐
    │  Redis   │    │ PostgreSQL │   │  InfluxDB   │
    │ (prices) │    │            │   │             │
    │ gheymat  │    │ AKHARIN    │   │ HAME ye     │
    │ e dagh   │    │ gheymat +  │   │ tick +      │
    │ bi-TTL   │    │ relational │   │ candle      │
    └────┬─────┘    └─────┬──────┘   └──────┬──────┘
         │                │                 │
      board/jadval   backfill e Redis     chart
```

| Store | Chi negah midare | Agar bioftad |
|---|---|---|
| `redis-prices` | akharin gheymat (board az inja mikhune) | `BoardQuery` mire ru Postgres — board **durost** mimune، faghat kond tar |
| PostgreSQL | akharin gheymat + hame ye data ye relational | polling edame mide (lista ye provider az `pb:providers:last`)، board **zende** mimune |
| InfluxDB | hame ye tick + candle | board salem ast؛ chart `:last` e cache ro neshun mide ba flag e `degraded`. Tick ha tu spool jam' mishan va ba'd replay mishan → **hich gap** |

### Chera Redis aval، Postgres dovom
Esteddal e «Postgres aval baraye durability» ghabel e defa' ast va tu cadence e 15 saniye-i tafavot e latency bi-ma'ni-e. Dalil e vaghei ye in tartib **asymmetry e shekast** ast:
- Postgres-aval + PG down → Redis be-rooz nemishe → **board yakh mizane**.
- Redis-aval + PG down → board **kamelan zende** mimune.

**Invariant e test-shodani**: Postgres hargez bishtar az yek tick aghab e Redis nist، va Redis hamishe az Postgres backfill-pazir ast.

### 🔴 Chera do ta Redis
`redis` e omumi `--maxmemory-policy allkeys-lru` dare. Tu feshar e memory Redis **har** kelidi ro evict mikone — shamel e kelid haye gheymat — va khodesh ro kamelan salem gozaresh mide. Natije: board bedun e hich error khali mishe.
`hg_website` in moshkel ro nadasht chun unja Redis faghat **cache** bud va Postgres posht esh bud. Inja Redis **store e dagh** ast.
→ `redis-prices` e joda ba `noeviction` + kelid haye **bedun e TTL**.

---

## 3. Gheymat: `bigint` e minor unit + `scale`

| Gozine | Hokm |
|---|---|
| `unsignedBigInteger` e rial (entekhab e hg_website) | ru `xau-oz-usd = 4140.19` va `xcu-lb-usd = 6.492` mishkane |
| `decimal(24,6)` hame ja | daghigh، vali PHP string bar migardune، har arithmetic bcmath mikhahe، va `(float)` saket khata ro bar migardune |
| **`bigint` e minor + `scale` ru har radif** | ✅ |

`minor = major × 10^scale` · `IRR → scale 0` · `USD → scale 4`.
`scale` **ru har radif va tu har payload e Redis** zakhire mishe، ta hich reader-i baraye tafsir e adad be enum niaz nadashte bashe.
Headroom e `bigint`: 9.2e18 → 9.2e14 dollar، 9.2e18 rial. Har do kheyli emn.

**Shart e in entekhab**: int e kham hargez az `Price` birun nemire. `plus()` ru cross-currency `throw` mikone.
**Agar in discipline negah dashte nashe** → `decimal(24,6)` + bcmath. Kond tar، vali ghabl e eshtebah nist.
**Ghati kardan e do ta mamnu'** — failure mode esh saket ast.

---

## 4. Spread ru read، na write

Chahar dalil (be tartib e ghodrat):

1. **Spread e bake-shode yek pele ye ja'li tu chart mizare.** Admin sa'at 14:00 spread ro az +0.5% be +0.8% mibare → candle haye ghabl ba spread e ghadim، ba'd ba jadid → yek discontinuity e 0.3% ke **harekat e gheymat nist**. Kol e ma'ni ye chart e teknikal in ast ke shekl esh ma'ni dare؛ MA/RSI/Bollinger un pele ro **signal e vaghei** mikhunan.
2. **Tarikhche ye Influx ghabl e re-spread nist.** Postgres yek radif dare pas asun ast، vali milyun ha point e tarikhi rah e arzun baraye bazneveshtan nadaran — va ba'd az dur rikhtan e meghdar e kham، **hich rah e durosti** nadaran.
3. **Meghdar e kham tanha vaghe'iyat e ghabl e audit ast.** Spread tanzim e kasb-o-kar ast. Yek `+50` ke eshtebah tu field e `percent` type shode mishe **rollback e config**، na hadese ye az-dast-raftan e data.
4. **Hazine ye read sefr ast** — map ~50 radif، `SpreadPricer` e `final readonly` e bi-halat.

### Do ta'ahod ke az in entekhab miad
- **Chart hamun spread e board ro migire.** Vagarna `close` e akharin candle ≠ gheymat e board، va har karbar un ro bug gozaresh mikone. `applySeries()` ru har chahar ta ye OHLC e'mal mishe؛ har do mode monotone-and pas `high ≥ low` khod-be-khod hefz mishe.
- **`spread_version` e monotonic** (`INCR pb:spreads:v`، mirror tu settings)، tu transaction e `ProviderSpreadAction` bump mishe، va tu **hame ye** kelid e cache e gheymat-dar + har ETag miad. Bedun esh board va chart be andaze ye tulani tarin TTL ba ham ekhtelaf daran.

### Se ghanun e `SpreadPricer`
1. `change_pct` va `direction` **hargez** tanzim nemishan — spread e darsadi ru darsad e taghir riyazi-an neutral ast، va spread e meghdari be close e ghabli niaz dare ke tu payload nist.
2. `day_high`/`day_low` ba delta ye **sell** shift mishan va ba'd clamp mishan، ta gheymat e zende az band birun nazane.
3. Spread-i ke gheymat ro be ≤ 0 bebare → fallback be `raw`، **na sefr**. Yek tanzim e kharab nabayad service ro bokoshe.

---

## 5. Tick pipeline

```
pricing:tick (container e price-worker، halghe ye grid-aligned e 15s)
│
├─ 0 Lock        Cache::lock('pricing:tick', 14)
│                Redis down → BEDUN e lock edame (paeen ro bebin)
├─ 1 TickTime    intdiv(time(),15)*15 — YEK timestamp baraye kol e tick
├─ 2 Providers   Redis(30s) → PG → pb:providers:last (bi-TTL)
├─ 3 Fetch       Http::pool — har provider try/catch e khodesh
├─ 4 Normalize   parse + unit + symbol map   (dakhel e Source)
├─ 5 Validate    staleness + PriceSanity
├─ 6 Persist     Redis → Postgres → Influx   (har kodum jodagane isolated)
└─ 7 Publish     warm e payload
```

### Policy e shekast
Har Stage e'lam mikone `Critical` ya `Isolated` ast — **jaye try/catch haye parakande**.

| Mitune tick ro motevaghef kone | Hargez nabayad |
|---|---|
| lock e gerefte shode (amdi، `SUCCESS` bar migardune) | shekast e HTTP/parse e **har** provider |
| sefr provider e fa'al (amdi) | shekast e neveshtan e Redis |
| | shekast e upsert e Postgres |
| | shekast e neveshtan e Influx (→ spool) |
| | shekast e neveshtan e health ya warm e cache |
| | dar dastres nabudan e backend e lock (→ bedun e lock) |

Hame `Log::warning`، **na `report()`** — vagarna tu yek ghat'i ye yek-sa'ate Sentry ba 240 event e yeksan flood mishe.

### Chera lock «faghat» performance ast
Hame ye sink ha idempotent-and:

| Sink | Mekanism |
|---|---|
| Redis | akharin write barande |
| `price_snapshots` | `ON CONFLICT (provider_id, symbol, side) DO UPDATE` |
| `price_changes` | faghat vaghti `raw_minor` ba snapshot fargh dare insert mishe |
| Influx | hoviyat e point = `(measurement, tagset, field, timestamp e floor-shode)` → overwrite |
| Task haye Influx | lookback ≥ 2 bar، `to()` upsert mikone |

Pas ejra ye dobare ye yek tick bi-zarar ast → vaghti Redis (backend e lock) down ast، **bedun e lock edame midim** jaye inke polling ro motevaghef konim.

---

## 6. Staleness — chera per-instrument

tgju 963 symbol mide ke **faghat ~389 tash taze-an**: `zinc` ts=2021-06-28، `geram18_buy` ts=2018-05-29.

Yek threshold e global ya gheymat e 2021 ro rah mide، ya instrument haye kond ro mindaze:

| Instrument | Freshness e manteghi |
|---|---|
| `usd-irr`, `xau-oz-usd` | ~180s (taghriban 24/5 moamele mishe) |
| `xau-18k-g-irr`, sekke ha | ~900s (faghat sa'at e bazaar) |
| `xag-925-g-irr` | ~1800s (mitune yek sa'at nakhore) |

Tartib e resolve: `provider_spreads.freshness_seconds` ?? `Instrument::freshnessSeconds()` ?? `providers.freshness_seconds`.

**Bazaar e baste yek feature ast، na bug.** Shab/jom'e/ta'til `ts` e tgju ghofl mishe → hich chiz neveshte nemishe → yek gap e vaghei tu chart، daghighan unja ke bazaar baste bude. **Forward-fill nakon** — chart e teknikal bayad rast begē.

estjt timestamp **nadare** → `md5` e HTML (`last_payload_hash`). Bi-taghir baraye >10 daghighe tu sa'at e bazaar → `health_state = degraded` + hoshdar، **vali tick edame mide**: gheymat e bi-taghir tu bazaar e aram tabi'i-e va yek candle e saf sadegh tar az yek gap ast.

---

## 7. `PriceSanity` — darvaze

Do check e mostaghel:
1. **Band e motlagh e har instrument** — khata ye 10× e toman/rial، soton e eshtebah e HTML، flip e arz.
2. **Saghf e enheraf e tick-be-tick** — 10% baraye tala ye IRR، 5% baraye USD.

Meghdar e rad-shode **hich ja neveshte nemishe** — na Redis، na Postgres، na Influx. Chera: yek parse e bad ke be `candles_1d` berese yek **dorugh e daemi ye ghabl e royat** ast، va pak kardanesh `delete` e predicate-i ru Influx + cascade e re-run e task mikhahe. Rad kardan tu darvaze 20 khat ast.

⚠️ Yek gap e vaghei ye 12% (devaluation، khabar e siasi) in guard ro trip mikone. Rah: **faghat avvalin breach rad mishe؛ agar tick e ba'di ta'eed kard، paziresh mishe** — «do outlier e mote'ali vaghei-and، yeki parse error ast».

---

## 8. Octane safety

Octane process ro zende negah midare — pas har chizi ke **halat** dare bayn e request ha nasht mikone.

| Chi | Bind | Chera |
|---|---|---|
| `PriceManager`، `InfluxTimeSeries` | ✅ `singleton` | `final readonly`، faghat config e immutable، tu har method client e **taze** misazan |
| `BoardPayload` (memo e har-request) | `scoped()` — **faghat vaghti `! runningInConsole()`** | vagarna worker e long-running gheymat e bayat ro memo mikone |
| `InfluxDB2\Client` / `WriteApi` / `QueryApi` | ❌ **hargez** | `WriteApi` e batching yek buffer e mutable ast: beyn e request ha mimune، mitune tavasot e request e bi-rabt flush she، memory leak mikone، va ba recycle e worker **saket** khali mishe |
| Builder e Eloquent tu property | ❌ | `$this->query()` hamishe Builder e taze |

**Write e Influx**: faghat tu worker e CLI، faghat `SYNCHRONOUS`، client tu khod e method sakhte va tu `finally` `close()` mishe.
**Read e Influx**: **aslan SDK estefade nemishe** — `Http::withBody($flux, 'application/vnd.flux')` + `FluxCsv::toCandles()`. Yek class e kamel e khatar ro az request path hazf mikone، yek HTTP stack baraye kol e app negah midare، va mostaghim be shape e KLineCharts parse mikone jaye alloc kardan e `FluxTable`/`FluxRecord` (series e `h1` e yek-sale = 8,760 × 5 ≈ 44k record).

**Cache**: hargez model e hydrate-shode cache nakon — zire Octane+Redis `__PHP_Incomplete_Class` mide. Ya array e kham cache kon (tarjih)، ya ba `setRawAttributes($attrs, true)` rehydrate kon.

---

## 9. Chiz hayi ke amdan **nakardim**

| Chi | Chera na |
|---|---|
| InfluxDB 3 | Core query ro be ~72 saat (432 file e Parquet) mahdud mikone؛ data mimune vali ghabl e query nist. Tarikhche Enterprise mikhahe ke baraye estefade ye tejari majani nist |
| TimescaleDB / QuestDB / ClickHouse | sahebe projeh Influx ro entekhab kard |
| Filament | mostaghim ba Eloquent kar mikone — ba ghanun e «hich Eloquent tu laye ye UI» tanaghoz dare |
| Reverb / WebSocket | polling e 15s kafi-e va yek service kamtar |
| `@klinecharts/pro` | 0.1.1 ast va `solid-js` miare — ba Alpine/Livewire va token haye ma mijange. Core khodesh indicator va overlay dare |
| Zakhire kardan e `buy`/`sell` tu DB | bakhsh e 4 |
| Backfill e tarikhche az biurn | sahebe projeh «az tick haye khodemun» ro entekhab kard. Opt-in mune: `api.tgju.org/v1/market/indicator/summary-table-data/{symbol}` |
| Font e CDN | ru host e Iran ghabel e etminan nist — font local ast |
