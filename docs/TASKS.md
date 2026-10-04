# TASKS — PriceBoard

> **In file manba' e haghighat e «koja hastim» ast.** Agar session ghat' shod، ba `cat docs/TASKS.md` edame bede.
> «Chi va chera» → `docs/PLAN.md`.

## Ghanun haye in file

1. Ba'd az **har** task e tamum shode، **hamun lahze** `- [ ]` → `- [x]` kon va yek khat e «chi shod» (file haye asli) zir esh benevis.
2. Bakhsh e **«Hala koja hastim»** ro be-rooz negah dar — makhsusan har chizi ke **nime-kare** mune.
3. Har tasmimi ke vasat e rah gerefti va tu `PLAN.md` nist → bakhsh e **«Tasmim haye vasat e rah»**.
4. Task e jadid lazem shod → ezafe kon ba shenase ye ba'di (`P1-14`). Task ro **pak nakon**؛ agar lazem nabud `~~strike~~` + dalil.

---

## Hala koja hastim

**Phase**: 1 — Pricing engine
**Task e fe'li**: `P1-04` / `P1-05` (test e Domain)
**Akharin kar**: Phase 0 **tamum shod** — stack bala-st va `/up` = 200. Enum ha, `Price`, `TickTime`, `Spread(Pair)`, `SpreadPricer`, `PriceSanity`, `NumberParser`, `PersianText` neveshte shodan va syntax eshun salem ast.
**Nime-kare**: hich. Vali **hanuz baraye hich kodum az class haye Domain test neveshte nashode** — gam e ba'di hamun ast.
**Gam e ba'di**: test e `NumberParser` ru fixture haye vaghei (`tests/Fixtures/`), ba'd `SpreadPricer` + `PriceSanity`. Ba'd `P1-03` (Port ha) va `P1-06` (migration ha).

### Chiz hayi ke vasat e rah yad gereftim (ta dobare gir nakonim)
- 🔴 **`docker compose restart` baraye taghir e `.env` KAFI NIST.** `env_file` faghat vaghte SAKHTE shodan e container khunde mishe; `APP_KEY` e khali hamchenan be onvan e env e OS export mishe va bar `.env` BARANDE mishe. Hatman `up -d --force-recreate`.
- Image e `pb-octane` ffmpeg ro nasb mikone (az Dockerfile e hg_website ers reside) — baraye in projeh lazem NIST va ~2 daghighe be build ezafe mikone. Yek tamiz-kari ye ekhtiari ast.

---

## Phase 0 — Bootstrap

- [x] **P0-00** — `git init` + folder haye `docs/`، `.claude/skills/`، `tests/Fixtures/`
  → repo sakhte shod. Fixture haye **vaghei** zakhire shodan: `tests/Fixtures/tgju-ajax.json` (175KB، 963 symbol)، `tests/Fixtures/estjt-new-price.json`.
- [x] **P0-01** — Rules + Skills + Docs
  → `/CLAUDE.md`؛ skill ha: `pricing-architecture`، `add-price-provider`، `influx-timeseries`، `dashboard-ui`، `docker-dev`؛ `docs/PLAN.md` + `docs/TASKS.md`.
- [x] **P0-02** — `docker-compose.local.yml`: `COMPOSE_PROJECT_NAME=pb`، port ha (8300/5373/55434/56381/56382/8386)، service e `influxdb`، service e `redis-prices` (`noeviction`)، service e `price-worker` (halghe ye grid-aligned)، hazf e chizi ke lazem nist
  → `pb` project name، port ha shift shodan، `influxdb`/`redis-prices`/`price-worker` ezafe shodan. `reverb` ezafe nashod (polling).
- [x] **P0-03** — `.env.example` + `.env`: hame ye kelid ha (`APP_*`، `DB_*`، `REDIS_*`، `REDIS_PRICES_*`، `PRICING_*`، `INFLUX_*`، `OTP_*`، `SMS_*`، `OCTANE_*`، `VITE_*`)
  → `.env.example` + `.env` sakhte shodan. `SMS_DRIVER=fake` default، `BYPASS_OTP=true` ba tozih e dokme ye por-kardan e khodkar.
