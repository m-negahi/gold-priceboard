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
| Redis DB2 (`prices`) | akharin gheymat (board az inja mikhune) | `BoardQuery` mire ru Postgres — board **durost** mimune، faghat kond tar |
| PostgreSQL | akharin gheymat + hame ye data ye relational | polling edame mide (lista ye provider az `pb:providers:last`)، board **zende** mimune |
| InfluxDB | hame ye tick + candle | board salem ast؛ chart `:last` e cache ro neshun mide ba flag e `degraded`. Tick ha tu spool jam' mishan va ba'd replay mishan → **hich gap** |

### Chera Redis aval، Postgres dovom
Esteddal e «Postgres aval baraye durability» ghabel e defa' ast va tu cadence e 15 saniye-i tafavot e latency bi-ma'ni-e. Dalil e vaghei ye in tartib **asymmetry e shekast** ast:
- Postgres-aval + PG down → Redis be-rooz nemishe → **board yakh mizane**.
- Redis-aval + PG down → board **kamelan zende** mimune.

**Invariant e test-shodani**: Postgres hargez bishtar az yek tick aghab e Redis nist، va Redis hamishe az Postgres backfill-pazir ast.

### Redis e vahed, connection haye joda
Yek container `redis:8-alpine` ba DB0 baraye OTP/session/queue, DB1 baraye cache va DB2 baraye prices. Policy `noeviction` jelo e hazf e saket e gheymat/session/queue ro migire; AOF `everysec` va volume e paydar. Maxmemory e pish-farz 512MB ba `REDIS_MAXMEMORY` ghabel e tanzim ast.

Logical DB faghat namespace/isolation e amaliati mide, na memory ya availability e joda. Tu OOM, write fail mishe; memory, queue backlog va failed jobs bayad monitor shan. Ghat'i e Redis bar board PG fallback dare; OTP/session/queue hamzaman unavailable mishan. Test ha be DB13/14/15 majbur mishan va RedisManager ba config e test az no sakhte mishe ta flushdb be dev nakhore.