- [x] **P0-04** — Check e extension haye PHP
  → ru image e sakhte-shode check shod: `curl` `mbstring` `json` `pdo_pgsql` `redis` `swoole` `bcmath` `intl` `gd` `zip` `apcu` `pcntl` `posix` `sockets` `exif` — **hame mojud**. PHP 8.5.11. Hich taghiri tu Dockerfile lazem nashod.
  → moghol be ba'd az build e image (`P0-10`) — bedun e image nemishe `php -m` zad.
- [x] **P0-05** — Laravel 13 install (skeleton tu temp، ba'd merge tu projeh bedun e overwrite kardan e file haye ma) + `composer.json` e nahayi
  → Laravel 13.34 nasb shod (skeleton tu temp، ba'd `rsync --ignore-existing` ta file haye ma overwrite nashan). `composer.json` e nahayi: Livewire 4.4.7 **sarih**، InfluxDB client 3.9.0، Octane 2.20، Horizon، Pulse، Telescope(dev)، Sanctum، jalali، tabler-icons، responsecache، sentry. 137 package.
- [x] **P0-06** — `app/Modules/CLAUDE.md` + `resources/views/CLAUDE.md` (ba'd az inke folder ha vojud daran)
  → `app/Modules/CLAUDE.md` + `resources/views/CLAUDE.md` neveshte shodan.
- [x] **P0-07** — `vite.config.js` (`port: 5373`، `strictPort: true`) + `package.json` (alpine، klinecharts، tailwind v4)
  → `vite.config.js` ba `port: 5373, strictPort: true` + polling. Plugin e font e `bunny` **hazf shod** (CDN — ru host e Iran ghabel e etminan nist) → font local mishe. `package.json`: alpine + collapse + focus + klinecharts 10.
- [x] **P0-08** — `phpunit.xml` ba `force="true"` ru hame ye env + `tests/TestCase.php`
  → `phpunit.xml` ba `force="true"` ru hame ye env (jologiri az pak shodan e DB e dev tavasot e RefreshDatabase) + env e `SMS_DRIVER=fake` va Influx e ghalati.
- [x] **P0-09** — `docs/architecture.md` + `docs/dashboard-design.md`
  → `architecture.md` (chera Ports&Adapters, se store, minor unit, spread ru read, pipeline, staleness, Octane safety, chiz haye amdan-nakarde) + `dashboard-design.md` (token haye kamel, font, sakhtar, component ha, `is_derived`).
- [x] **P0-10** — `up -d` + check e salamat va port
  → hame ye service bala: `pb_octane` `pb_postgresql` `pb_redis` `pb_redis_prices` `pb_influxdb`. `curl localhost:8300/up` = **200**.
  → Policy e Redis **ta'eed shod**: `redis` = `allkeys-lru` (cache), `redis-prices` = `noeviction` + `appendonly no`. InfluxDB 2.7.12 `ready for queries and writes`.
  → Hich conflict e port: tanha takrar haye machine `5173` va `8080`-and ke beyn e `hg_*` va `livewire_learn_*` az ghabl bude, na mal e ma.
- [ ] **P0-11** — Commit e avval

---

## Phase 1 — Pricing engine (bedun e UI)

### Domain (bedun e Docker test mishe)
- [ ] **P1-01** — Enum ha: `Instrument` (15 ta symbol + unit/quote/scale/weight/freshness/sanityBand)، `Metal`، `QuoteCurrency`، `Unit`، `Side`، `SpreadMode`، `Interval`، `Direction`، `HealthState`، `UpstreamUnit`
- [ ] **P1-02** — ValueObject ha: `Price` (minor+quote، `plus()` ba throw ru cross-currency)، `Symbol`، `Spread`، `Freshness`، `TickTime` (floor e grid e 15s)
- [ ] **P1-03** — Port ha: `QuoteSourcePort`، `SnapshotStorePort`، `HotCachePort`، `TimeSeriesPort`، `ClockPort`
- [ ] **P1-04** — `Support/NumberParser` (bcmath، policy e har-source، `٫`/`,`/`\t`/raghame Farsi) + `Support/PersianText::fold()`
  **Test**: ru fixture haye vaghei
- [ ] **P1-05** — `Domain/Services`: `SpreadPricer` (se invariant)، `QuoteEnricher`، `PriceSanity` (band + deviation + ghanun e «do outlier e mote'ali vaghei-and»)

### Persistence
- [ ] **P1-06** — Migration ha: `providers`، `provider_spreads`، `provider_spread_revisions`، `price_snapshots`، `price_changes`، `influx_backfills`
- [ ] **P1-07** — Model ha + `EloquentSnapshotStore` (upsert e yek-ja، na halghe) + `RedisHotCache` (pipeline، bedun e TTL، ru `redis-prices`)

### Source ha
- [ ] **P1-08** — `TgjuQuoteSource` + map e 17 kelid + filter e staleness
  ⚠️ `p` comma dare va ba'zi vaght `\t`؛ faghat ~389 az 963 taze-an؛ `tgju_gold_irg18`/`_buy` tanha jofte bid/ask
- [ ] **P1-09** — `EstjtQuoteSource` (DOMXPath، label e fold-shode، UA + HTTP/1.1، Toman→Rial)
  ⚠️ «انس طلا» **USD** ast va baghi Toman → unit **tu map**
- [ ] **P1-10** — `MockQuoteSource` (random-walk baraye dev/offline)
- [ ] **P1-11** — Test e **har instrument e har source** ru fixture e vaghei (meghdar e daghigh e minor)

### Influx
- [ ] **P1-12** — `InfluxTimeSeries` (write ba SDK e SYNCHRONOUS tu CLI؛ read ba `Http` + `FluxCsv`) + `Flux` + `InfluxSpool`
- [ ] **P1-13** — `resources/influx/tasks/*.flux` (6 task) + `pricing:influx:provision` e idempotent
- [ ] **P1-14** — `pricing:influx:drain` + `pricing:influx:health`

### Pipeline
- [ ] **P1-15** — `TickPipeline` + Stage ha (`Fetch`/`Normalize`/`Validate`/`Persist`/`Publish`) + `StagePolicy`
- [ ] **P1-16** — `IngestTickAction` + `TickReportDto`
- [ ] **P1-17** — `pricing:tick` (command e nazok) + khoruji e Finglish
- [ ] **P1-18** — `ProviderQuery` / `SpreadQuery` / `BoardQuery` / `CandleQuery`
- [ ] **P1-19** — `ProviderAction` / `ProviderSpreadAction` (ba `spread_version` bump + revision)
- [ ] **P1-20** — `PricingServiceProvider` + `Config/pricing.php` + **test e bind**
- [ ] **P1-21** — Fake ha (`InMemorySnapshotStore`، `ArrayHotCache`، `FakeTimeSeries`، `FrozenClock`) + test e kol e pipeline **bedun e Docker**
- [ ] **P1-22** — Seeder e provider ha (tgju + estjt، `is_active=false`) + spread e wildcard
- [ ] **P1-23** — Test e taab-avari: Influx down → spool؛ Redis down → PG fallback؛ PG down → polling edame

---

## Phase 2 — Auth (mobile + OTP)

- [ ] **P2-01** — Middleware ha: `TrustProxies` (Octane-safe)، `NormalizeStringMiddleware`، `NormalizePhoneMiddleware`، `NormalizeRouteParamsMiddleware`، `LatinizeDigits`، `SecurityHeaders` + `PhoneHelper` + `StringHelper`
- [ ] **P2-02** — Sabt tu `bootstrap/app.php` ba tartib e dorost + test e `LatinizeDigits` (JSON e Livewire rad she)
- [ ] **P2-03** — Migration e `users` (phone e unique، `phone_verified_at`، `is_admin`)
- [ ] **P2-04** — `OtpStorePort` + `RedisOtpStore` (code **hash shode**)
- [ ] **P2-05** — `SmsPort` + se driver: `KavenegarSms` (vaghei)، `LogSms` (dev)، `FakeSms` (in-memory، `assertSentTo()` baraye test) + `SmsManager` e switcher ru `SMS_DRIVER`
- [ ] **P2-06** — `SendOtpAction` / `VerifyOtpAction` + `OtpThrottle` (**phone + IP**)
- [ ] **P2-07** — `Livewire/SignIn` (do marhale) + `EnsureAdmin`
  Agar `BYPASS_OTP=true`: (a) code e tolid-shode zir e form neshun dade she، (b) dokme ye «پر کردن خودکار» kenar e input ke code ro mizare tu input. `false` bashe hich kodum nabashe (eyn e production) — **test baraye har do hālat**.
- [ ] **P2-08** — Test: rate limit e phone **va** IP jodagane، enumeration safety، `max_attempts` code ro batel kone

---

## Phase 3 — Panel e admin

- [ ] **P3-01** — `resources/css/app.css`: token haye `@theme` (brand/ink/canvas/surface/up/down/metal، radius ta 3xl، shadow، gradient، `hachure`) + utility ha
- [ ] **P3-02** — Font (local، bedun e CDN) + typography e RTL
- [ ] **P3-03** — Layout e `shell-app` + sidebar + topbar
- [ ] **P3-04** — Component ha: `stat-card`، `pill`، `health-badge`، `gauge`، `bar-chart` (capsule + hachure)
- [ ] **P3-05** — `/dashboard` — safhe ye asli (stat card ha + salamati)
- [ ] **P3-06** — `ProviderList` + `ProviderForm`
- [ ] **P3-07** — `ProviderSpreadForm` (har `Instrument::cases()` + preview e **server-side** ba hamun `SpreadPricer`)
- [ ] **P3-08** — `ProviderHealth`
- [ ] **P3-09** — `TickMonitor` (chi rad shod va chera) — safhe ye asli ye debug
- [ ] **P3-10** — `InstrumentMatrix` (`provider × symbol` + sen e har khune + marker e `is_derived`)
- [ ] **P3-11** — Verify ba Playwright + moghayese ba `images/dashboard-prefer.webp`

---

## Phase 4 — Wallet + API

- [ ] **P4-01** — Migration e `wallets` + `wallet_transactions` (append-only، `unique(wallet_id, reason, reference)`)
- [ ] **P4-02** — `WalletAction` (credit/debit) + `WalletQuery` (balance az ledger)
- [ ] **P4-03** — Migration e `api_clients` + `api_price_groups` (mehvar e **jodagane** az `provider_spreads`)
- [ ] **P4-04** — HMAC auth middleware (+ decoy-HMAC، nonce، skew)
- [ ] **P4-05** — Debit e atomic ba Lua + refund ru error/304 — **fail-CLOSED**
- [ ] **P4-06** — Endpoint ha + `ETag` ru snapshot
- [ ] **P4-07** — `CustomerList` + `WalletLedger` tu panel
- [ ] **P4-08** — Test: debit e dobare mamnu'، refund، fail-closed vaghti Redis down ast

---

## Phase 5 — Website UI

- [ ] **P5-01** — Layout e site e omumi + token haye moshtarak
- [ ] **P5-02** — Jadval e moghayese (SSR + `/api/board.json` ba ETag)
- [ ] **P5-03** — Board e TV (full-screen، font e bozorg، flash)
- [ ] **P5-04** — KLineCharts + toolbar e Alpine + `Interval::availableFrom()`
- [ ] **P5-05** — SEO: `seo_meta`، `SeoResolver`، JSON-LD، sitemap، `robots.txt`
- [ ] **P5-06** — Verify ba Playwright (+ check e **sefr request e biruni** tu network tab)

---

## Tasmim haye vasat e rah

| Tarikh | Tasmim | Chera |
|---|---|---|
| 2026-10-04 | `tests/Fixtures/` ghabl az Laravel sakhte shod | payload haye vaghei hamun lahze ke verify shodan zakhire shodan — ba'dan gereftaneshun mitune fargh kone |
| 2026-10-04 | `app/Modules/CLAUDE.md` va `resources/views/CLAUDE.md` be `P0-06` moghol shodan | folder eshun ba Laravel install miad |
| 2026-10-04 | SMS se driver dare (`kavenegar`/`log`/`fake`) na do ta | `fake` baraye test lazem ast — `log` IO dare va assert kardanesh sakht ast |
| 2026-10-04 | `BYPASS_OTP` alave bar neshun dadan e code، dokme ye por-kardan e khodkar ham dare | darkhast e sahebe projeh — sor'at e test e dasti |

---

## Chiz hayi ke amdan **nakardim** (ta kasi dobare pishnahad nade)

| Chi | Chera na |
|---|---|
| InfluxDB 3 | Core query ro be ~72 saat mahdud mikone؛ tarikhche mikhahe Enterprise |
| `@klinecharts/pro` | 0.1.1 ast va `solid-js` miare — ba Alpine/Livewire va token haye ma mijange |
| Filament | ba ghanun e «hich Eloquent tu laye ye UI» tanaghoz dare |
| Reverb / websocket | polling e 15s kafi-e |
| Zakhire kardan e buy/sell tu DB | spread ru **read** e'mal mishe (PLAN §1.7) |
| Backfill e tarikhche az biurn | sahebe projeh «az tick haye khodemun» ro entekhab kard (opt-in mune) |