Enteghal e dev: price-worker stop, 7 kelid ba DUMP/RESTORE va TTL be DB2 enteghal yaft, mohtava ba compare e canonical taeed shod; source dast nakhord. Ba'd az taeed, container e ghadimi hazf shod; volume ha hefz shodan. `.env` taghir nakard; `REDIS_PRICES_*` dige masraf nemishan, kelid e jadid `REDIS_PRICE_DATABASE=2` ast.

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
├─ 0 Lock         Cache::lock('pricing:tick', 14)            (IngestTickAction)
│                 Redis down → BEDUN e lock edame (paeen ro bebin)
├─ 1 TickTime     intdiv(time(),15)*15 — YEK timestamp baraye kol e tick
├─ 2 Providers    Redis(30s) → PG → pb:providers:last (bi-TTL)
├─ 3 LoadPrevious SnapshotStorePort::all()  🔴 TANHA marhale ye CRITICAL (TickPipeline::run)
├─ 4 Fetch        Http::pool — har provider try/catch e khodesh + retry e gozara    (FetchStage)
│                 Normalize (parse + unit + symbol map) DAKHEL e har QuoteSource ast
├─ 5 Validate     staleness + PriceSanity + check e cross-provider                 (ValidateStage)
├─ 6 Enrich       band e rooz، jahat، darsad e taghir — BA'D az Validate           (EnrichStage)
├─ 7 Persist      Redis → Postgres → Influx (har sink jodagane isolated)            (PersistStage)
├─ 8 ManualBackup raw e accepted e hamin tick → manual_prices (isolated)            (ManualBackupStage)
└─ 9 Publish      version + zarabān e nevisande                                     (PublishStage)
```

Tartib e stage ha tu `app/Modules/Pricing/Providers/PricingServiceProvider.php` (bind e `TickPipeline`) sabt shode؛ file haye stage tu `app/Modules/Pricing/Application/Pipeline/Stages/`. Command e `pricing:tick` faghat `IngestTickAction::execute()` ro seda mizane va `TickReportDto` ro chap mikone.

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


## 10. Module ha va marz e masouliat

13 module tu `app/Modules/` (yek khat masouliat + class haye kelidi — hame ye esm ha ba code check shodan):

| Module | Masouliat (yek khat) | Class haye kelidi | ServiceProvider |
|---|---|---|---|
| `Common` | zirbana ye moshtarak: `BaseAction`/`BaseQuery`/`TableQuery`، helper ha (`Money`، `Jalali`، `PhoneHelper`، `StringHelper`، `SvgSanitizer`)، middleware haye global (`TrustProxies`، `Normalize*`، `LatinizeDigits`، `SecurityHeaders`)، `QueueName` | `Helpers/Money.php`، `Enums/QueueName.php` | `ModuleServiceProvider` (class e abstract e paye؛ tu `bootstrap/providers.php` sabt NIST — 4 provider e module azash ers mibaran) |
| `Pricing` | kol e motor e gheymat: QuoteSource ha، tick pipeline، sanity/trust-anchor، spread ru read، manual/priorities، Redis/PG/Influx، board/TV/chart e omumi | `IngestTickAction`، `TickPipeline`، `SourceManager`، `BoardQuery`، `PublicBoardQuery`، `TvBoardQuery`، `CandleQuery`/`ChartQuery`، `EffectivePriceQuery` | `PricingServiceProvider` |
| `Auth` | login ba mobile + OTP (Kavenegar/Fake)، user e active/disabled، profile e haghighi/hoghoghi + audit، `EnsureAdmin`/`EnsureActiveUser` | `SendOtpAction`، `VerifyOtpAction`، `RedisOtpStore`، `SmsManager`، `UserQuery`، `UserAdminAction` | `AuthServiceProvider` |
| `Access` | RBAC: role/permission (`PermissionCatalog`)، profile e admin، gate e route/navigation، audit e dastresi | `AccessQuery`، `ManageAccessAction`، `Domain/PermissionCatalog` | `AccessServiceProvider` |
| `Api` | API e foroushi: HMAC auth، idempotency/charge e har call، plan/subscription/quota، site approval، usage e ruzane | `ApiHmacAuth`، `IdempotentApiCall`، `ChargeApiCall`، `LogApiRequest`، `PurchasePlanAction`، `RollupApiUsageAction`، `HmacSignature` | `ApiServiceProvider` |
| `Wallet` | kif e pool: balance e cache-shode + `wallet_transactions` e append-only + idempotency e `(wallet, reason, reference)` | `WalletAction`، `AdjustWalletAction`، `ReverseWalletAdjustmentAction`، `WalletQuery` | — (Action/Query e sade؛ autowire) |
| `Billing` | pardakht: `PaymentGatewayPort` + `ZibalGateway`، topup e wallet، sodur e factor e immutable، safhe haye `/account` | `TopupPaymentAction`، `IssueInvoiceAction`، `CustomerBillingQuery`، `Config/billing.php` | `BillingServiceProvider` (bind e gateway) |
| `Finance` | daftar e kol: journal e do-tarafe ru har tarakonesh e wallet، settlement e dargah، gozaresh haye admin | `PostWalletJournalAction`، `SettleGatewayPaymentAction`، `FinanceLedgerQuery`، `FinanceMonthlyReportQuery`، `FinanceInvoiceQuery` | — (autowire) |
| `Kyc` | onboarding + estelam e hoviat (Zohal) posht e `IdentityVerificationPort`؛ job e `RunIdentityChecks`؛ tasmim e admin | `SubmitKycRequestAction`، `QueueIdentityChecksAction`، `DecideKycAction`، `ZohalIdentityVerification` | `KycServiceProvider` |
| `Ticket` | ticket e moshtari/admin: status/message/event، peyvast e private ba signed URL، SMS e status tu queue | `OpenTicketAction`، `ReplyTicketAction`، `TransitionTicketAction`، `TicketQuery`، `Jobs/SendTicketStatus` | `TicketServiceProvider` |
| `Media` | ketabkhane ye file: upload/virayesh/hazf، derivative e tasvir، `media_usages` (guard e hazf) | `UploadMediaAction`، `MediaQuery`، `Infrastructure/Support/ImageProcessor` | `MediaServiceProvider` |
| `Seo` | metadata ye omumi: `seo_meta` + `SeoResolver`، JSON-LD، sitemap، `robots.txt` e dynamic، editor e panel | `SeoResolver`، `SitemapQuery`، `Domain/Services/JsonLd`، `RobotsTxt` | `SeoServiceProvider` |
| `Panel` | controller/view/navigation e `/dashboard` (hich mantegh e domain — faghat Query/Action e module haye dige ro seda mizane) | `Support/Navigation`، `Queries/DashboardQuery`، controller ha tu `Http/Controllers/` | `PanelServiceProvider` |

Action ha masir e write، Query ha masir e read-and. Model ha dar `Infrastructure/Persistence/Models`؛ adapter e source dar `Pricing/Infrastructure/Sources`؛ Media Actions/Queries/Dto dar `Application`، image processor dar `Infrastructure/Support`. Dependency e gateway dar `BillingServiceProvider` bind mishe. Provider ha dasti dar `bootstrap/providers.php` sabt mishan (tartib: `App`، `Horizon`، `Auth`، `Access`، `Api`، `Billing`، `Media`، `Pricing`، `Panel`، `Seo`، `Ticket`، `Kyc`). Route ha: `routes/web.php` (omumi + panel + account ticket)، `routes/api.php` (`/v1/*`)، `routes/billing.php` (mali/plan/site e panel + `/account/*`)، `routes/identity.php` (user edit + ticket e KYC e panel)، `routes/console.php` (scheduler).

## 11. Dastresi va hoviat

Admin e ghadimi bedun e `admin_access_profiles` full-access ast. Vaghti profile sarih sabt shod, `full_access=false` + role e khali yaani hich permission. Permission ha `view/manage` dar `PermissionCatalog`; `manage` ijaze e read e hamun bakhsh ro ham mide. Route e nashenas baraye admin e mahdood denied ast. Horizon/Telescope ham permission e monitoring daran; list/read ba delete/retry/toggle joda ast.

Har taghir e role/profile/status admin, admin ha ro ba tartib e ID lock va actor ro dobare authorize mikone. Hadaf: stale request natune dastresi e akharin admin e faal e `access.manage` ro hazf kone. Taghir e shomare/name e admin ham `access.manage` mikhad. Navigation va control ha permission-aware-and; backend ham mostaghel check mikone. Private ticket attachment faghat owner ya admin e `tickets.view`, hamrah e signed URL.

Hoviat e haghighi/hoghoghi dar `identity_profile`; profile e taeed-shode e factor dar `invoice_profile`. Profile edit audit dare. `IdentityVerificationPort` natije e passed/failed/warning mide; token e natanzeem-shode warning ast. Job baraye har submission/run claim e atomic dare; rerun sarih e admin run e jadid misaze. KYC approval dar transaction sath/profile factor ro snapshot mikone. Madarek private-and. SMS e status tu queue ba template e tanzim-shode ersal mishe.

## 12. Wallet, eshterak, pardakht va daftar kol

Mablagh hameja rial e integer ast. Gheymat e marja e USD e jahani USD mimune. Display/parser e rial `Money` hich float ya zarib e toman nadare; upstream toman tu Source normalize mishe.

Kharid e plan client/owner/status ro check, wallet ro lock va price ro debit mikone. Subscription snapshot e duration/quota/rate dare; edit e plan eshterak e ghabli ro avaz nemikone. Sahmie e kol va rooz aval masraf mishe; bishtar az sahmie ba rate e eshterak PAYG ast. Rate e sefr dar overage yaani 429. Eshterak e monghazi → PAYG e pish-farz. Wallet e blocked hata call e included ro rad mikone. Request e fail/refund baraye bargasht e quota/balance talash mikone. BUG e baz dar checkpoint: agar refund e DB shekast bokhorad, compensation e paydar nadarim va header mitavanad cost e sefr e eshtebah neshan dahad.

Topup: request-key e karbar + track_id e gateway unique; callback faghat ba verify e server-to-server, amount e daghigh va result e motabar wallet ro credit mikone. Payment row lock shode; callback e tekrari credit e jadid nemisaze. Gateway unconfigured khata e vazeh mide. Zibal credential az `ZIBAL_MERCHANT`; test ha fake-and. Invoice faghat ba `invoice_verified` va invoice_profile (dar checkpoint e tavaqof, mapping e yekdast e approved profile hanuz pending ast): snapshot e kharidar, source e pardakht/eshterak, mablagh, shomare e sequence. Snapshot immutable ast. Factor e dakheli/printable ast; enteghal be samane e modian ya rule e maliat piade nashode.

| Code e hesab | Masraf |
|---|---|
| 1000 | Bank; settlement ba reference e bank |
| 1100 | Vojuh e dar rah e dargah |
| 2100 | Bedehi e wallet e moshtarian |
| 3000 | Traz e opening balance e data e ghabli |
| 3900 | Hesab e vaset e eslahat e admin |
| 4100 | Daramad e eshterak |
| 4200 | Daramad e call e API |

Har wallet transaction dar hamun DB transaction journal e do-tarafe misaze. `source_key` unique; har line faghat yek taraf e mosbat. PostgreSQL header/line UPDATE/DELETE ro ba trigger rad mikone; balance e har sanad dar COMMIT ba constraint trigger check mishe. `posting_txid` ba `txid_current()` set mishe; line faghat dar transaction e sakhte-shodan e header mojaz ast. In field amdan jaye tuple `xmin` ast: savepoint e nested transaction xmin e joda dare.

Asnad e ghadimi pak/virayesh nemishan. Reversal yek sanad e mokhalef ba reference va admin/reason ast; correction reversal+replacement atomic ast. Mande manfi rad mishe. Opening migration aval sum e ledger ro ba cached balance moghayese mikone, mismatch ro rad mikone va baraye mande e mosbat journal misaze. Settlement faghat receipt e verified ro yek bar az clearing be bank montaghel mikone.

## 13. Migration, ejra va test

Baseline e 2026-10-07 ba darkhast e sahebe proje consolidate shod: users/profile, providers/logo/trust-anchor, plans/clients va wallet trigger dar create haye asli. Media FK ba'd az create e media attach mishe. Filename e create haye mojud hefz shode ta live dobare CREATE nakone. Migration haye pure alter e superseded hazf shodan; record e tarikhche e live hefz ast. Data migration e opening mali joda mimune.

Baraye database e mojood e ghadimi: aval revision e pre-consolidation/additive ra migrate kon, ba backup va taeed e ledger; ba'd baseline e consolidated ra deploy kon. Taghir e mostaghim az yek schema e ghadimi be baseline-e rewritten bedun e upgrade script mojaz nist. In dev ghabl az consolidate update shod, backup local gerefte shod va hich `migrate:fresh` ru data e mojood ejra nashod. Migration haye baadi additive-and.

- PHP suite: SQLite in-memory, Redis13/14/15, SMS fake, Zohal/Zibal credential khali; `GuardsTest` config va DB e vaghei e connection ro misanje. Test ha serial-and.
- `tests/Integration/FinancePostgresTest.php`: faghat database e sarih `priceboard_verify_*`, ba guard e name va default-dev inequality; migrate e disposable, commit/trigger e vaghei, nested purchase/topup. Hargez default DB e dev.
- Browser: Playwright az host, fixture e joda, theme/token/RTL/mobile375 check; no real payment/SMS/inquiry. Full PHP va E2E ba'd az integration.
- Asset taghir kard: `npm run build`, ba'd `php artisan octane:reload`. `.env` taghir kard: container recreate. Worker ha ba deploy restart shan.
- Service smoke: `/up`, `horizon:status`, `migrate:status`, count/status e Redis e vahed. Live provider tick ra baraye test offline majbur nakon.

## 14. Manba' haye gheymat (QuoteSource ha)

Har provider = yek radif tu `providers` (`key` + `driver`) + yek class tu `app/Modules/Pricing/Infrastructure/Sources/` ke `QuoteSourcePort` ro piade mikone. Switch e `driver → class` tu `SourceManager::make()` ast؛ `SourceManager::drivers()` lista ye driver haye mojaz ro mide. `ProviderSeeder` hame ro **gheyr-fa'al** misaze (`is_active=false`) va `is_active`/`is_trust_anchor` e radif e mojud ro dast nemizane — admin tasmim migire kodum roshan she.

| `key` | `driver` | `kind` | `sort` | Vahed e upstream → rial | Timestamp e upstream | Side ha | Nokte ye vaghei (az docblock e Source) |
|---|---|---|---|---|---|---|---|
| `tgju` | `tgju` | reference | 20 | IRR ha **rial**؛ `ons`/`silver`/`copperp` USD — unit **per-entry** | `ts` vaght e Tehran bedun e offset | `last`؛ `tgju_gold_irg18` + `_buy` = ask/bid (abshode) | `ons`/`silver`/`copperp`/`gerami` faghat **ruzane** be-rooz mishan → `freshness_seconds` e `provider_spreads` baraye tgju override |
| `estjt` | `estjt` | reference | 10 | **toman** ×10؛ «انس طلا» USD (per-instrument) | **nadare** → `payloadHash` (md5 e HTML) | `last` | `POST admin-ajax.php action=new_price`؛ UA e browser + HTTP/1.1 ejbari؛ separator e hezargan `٫` (U+066B) |
| `nerkh` | `nerkh` | reference | 30 | **toman** ×10؛ `OUNCE`/`OUNCE_SILVER` USD | `update` e har radif **Jalali** (Tehran) → UTC؛ `timestamp` e bala faghat fallback | `last` | `Cache-Control: max-age=30`؛ faghat radif haye marja' map shodan |
| `nerkh-stock` | `nerkh` (option `item=GOLD18K_STOCK`) | reference | 70 | toman ×10 | hamun nerkh | `last` (faghat `xau-18k-g-irr`) | hamun driver، provider e joda — `NerkhQuoteSource::entries()` ba `options.item` faghat yek radif parse mikone |
| `milli` | `milli` | platform | 40 | **rial e har milligram** → scale 3 (×1000) | `date` ISO e Tehran bedun e offset | `last` (kharid = foroosh) | `GET /api/v1/public/milli-price/external` |
| `talasea` | `talasea` | platform | 50 | **toman e har milligram** → ×1000 ×10 | **nadare** → `payloadHash` | `last` (`fee` joda) | `api.talasea.ir` challenge nadare؛ `talasea.ir` dare |
| `technogold` | `technogold` | platform | 60 | **toman e har gram** ×10 | **nadare** → `payloadHash` | `bid`/`ask` e vaghei: `sell_price`=bid، `buy_price`=ask | esm ha az did e karbar-and؛ bid > ask = map e eshtebah (test dare) |
| `tlyn` | `tlyn` | platform | 70 | IRR ha **hezar toman** → ×1000 ×10؛ `XAU` USD | `date.utc_timestamp` ISO ba `Z` | `buy`=bid، `sell`=ask؛ sekke ha yek gheymat ru har do side | `status=disabled` rad mishe؛ `GOLD18_INTL`/`GOLD_BUBBLE_*` amdan map nashodan |
| `manual` | `manual` | reference | 80 | az `manual_prices` (SourceManager mikhune va pass mide) | **nadare (amdan)** → hargez «kohne» rad nemishe | `last` | HTTP nadare؛ `FetchStage` mostaghim `fetch()` ro seda mizane |
| `mock` | `mock` | reference | 90 | — (tabe' e mahz e zaman × seed) | `ClockPort` | `last` + bid/ask baraye jofte ye vaghei | dev e offline؛ hich IO؛ ba `FrozenClock` determinist |

Vahed ha az enum e `UpstreamUnit` (`rial`/`toman`/`none`) va side ha az `Side` (`last`/`bid`/`ask`) miyan. `providers.upstream_unit` faghat **default** ast — unit e vaghei tu map e har entry e Source mineshine (dars e estjt: ons USD vasat e radif haye toman).

Provider e jadid → skill e `add-price-provider` (yek `case` tu `SourceManager` + yek radif tu seeder + fixture e vaghei tu `tests/Fixtures/`).

---

## 15. Zanjire ye olaviat، feed e mo'asser va backup e dasti

```
PriorityChain::order(global, custom)          Domain — mahz، bedun e IO
   │  1. sefareshi (symbol_provider_priorities) aval
   │  2. baghi ye provider haye fa'al be tartib e providers.sort
   │  3. kelid e gheyr-fa'al/nashenas nadide
   │  4. `manual` HAMISHE akhar
   ├──▶ EffectivePriceQuery::feed()          API e /v1/effective + alarm e panel
   │      har symbol: avvalin provider e TAZE → vagarna manual → vagarna taze-tarin (flag e stale)
   │      `usingFallback` roshan = admin bayad befahme
   └──▶ EloquentManualBackup::sync()         ManualBackupStage (isolated، ba'd az Persist)
          baraye HAR Instrument yek radif e symbol_price_settings (firstOrCreate ba default)
          manual_only ya manual_auto_source='none'  → hich
          manual_auto_source='priority'             → zanjire bedun e manual
          manual_auto_source='<provider key>'       → faghat hamun provider
          snapshot e `last` (fallback `ask`)، sen = upstreamAt ?? quotedAt، bayad tu freshness bashe
          row-lock ru settings → manual_prices.{minor,scale,quote_currency,source_hint,updated_by='auto'}
          updated_at = zaman e observe e upstream (na now) ta sen e backup sadegh bemune
```

- Yek manba' e haghighat baraye DO masraf (feed va «پر کردن بر اساس اولویت») — `PriorityChain` tu `Domain/Services/`، entekhab e provider e backup tu `ManualBackupSelector::providerKeys()`.
- **Default e `manual_auto_source` = `priority`** (migration `2026_10_07_140000_default_manual_auto_source_to_priority`؛ ghablan `none` bud va symbol e tanzim-nashode hich vaght backup nemigereft). `SymbolPriceSettingsQuery::defaults()` hamin ro mide.
- Manual-only = feed e un symbol faghat `manual_prices` ro mibine؛ hich auto-update (hata ba source e ghabli).
- UI: `/dashboard/priorities` (`panel.priorities.*`) — olaviat + tanzim e manual-only/backup؛ `/dashboard/manual` (`panel.manual.*`) — gheymat e dasti + import/export e Excel (`ManualPriceWorkbook`).

---

## 16. Vahed e namayesh: zakhire RIAL، tablo ye omumi TOMAN

- DB، Redis، Influx، API (`/api/v1/*`)، panel e admin va TV hame **rial**-and (`QuoteCurrency::displayDivisor()` = 1، `displayLabel()` = «ریال»/«دلار»، `Price::display()` ba bcmath).
- **Faghat tablo ye omumi (`/` va `/board.json`) toman neshun mide** — khaste ye sahebe proje (2026-10-06). Tabdil tu `PublicBoardQuery` ba `Common\Helpers\Money::tomanDivisor('IRR') = 10` va `Money::tomanDisplay()` (bcmath، nim-be-bala): `divisor`/`quoteLabel` tu payload mire ta SSR va `board.js` yek adad ro render konan؛ `sellDisplay`/`minDisplay`/`avgDisplay` ham toman-and. Dollar bedun e tabdil.
- Toman be onvan e **vahed e upstream** (`UpstreamUnit::Toman`) dakhel e Source ×10 mishe va rabti be namayesh nadare.
- Form haye admin (`x-form.money`) rial e sahih migiran (`Money::rialFromInput`).

---

## 17. Mali — takmil e bakhsh 12 (jaryan e code)

| Reason e `wallet_transactions` | Koja neveshte mishe | Reference |
|---|---|---|
| `topup` | `Billing\TopupPaymentAction` (ba'd az verify e server-to-server) | `zibal:{track_id}` |
| `plan_purchase` | `Api\PurchasePlanAction` | `purchase_reference` e eshterak |
| `api_call` / `refund` | `Api\ChargeApiCallAction` (reserve → ejra → refund ru khata/304) | hash e client + request id |
| `admin_adjust` | `Wallet\AdjustWalletAction` (panel: `users.wallet.adjust`، `finance.wallet-adjust`) | request key؛ bargasht ba `ReverseWalletAdjustmentAction` |

- Har tarakonesh e wallet tu hamun DB transaction yek sanad e `finance_journals` + `finance_journal_lines` misaze (`Finance\PostWalletJournalAction`)؛ `source_key` unique، trigger haye PG hame ye UPDATE/DELETE ro rad mikonan (jadval e hesab ha tu bakhsh 12).
- Query haye admin (`FinanceController`، route haye `panel.finance.*` tu `routes/billing.php` ba `can:finance.view` / `can:finance.manage`):
  - `FinanceLedgerQuery`: `ledger()`، `totals()`، `trialBalance()`، `generalLedger()`، `transaction()`، `journalLines()`، `unsettledPayments()`، `exportRows()` (CSV).
  - `FinanceMonthlyReportQuery::report()`: satl e **mah e Jalali** ke tu PHP hesab mishe (marz e 00:00 e Tehran، yek aggregate per mah، saghf 24 mah) — `date_trunc('month')` e SQL miladi ast va bi-ma'ni. Mablagh ha rial.
  - `FinanceInvoiceQuery`: `list()`، `issuable()` (topup e verify-shode / eshterak e karbar e `invoice_verified` bedun e factor)، `invoice()`.
- Factor: `Billing\IssueInvoiceAction` → `billing_invoices` (trigger e `billing_invoices_immutable`) + `billing_invoice_sequences(year, last_number)` ba `lockForUpdate`. Shomare `PB-YYYY-NNNNNN`؛ **YYYY sal e miladi** ast (`CarbonImmutable::now()->format('Y')`). Faghat karbar e `level=invoice_verified` ba `invoice_profile`؛ ru har source (`site_subscription_id` / `wallet_topup_payment_id`) idempotent. Block e foroshande az `config('billing.seller')` (`app/Modules/Billing/Config/billing.php`؛ env `BILLING_SELLER_*`، `BILLING_VAT_PERCENT` faghat sahm e VAT ro **namayesh** mide، mablagh ro taghir nemide). Ersal be samane ye modian piade **nashode**.

---

## 18. Schema — nama ye kolli (az `database/migrations/`)

Hame ye mablagh ha `bigint` e minor unit (rial → `scale 0`، USD → `scale 4`). `timestampTz` hame ja. Jadval haye framework (`cache`، `cache_locks`، `jobs`، `job_batches`، `failed_jobs`، `sessions`، `password_reset_tokens`، `telescope_*`) inja nayumadan.

| Jadval | Hadaf | Soton haye kelidi / constraint |
|---|---|---|
| `users` | hoviat e login (phone) + sath e moshtari | `phone` unique، `is_admin`، `status`، `disabled_at/reason`، `level` (`basic`/…/`invoice_verified`)، `customer_type`، `national_code`، `onboarded_at`، `invoice_requested`، `identity_profile` json، `invoice_profile` json، `last_login_at` |
| `providers` | config + salamat e har manba' | `key` unique، `driver`، `kind` (reference/platform)، `name/name_fa`، `logo_media_id` FK→media، `is_active`، `is_trust_anchor`، `in_comparison/on_board/in_chart`، `timeout`، `poll_interval_seconds`، `next_poll_at`، `freshness_seconds`، `hide_after_seconds`، `upstream_unit`، `sort`، `options` jsonb، `last_*` (attempt/success/failure/error/latency/symbol_count/rejected_count)، `consecutive_failures`، `health_state`، `last_payload_hash`، softDeletes |
| `provider_spreads` | spread e namayesh e har (provider, symbol) + override e freshness | `buy_mode/buy_value`، `sell_mode/sell_value` decimal(12,4)، `is_enabled`، `freshness_seconds`، `sort`؛ unique(provider_id, symbol) |
| `provider_spread_revisions` | tarikhche ye append-only ye spread | `is_removal`، `changed_by`، `effective_from`؛ index(provider_id, symbol, effective_from) |
| `price_snapshots` | **akharin** gheymat e kham e har (provider, symbol, side) | `quote_currency`، `scale`، `raw_minor`، `prev_minor`، `day_open/high/low_minor`، `change_pct`، `direction`، `pending_outlier_minor`، `has_native_sides`، `upstream_at`، `upstream_lag_seconds`، `quoted_at`؛ unique(provider_id, symbol, side) |
| `price_changes` | audit e append-only — faghat vaghti `raw_minor` avaz shod | `raw_minor`، `upstream_at`، `observed_at`؛ index(provider_id, symbol, side, observed_at) |
| `influx_backfills` | provenance e backfill e Influx | `bucket`، `provider_id`، `symbol`، `source`، `range_start/stop`، `points` |
| `manual_prices` | gheymat e dasti/backup e har symbol | `symbol` unique، `minor`، `scale`، `quote_currency`، `source_hint`، `updated_by` (`auto` = backup e khodkar) |
| `symbol_provider_priorities` | olaviat e sefareshi e har symbol | unique(symbol, provider_id)، `position` |
| `symbol_price_settings` | manual-only / backup e har symbol | `symbol` unique، `manual_only`، `manual_fallback_enabled`، `manual_auto_source` (default **`priority`**) |
| `wallets` | kif e pool (balance = cache) | `owner_id` FK users، `balance_minor`، `currency`، `is_blocked`؛ unique(owner_id, currency) |
| `wallet_transactions` | ledger e **append-only** (trigger e PG + SQLite) | `direction` (credit/debit)، `amount_minor`، `balance_after_minor`، `reason`، `reference`، `meta`؛ unique(wallet_id, reason, reference) |
| `api_price_groups` | daste-bandi ye gheymat e customer (fe'lan masraf e asli nadare) | `key` unique، `cost_per_call_minor`، `is_default` |
| `api_plans` | plan e eshterak | `key` unique، `price_minor`، `calls_per_day`، `cost_per_call_minor`، `duration_days`، `calls_included`، `is_active`، `sort` |
| `api_clients` | site e moshtari + credential | `user_id`، `plan_id`، `price_group_id`، `client_id` unique، `secret_encrypted`، `status` (pending/approved/…)، `approved_at/by`، `last_used_at`، softDeletes |
| `api_usage_daily` | rollup e ruzane ye usage | unique(api_client_id, day)، `calls`، `cost_minor` |
| `site_subscriptions` | snapshot e eshterak e kharide-shode | `api_client_id`، `api_plan_id`، `user_id`، `starts_at/ends_at`، `calls_included`، `calls_per_day`، `price_minor`، `cost_per_call_minor`، `calls_used`، `purchase_reference` unique |
| `site_subscription_days` / `site_subscription_calls` | masraf e ruzane / idempotency e har call | unique(site_subscription_id, day) / `reference` unique |
| `wallet_topup_payments` | pardakht e dargah (Zibal) | `request_key`، `track_id` unique، `status`، `gateway_reference`، `verified_at`، `verify_data`؛ unique(user_id, request_key) |
| `billing_invoices` | factor e **immutable** (trigger) | `number` unique (`PB-YYYY-NNNNNN`)، `kind`، `amount_minor`، `buyer_snapshot`، `lines_snapshot`، `issued_at`؛ FK unique be topup ya subscription |
| `billing_invoice_sequences` | shomarande ye sali | `year` PK، `last_number` |
| `finance_accounts` | chart e hesab ha | `code` unique، `name`، `normal_side`، `is_active` |
| `finance_journals` | header e sanad e do-tarafe (append-only) | `source_key` unique، `memo`، `actor_id`، `reverses_journal_id` unique، `occurred_at`، `posting_txid` (PG) |
| `finance_journal_lines` | khat haye sanad | `finance_journal_id`، `finance_account_id`، `wallet_id`، `user_id`، `debit_minor`/`credit_minor` (faghat yek taraf mosbat — CHECK)؛ constraint trigger e balance ru COMMIT |
| `seo_meta` | override e metadata ye safhe ha | unique(seoable_type, seoable_id)، `title`، `description`، `canonical_url`، `robots`، `og_image` |
| `media` / `media_usages` | ketabkhane ye file + jaye masraf | `path` unique، `mime`، `size`، `width/height`، `alt`، `derivatives` json؛ `media_usages` unique(media_id, usable_type, usable_id, field)، `restrictOnDelete` |
| `tickets` / `ticket_messages` / `ticket_events` / `ticket_attachments` / `ticket_notifications` | ticket e moshtari/admin + audit + peyvast e private + SMS e ersal-shode | `type`، `status`، `subject`، `meta`، `last_activity_at`؛ message `is_staff`؛ event `kind`/`from_status`/`to_status`؛ attachment `path` unique، `sha256`؛ notification `event_id` unique، `sent_at` |
| `user_profile_audits` | before/after e virayesh e profile | `user_id`، `actor_id`، `before`، `after` json |
| `identity_verification_runs` | har ejra ye estelam e Zohal | `submission_key` unique، `status` (queued/…)، `input`، `results` json، `ticket_id` |
| `access_roles` / `admin_access_profiles` / `admin_role_assignments` / `access_audits` | RBAC | role `name` unique + `permissions` json؛ profile `user_id` PK + `full_access`؛ assignment PK(user_id, role_id)؛ audit `action`، `subject_id`، `before/after` |

Migration ha **ezafe-shi** mimunan؛ baseline e consolidate e 2026-10-07 tu bakhsh 13.

---

## 19. Ops — service ha، command ha، gate ha

### Service haye `docker-compose.local.yml`

| Service | Container | Port e host | Kar |
|---|---|---|---|
| `octane` | `pb_octane` | **8300** → 8000 | app (Swoole)، healthcheck `GET /up` |
| `vite` | `pb_vite` | **5373** | HMR (`vite.config.js` bayad `port: 5373, strictPort: true` bashe) |
| `horizon` | `pb_horizon` | — | `php artisan horizon` (queue haye `QueueName`: default/notifications/verification) |
| `price-worker` | `pb_price_worker` | — | halghe ye `while :; do php artisan pricing:tick; sleep $((15 - $(date +%s) % 15)); done` — **amdan scheduler nist** |
| `scheduler` | `pb_scheduler` | — | `php artisan schedule:work` |
| `postgresql` | `pb_postgresql` | **55434** | Postgres 18 |
| `redis` | `pb_redis` | **56381** | yek instance؛ DB0 session/queue/OTP، DB1 cache، DB2 prices؛ `noeviction` + AOF |
| `influxdb` | `pb_influxdb` | **8386** | Influx 2.7؛ bucket e avaliye `ticks` (retention `INFLUX_RETENTION`، default 30d) |

### Command haye artisan e module ha (`app/Modules/*/Console/Commands/`)

| Command | Option ha | Kar |
|---|---|---|
| `pricing:tick` | `--json`، `--quiet-ok`، `--force` (nadide gereftan e `next_poll_at`) | yek tick e kamel (`IngestTickAction`) |
| `pricing:influx:provision` | `--tokens` | sakht/be-rooz e bucket ha (`ticks`، `candles_1m/5m/15m/1h/4h/1d`) va task ha (`resources/influx/tasks/*.flux`)؛ idempotent |
| `pricing:influx:drain` | `--batch=2000`، `--max-batches=10` | replay e spool e Influx |
| `pricing:influx:health` | `--json` | salamat e task ha/candle ha |
| `api:usage:rollup` | `--date=`، `--days=2` | Redis + ledger → `api_usage_daily` (idempotent) |
| `otp:flush {phone}` | — | pak kardan e code/cooldown/rate-limit e yek shomare — **faghat dev**؛ e2e azash estefade mikone |

Scheduler (`routes/console.php`): `pricing:influx:drain` har daghighe، `pricing:influx:health` har 5 daghighe، `api:usage:rollup` har 15 daghighe، `horizon:snapshot` har 5 daghighe، `telescope:prune --hours=48` ruzane (faghat agar Telescope fa'al bashe). Hame `withoutOverlapping`.

### Horizon / Telescope

- **Horizon** hamishe register mishe (`App\Providers\HorizonServiceProvider`)؛ `/horizon` ba gate e `viewHorizon` = permission e `monitoring.view` (`AccessQuery::allows`).
- **Telescope** dev-dependency ast: `AppServiceProvider` faghat tu `local`/`testing` va vaghti class vojud dare `TelescopeServiceProvider` ro register mikone؛ `config/telescope.php` → `TELESCOPE_ENABLED` (default: faghat `local`). Gate e `viewTelescope` = `monitoring.view`. OTP/cookie/signature redact mishan.
- Tu `PermissionCatalog`، route haye `panel.monitor.*`، `horizon.*`، `telescope.*` zir e group e `monitoring` hastan.

### Taghir dadi، chi lazem e?

| Taghir | Kar |
|---|---|
| PHP/Blade | Octane ba `OCTANE_WATCH=true` khodesh reload mikone؛ age nashod `octane:reload` |
| Asset (JS/CSS) | `npm run build` + **`php artisan octane:reload`** — Octane manifest e Vite ro per-worker cache mikone |
| `.env` | `docker compose -f docker-compose.local.yml up -d --force-recreate` — `octane:reload` env ro **nemikhune** |
| Worker ha (horizon/price-worker/scheduler) | ba'd az deploy restart |
| Influx token/bucket | `pricing:influx:provision --tokens` → token ha tu `.env` → `--force-recreate` |

Script haye yek-bare ye dev tu `scripts/` (`resilience-check.sh` taab-avari ru mohit e vaghei، `fetch-benchmark.php`، `outage-drill.php`، `reset-influx-tokens.php`، …).

---

## 20. Test

### PHP (PHPUnit — dakhel e container)

```bash
docker compose -f docker-compose.local.yml exec octane php artisan test            # kol
docker compose -f docker-compose.local.yml exec octane php artisan test --compact  # kholase
```

- `phpunit.xml` ba `force="true"` ru hame ye env: `DB_CONNECTION=sqlite` + `:memory:`، `CACHE_STORE=array`، `QUEUE_CONNECTION=sync`، `SMS_DRIVER=fake`، `BYPASS_OTP=false`، `TELESCOPE_ENABLED=false`، `REDIS_PRICE_DATABASE=15`، Influx URL e `invalid` (adapter e fake bind mishe). Dalil: container env e vaghei ye `DB_DATABASE=priceboard` ro export mikone va bedun e `force`، `RefreshDatabase` **database e dev ro pak mikard** (yek bar shod).
- `tests/TestCase.php` kamarband e dovom: sqlite e in-memory ro sarih set mikone، Redis e `default`→DB13، `cache`→DB14، `prices`→DB15 va `RedisManager` ro az no misaze (vagarna `flushdb()` e test ru DB0 e dev mioftad — session ha pak mishodan).
- Suite ha: `tests/Unit/{Auth,Common,Pricing,Seo}` (bedun e Laravel/DB — Domain e mahz) va `tests/Feature/{Access,Api,Auth,Billing,Common,Kyc,Media,Panel,Pricing,Public,Seo,Ticket,Wallet}` + `ClassLoadabilityTest` (hame ye class haye `app/` load mishan) + `GuardsTest` (config e test ro zende check mikone).
- `tests/Integration/FinancePostgresTest.php`: faghat ba `PB_TEST_PG_DATABASE=priceboard_verify_*` (guard e regex)؛ trigger/commit e vaghei ye PG. Hargez ru DB e dev.
- Fake haye port tu `tests/Support/Fakes/`: `ArrayHotCache`، `InMemorySnapshotStore`، `FakeTimeSeries`، `FrozenClock`، `FakeQuoteSource`، `FakeManualBackup`، `InMemoryUpstreamCallLog`، `InMemoryOtpStore`، `FakeIdentityVerification`، `FakePaymentGateway`. Har port e jadid = fake + bind + **test e bind**.

### Fixture ha — hich network

`tests/Fixtures/`: `tgju-ajax.json`، `estjt-new-price.json`، `nerkh-prices.json`، `milli-price-external.json`، `talasea-get-gold-price.json`، `technogold-only-price.json`، `tlyn-price.json`، `zohal/`. Hame payload e **vaghei** e download-shode-and؛ test e har instrument e har source ru meghdar e minor e daghigh assert mikone (jaye bug e 10×). Hich test-i be tgju/estjt/nerkh/…/Zohal/Zibal/Kavenegar request nemizane.

### Playwright (az HOST)

```bash
docker compose -f docker-compose.local.yml up -d   # stack bala
npx playwright test                                 # kol e e2e/ — 1 worker، serial
npx playwright test e2e/login.spec.ts
```

`playwright.config.ts`: `baseURL` 8300، `fa-IR`/`Asia/Tehran`، `workers: 1` (spec ha DB va session e moshtarak daran). Helper ha tu `e2e/helpers/auth.ts`: `ADMIN`، `artisan()`، `resetOtp()` (`otp:flush`)، `signInAsAdmin()`، `signInAsCustomer()`، `tokenRgb()` (check e token e `@theme`)، `expectNoPageOverflow()`.

| Spec | Chi ro mipushune |
|---|---|
| `login.spec.ts` | flow e OTP (normalize ye raghame Farsi، bypass e dev، refresh e vasat e flow، code e ghalat) + token haye brand |
| `public.spec.ts` | tablo ye omumi ba data ye vaghei، poll e `/board.json` + ETag، flash e sabz، **sefr request e biruni** |
| `tv.spec.ts` | board e TV bedun e login، `/tv.json` + 304، 375px، sefr request e biruni |
| `seo.spec.ts` | JSON-LD/FAQ، sitemap index + pages، `robots.txt` e dynamic، 375px |
| `panel.spec.ts` | mizkar، sidebar، manba' ha، spread + preview، payesh e tick، salamat/matrix |
| `sidebar.spec.ts` | tooltip e rail e sidebar |
| `responsive.spec.ts` | drawer e mobile + hame ye safhe haye panel tu 375px bedun e overflow |
| `monitor.spec.ts` | payesh e tick: kholase ye provider، filter ha، «جدول ردها» |
| `upstream.spec.ts` | payesh e manba' ha (call haye upstream): filter، search، jozyat e call ba redact، 404 |
| `charts.spec.ts` | chart e admin: deep-link، baze ye haftegi، legend e manba' ha، link az matrix |
| `priorities.spec.ts` | olaviat e per-symbol، save/reset، manual-only + badge e tak-manba' |
| `users.spec.ts` | lista ye karbaran + safhe ye karbar (search/filter/pill/token) |
| `identity-profile.spec.ts` | profile e haghighi/hoghoghi + audit، ticket ba peyvast، 375px |
| `onboarding.spec.ts` | onboarding e moshtari (hoghoghi + madrak → ticket؛ validation؛ «خیر») |
| `tickets.spec.ts` | ticket e moshtari/admin، javab، peyvast ba signed URL، 375px |
| `finance.spec.ts` | safhe ye mali: 6 tab، gozaresh e mahane ye Jalali، sharj، filter e Jalali + CSV |
| `access-seo.spec.ts` | naghsh e readonly az UI + save e SEO ba escape |
| `media.spec.ts` | ketabkhane: upload → virayesh → logo → guard e hazf → hazf |
| `monitoring-access.spec.ts` | guest → login؛ admin Horizon/Telescope ro mibine |

Ghabl az e2e: `npm run build` + `octane:reload`؛ `public/hot` (Vite dev) nabayad bashe vagarna Alpine dir init mishe va `fill` ha flaky mishan.

### Mutation check — ghanun e kar

Test e sabz hichi sabet nemikone ta vaghti ba kharab kardan e code ghermez nashe. Baraye har mekanism e mohem (bind e container، `×10` e toman، zanjire ye olaviat، consume e atomic e OTP، refund e wallet، `ltrim` e log) yek bar amdan code ro beshkan، fail ro bebin، bargardun — bargasht ba `cp` e backup، **na `git checkout --`**. Natije ye mutation check tu entry e task tu `docs/TASKS.md` sabt mishe.
