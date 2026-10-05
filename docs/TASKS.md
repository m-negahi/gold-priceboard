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
**Task e fe'li**: `P1-19` (`ProviderAction` / `ProviderSpreadAction`)
**Akharin kar**: `P1-18` tamum shod. **376 test، 1917 assertion، hame sabz**، Pint tamiz.
**ENGINE ZENDE-ST**: `price-worker` tick mizane، `BoardQuery` tablo ye vaghei ro ba moghayese ye do provider bar migardune.
**Nime-kare**: hich.
**Gam e ba'di**: `P1-19` — `ProviderAction` (CRUD) va `ProviderSpreadAction` ke bayad: (a) spread ha ro sync kone، (b) `provider_spread_revisions` benevise، (c) `spreadVersion` ro bump kone — har se tu YEK transaction. Ba'd `P1-20` (ServiceProvider + test e bind) va `P1-21`/`P1-22`/`P1-23`.

### 🔴🔴 MOHEM TARIN DARS: `force="true"` e phpunit KAFI NABUD
Container e `octane` `DB_CONNECTION=pgsql` va `DB_DATABASE=priceboard` ro be onvan e env e **vaghei ye OS** export mikone، va un bar `phpunit.xml` **BARANDE shod**.
Natije: `RefreshDatabase` ru database e **DEV** ejra shod va jadval e `providers` ro khali kard؛ `RedisHotCacheTest::flushdb()` ham ru db e 0 tablo ye dev ro pak kard. (Khodam vasat e kar motevajeh shodam ke `providers` khali-e.)

**Hall**: `tests/TestCase.php` config ro **BA'D az bootstrap** be zoor set mikone — unja hich env e OS-i nemitune barande beshe.
**Negahban**: `tests/Feature/GuardsTest.php` — agar ruzi kasi in ro bardare، un test fail mishe، na inke data bere.

### Raftar e vaghei ye feed (andaze-giri shode، na hads)
- **Bazaar e Iran baste = feed yakh mizane.** 10:13 e doshanbe (ghabl az baz shodan e bazaar): tgju baraye tala/sekke/dollar `ts` e **nime-shab e rooz e ghabl** mide. 14 ta az 16 symbol be dorosti `stale` rad shodan. Tablo khali nemimune — akharin gheymat e paziresh-shode ba badge e sen mimune.
- **Symbol haye jahani ye tgju ~SA'ATI be-rooz mishan**، na modam (09:19 vs 10:13). Pas override e `freshness_seconds` tu seeder **load-bearing** ast، na tazyini.
- **Dore ye vaghei ~30 saniye ast، na 15.** Tick ~11s tool mikeshe (estjt) va `next_poll_at` +15s ast، pas amalan yeki dar miyan slot tick mikhore. Lock ham dorost kar mikone («tick e ghabli hanuz dar hale ejra-st»).
- Check e arz beyn e do manba' e mostaghel: estjt ons = $4151.00، tgju ons = $4139.82 → 0.27% ekhtelaf. Vahed ha salem-and.

### 🔴 Yek rakhne ye vaghei ke peyda shod va baste shod
**Replay e spool tick e kham ro bar migardund vali candle ro NA.**
Task e `candles_1m` lookback e `-3m` dare. Agar Influx bishtar az se daghighe ghat' bashe، `drain` tick ha ro ba timestamp e ASLI (ghadimi) mineveshte — va un timestamp ha dige tu panjere ye task nabudan. Natije: `m1` (ke mostaghim az `ticks` mikhune) kar mikard vali `m5`–`d1` yek **surakh e daemi** peyda mikardan.
Hall: `InfluxTimeSeries::rebuildMinuteCandles()` ke `drain` ba'd az har replay ru hamun bazeh seda mizane.
Ta'eed: 12 tick e **20 daghighe-i** tu spool → `drain` → har 3 daghighe candle e salem ba `count=4`.

### 🔴 Andaze-giri ye vaghei ye fetch (da'va ro sanjidim، farz nakardim)
`scripts/fetch-benchmark.php` ru endpoint haye zende، 3 ejra:

| | poshte-sar-e-ham | hamzaman |
|---|---|---|
| 1 | 13.1s | 6.3s |
| 2 | 12.8s | 9.1s |
| 3 | 12.2s | 8.4s |

tgju 2.4–3.6s، estjt 6.2–9.1s (kheyli nowsan dare — bar'aks e 7.1s e avvaliye ke andaze gerefte budam).
**Ba HAMIN DO provider، poshte-sar-e-ham ~13s migire va budget 15s ast** — ya'ni provider e sevom tick ro mishkane. Hamzaman ejbari-e، na behine-sazi.

⚠️ Hamchenin: tgju gah-gah ba `cURL 35 TLS connect error` shekast mikhore (hamun khata-i ke avval ru GitHub ham didim — ehtemalan shabake ye in mohit ast، na code e ma). Pipeline durost raftar mikone (provider FAIL mikhore va baghi salem mimunan)، vali **retry baraye khata haye gozara ye TLS** arzesh e barresi dare → `P1-25`.

### Model e ejaze ye Influx (se bar gir kardim، pas benevis)
Token e `write` baraye `rebuildMinuteCandles` be HAR SE ta niaz dare:
1. `read` ru **orgs** — vagarna `404 could not find org "priceboard"`.
2. `read` ru bucket e **manba'** (`ticks`).
3. **`read` ru bucket e MAGHSAD** (`candles_1m`) alave bar `write` — Flux baraye resolve kardan e ESM e bucket، hatta bucket e maghsad e `to()`، read mikhad. Ba faghat `write`: `404 could not find bucket "candles_1m"`.

### Chiz hayi ke vasat e `P1-12` fahmidim
- **Retention e bucket vaghe'an e'mal mishe**: neveshtan ba timestamp e kharej az 30 rooz ba `422` rad mishe («violates a Retention Policy Lower Bound»). Test ha bayad timestamp e DAKHEL e retention bezanan.
- **Bar haye `m1` ru marz e DAGHIGHE mishinan** (`timeSrc:"_start"`)، na ru timestamp e 15-saniye-i e tick. Moghayese ye mostaghim e `candle.timestamp` ba `tick.timestamp()` ghalat ast.
- **Test e Influx bayad khodesh ro tamiz kone.** Baghi-munde ye yek ejra ye ghabli tu hamun daghighe mishine va `close` ro avaz mikone — yek test e felaki ye klassik. Ba `POST /api/v2/delete` ru yek tag e provider e sabet hall shod (tag e tasadofi cardinality ro bala mibare).
- **`influxdata/influxdb-client-php` amalan estefade nashod.** Hazf esh az `composer.json` yek tamiz-kari ye ekhtiari ast.

### 🔴 Do chiz ke az data ye zende fahmidim
1. **`ons`/`silver`/`copperp`/`gerami` tu feed e tgju faghat RUZANE be-rooz mishan** (`ts` = nime-shab e rooz e ghabl)، na zende. Default e `freshnessSeconds()` baraye jahani 180s ast → bedun e override har tick rad mishan. Hall tu `P1-22`.
2. **estjt ~7 saniye tool mikeshe** (tgju ~0.5s). Ba tick e 15 saniye-i، in nesf e budget ast → fetch e provider ha tu `P1-15` bayad **movazi** bashe (`Http::pool`)، na poshte sar e ham. Vagarna ezafe kardan e provider e sevom tick ro az 15s rad mikone.

### Chiz hayi ke vasat e rah yad gereftim (ta dobare gir nakonim)
- 🔴 **`docker compose restart` baraye taghir e `.env` KAFI NIST.** `env_file` faghat vaghte SAKHTE shodan e container khunde mishe; `APP_KEY` e khali hamchenan be onvan e env e OS export mishe va bar `.env` BARANDE mishe. Hatman `up -d --force-recreate`.
- **Band e motlagh khata ye 10x ro nemigire va nemitune begire.** Rial onghadr sari tavarrom dare (tala ~70 barabar tu 8 sal) ke har band e chand-sale az yek martabe ham pahn tar ast. Chizi ke 10x ro migire enheraf e tick-be-tick ast (90% ya 901%). Rakhne: avvalin tick → `P1-24`.
- **`Price::multipliedBy(float)` hazf shod.** `100 * 1.005` tu float mishe `100.49999999999999` va `round()` un ro 100 mikone. Har zarb e nesbati tu `SpreadPricer` ba mantegh e delta anjam mishe.
- **PHP `int / int` vaghti baghi-munde nadare INT bar migardune.** Baraye IRR (`scale 0`) in ya'ni series e chart int mishod va baraye USD float — cast e sarih e `(float)` ezafe shod.
- **Client e Redis e Laravel KHODESH prefix mizare.** Agar tu test dasti `config('database.redis.options.prefix')` ro jolo ye kelid bechasbuni، be yek kelid e khiali eshare mikoni va test saket be dalil e GHALAT pass mishe (do ta az test haye `RedisHotCache` daghighan hamin tur bud).
- **`microtime()` dor e `Http::pool` bi-ma'ni-st**: vaghti javab ha miyan، pool ghablan tamum shode va ma faghat parse mikonim. Latency bayad az `handlerStats()['total_time']` biad — vagarna har provider «1ms» gozaresh mishe va monitoring e panel dorugh mige. (Hamin ro tu benchmark e avval didim.)
- 🔴 **`TestCase` e Laravel method haye PUBLIC dare ke esm haye ma'muli daran**: `seed()`، `query()`، `artisan()`، `get()`، `post()`، `call()`، `withoutMiddleware()`… Yek helper e `private` ba un esm ha **fatal** mide: «Access level must be public». Se bar gir kardim (`run`، `query`، `seed`). Esm e helper ro makhsus bezar: `tick()`، `boardQuery()`، `seedPrices()`.
- **`run()` tu `PHPUnit\Framework\TestCase` FINAL ast** — method e komaki ye test ro nemishe `run()` esm gozasht.
- **`scripts/` be mount haye compose ezafe shod** — baraye script e yek-bare ye dev (`outage-drill.php`، `reset-influx-tokens.php`). Ghablan mount nabud va `php scripts/…` tu container kar nemikard.
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
- [x] **P0-11** — Commit e avval
  → `501d1af` — 125 file.

---

## Phase 1 — Pricing engine (bedun e UI)

### Domain (bedun e Docker test mishe)
- [x] **P1-01** — Enum ha: `Instrument` (15 ta symbol + unit/quote/scale/weight/freshness/sanityBand)، `Metal`، `QuoteCurrency`، `Unit`، `Side`، `SpreadMode`، `Interval`، `Direction`، `HealthState`، `UpstreamUnit`
  → `Instrument` (15 symbol)، `Metal`، `QuoteCurrency`، `Unit`، `Side`، `Direction`، `SpreadMode`، `UpstreamUnit`، `HealthState`. `Interval` hanuz nist — ba `CandleQuery` miad (`P1-18`).
- [x] **P1-02** — ValueObject ha: `Price` (minor+quote، `plus()` ba throw ru cross-currency)، `Symbol`، `Spread`، `Freshness`، `TickTime` (floor e grid e 15s)
  → `Price`، `TickTime`، `Spread`، `SpreadPair`، `SanityVerdict`. `Symbol` lazem nashod (`Instrument` khodesh enum e typed ast) va `Freshness` raft tu `Instrument::freshnessSeconds()`.
- [x] **P1-03** — Port ha + Dto ha + Fake ha
  → 5 Port: `QuoteSourcePort`، `SnapshotStorePort`، `HotCachePort`، `TimeSeriesPort`، `ClockPort`.
  → 5 Dto: `RawQuoteDto`، `SnapshotDto`، `CandleDto`، `CandleRequestDto`، `ProviderHealthDto`.
  → 5 Fake tu `tests/Support/Fakes/` ke har kodum mitunan amdan **beterkan** (`breakIt()`) ya **saket evict** shan (`evictEverything()` — shabih-sazi ye `allkeys-lru`).
  → `Interval` enum az `P1-18` jolo kashide shod (port e candle be type esh niaz dasht).
  → `PortContractTest` (27 test) + yek test e me'mari ke **be sorat e khodkar check mikone `Domain/` hich Facade/helper e Laravel nadare** (ba tokenizer comment ha hazf mishan ta docblock ha false-positive nadan).
- [x] **P1-04** — `NumberParser` + `PersianText::fold()` + test ha
  → 14 test e `NumberParser` + 7 test e `PersianText` + 13 test e `RealPayloadTest` ru fixture haye **vaghei**.
  → `RealPayloadTest` sabt mikone: `\t` e jolo ye `mesghal_buy`، bayat budan e bishtar az nesf e 963 symbol، tanha budan e jofte bid/ask e abshode، va inke ba'd az ×10 gheymat e estjt ba tgju ta 2% michasbe.
- [x] **P1-05** — `SpreadPricer` + `PriceSanity` + test ha
  → `SpreadPricer` ba 3 invariant (24 test) va `PriceSanity` (16 test).
  → `QuoteEnricher` hanuz neveshte nashode — be `P1-15` (Normalize stage) moghol shod chun be snapshot e ghabli niaz dare.

### Persistence
- [x] **P1-06** — Migration ha + `SchemaTest`
  → 6 migration، hame ejra shodan ru Postgres 18.
  → `pending_outlier_minor` ru `price_snapshots` ezafe shod (na Redis) — ta ghanun e «do outlier» ba restart e Redis gom nashe.
  → `SchemaTest` (13 test): unique e `(provider, symbol, side)`، ejaze ye bid/ask e jodagane، do provider ru yek symbol، `bigint` baraye sekke ye 2.7 miliardi، `scale` ru har radif، spread e manfi va 4 raghame ashar، cascade delete.
- [x] **P1-07** — Model ha + `EloquentSnapshotStore` + `RedisHotCache`
  → 6 Model tu `Infrastructure/Persistence/Models/` ba cast e enum. `raw_minor` cast e `integer` dare chun driver e Postgres `bigint` ro STRING bar migardune va bedun esh har `===` saket shekast mikhore.
  → `EloquentSnapshotStore`: upsert e **yek-dasturi** (test esh query log ro mishmare va assert mikone faghat YEK insert bashe — jologiri az regression e `updateOrCreate` tu halghe).
  → `RedisHotCache`: ru connection e jadid e `prices` (= container e `redis-prices`)، kol e tablo tu **yek kelid** (yek round-trip na 50 ta)، `set`+`incr` tu yek pipeline، va **bedun e TTL**.
  → `config/database.php`: connection e `prices` ezafe shod.
  → `phpunit.xml`: `REDIS_PRICES_DB=15` majbur shod ta test tablo ye dev ro pak nakone.
  → 34 test (17 store + 17 cache).

### Source ha
- [x] **P1-08** — `TgjuQuoteSource` (16 test)
  → Map e 16 kelid، `unit` **per-entry**، `ts` be onvan e vaght e Tehran parse mishe (`"2026-10-04 12:21:30"` → `08:51:30Z`).
  → **Staleness inja NIST** — raft tu stage e `Validate` (tozih e kamel tu docblock e `QuoteSourcePort`). Chun tazegi be (provider, instrument) vabaste ast، na faghat instrument.
  → Ru endpoint e **zende** test shod: 520ms، 16 quote.
- [x] **P1-09** — `EstjtQuoteSource` (16 test)
  → DOMXPath (na regex)، label e fold-shode، UA + `X-Requested-With` + Referer + **HTTP/1.1**، Toman→Rial.
  → «انس طلا» **USD** ast va baghi Toman — `unit` tu map e har instrument.
  → Test e moghayese ba tgju: har do manba' ba'd az normalize bayad ta 2% be ham nazdik bashan (check e nahayi ye vahed).
  → Ru endpoint e **zende** test shod: 9 quote. ⚠️ **7.1 saniye** tool keshid.
- [x] **P1-10** — `MockQuoteSource` (11 test)
  → **Bedun e halat**: gheymat yek TABE ye MAHZ az zaman ast (do mowj e sinusi + jitter e hash-based)، na «akharin meghdar tu Redis + delta ye random». Pas Source ghabl e test bedun e Redis ast va `final readonly` esh vaghe'an bi-halat mimune.
  → Har symbol faz e khodesh ro dare (ba `crc32`) ta kol e tablo ham-faz harekat nakone.
  → Jofte bid/ask e abshode ro ham taghlid mikone، vagarna mantegh e `is_derived` tu dev hichvaght test nemishe.
- [ ] **P1-11** — Test e **har instrument e har source** ru fixture e vaghei (meghdar e daghigh e minor)

### Influx
- [x] **P1-12** — `InfluxTimeSeries` + `Flux` + `FluxCsv` + `InfluxSpool` (57 test)
  → **Aslan SDK estefade nashod** — na baraye read، na baraye write. Har do ba `Http` facade-and (hamun stack e baghi ye app، Octane-proven). Yek kelas e kamel e khatar e Octane hazf shod va `influxdata/influxdb-client-php` amalan bi-estefade munde.
  → `Flux` ba allowlist e `[a-z0-9_-]` — 8 ta payload e hojumi test shod.
  → Har 4 tale test e makhsus daran: `timeSrc:"_start"`، `drop` ghabl az `set`، `sort→limit→sort`، `timezone.fixed(3h30m)`.
  → `FluxCsv` dasti parse mikone (yek series e `h1` e yek-sale tu SDK ≈ 44k object misazad).
  → Round-trip e **vaghei** ru Influx e zende: neveshtan + khundan + **ta'eed e idempotency** (neveshtan e dobare ye hamun tick OVERWRITE mikone، na duplicate — hamin chizi-e ke replay e spool ro emn mikone).
  → `app/Modules/Pricing/Config/pricing.php` ham neveshte shod.
- [x] **P1-13** — 6 task e Flux + `pricing:influx:provision` (10 test)
  → `resources/influx/tasks/*.flux` ba placeholder e `{{org}}`/`{{tz_offset}}`. Manba' e haghighat FILE ast، na UI — provision agar flux e deploy-shode ba file fargh dashte bashe، file ro barande mikone.
  → `InfluxAdmin` (bucket/task/token) — faghat CLI، ba token e admin.
  → Ru Influx e **zende** ejra shod: 7 bucket + 6 task sakhte shodan، va ejra ye dovom hame «bi-taghir» dad (idempotency ta'eed shod).
  → Mantegh e task ru data ye vaghei ta'eed shod: 3 daghighe tick → 3 candle ba OHLC e daghigh va `count=4`.
- [x] **P1-14** — `pricing:influx:drain` + `pricing:influx:health`
  → `drain`: replay e spool + **rebuild e candle** (paeen ro bebin). Agar neveshtan shekast bokhore، khat ha BAR MIGARDAN tu spool — test shod.
  → `health`: 5 check (dastresi، tazegi ye `candles_1m`، bar e ruzane be vaght e Tehran، hālat e task ha، andaze ye spool). `--json` ham dare.
  → `routes/console.php`: drain har daghighe، health har 5 daghighe. `pricing:tick` AMDAN inja nist (scheduler kamtarin daghighat esh 1 daghighe-ast).

### Pipeline
- [x] **P1-15** — `TickPipeline` + Stage ha + `StagePolicy` (22 test)
  → Marhale ha: `Fetch` → `Validate` → `Enrich` → `Persist` → `Publish`. («Normalize» marhale nashod — un kar e khod e Source ast.)
  → `QuoteEnricher` (az `P1-05` aghab oftade bud) neveshte shod: band e rooz ba rooz e **Tehran** reset mishe (na UTC — vagarna «بیشترین امروز» har rooz 3:30 e sobh reset mishe)، va gheymat e bi-taghir jahat ro be `flat` bar nemigardune.
  → **Fetch e HAMZAMAN** ba `Http::pool` — port `request()` gereft ta «chetor beporsim» az «chetor bekhunim» joda beshe.
  → Kol e pipeline **bedun e Docker/DB/Redis/Influx** test mishe (22 test ba fake ha).
- [x] **P1-16** — `IngestTickAction` + `TickReportDto` (11 test)
  → Lock (ba fallback: Redis ghat' → **bedun e lock edame**، chun hame ye sink ha idempotent-and)، load e provider ha ba fallback e Redis vaghti Postgres ghat' ast، sakht e Source ha، ejra ye pipeline، sabt e salamat.
  → `ProviderQuery` + `SourceManager` + `RecordProviderHealthAction` + `SystemClock` ham inja neveshte shodan (ghesmati az `P1-18`/`P1-19`).
  → `kind` be `providers` ezafe shod (migration e ezafe-shi): `reference` vs `platform` — platform ha kharid/foroosh e VAGHEI daran va nabayad marker e «محاسبه‌شده» begiran.
- [x] **P1-17** — `pricing:tick` (5 test) + `ProviderSeeder`
  → Command **nazok**: faghat yek Action seda mizane. `--json` va `--quiet-ok` dare.
  → ⚠️ Shekast e yek provider ya sink `FAILURE` **nist** — halghe ye worker nabayad be khatere yek ghat'i ye gozara motevaghef beshe.
  → Seeder ba override haye `freshness_seconds` baraye `ons`/`silver`/`copper`/`gerami` e tgju.
  → **Ru mohit e zende kar mikone**: `price-worker` har dore tick mizane va har se sink minevisan.
- [x] **P1-18** — `ProviderQuery` / `SpreadQuery` / `BoardQuery` / `CandleQuery` (38 test)
  → `BoardQuery`: Redis → **fallback e Postgres** (ham baraye ghat'i ham baraye evict e saket)، spread ru read، `isDerived`، `isStale`، ETag.
  → `CandleQuery`: cache e **do tabaghe** (taze ba TTL e interval + `:last` e 6 saate)، spread ru series، `degraded` vaghti Influx javab nade.
  → `SpreadQuery`: map ba fallback e wildcard.
  → `spreadVersion` be `HotCachePort` ezafe shod — tu kelid e cache va ETag.
  → 🔴 **`BoardChartAgreementTest`**: 6 test ke sabt mikone tablo va chart baraye hamun spread DAGHIGHAN hamun adad ro midan (percent، manfi، amount، samt e bid، va ba'd az taghir e spread). In ta'ahod e asli ye tasmim e «spread ru read» ast.
  → Ru data ye **zende** check shod: `xau-oz-usd` har do provider ro kenar e ham neshun mide (estjt 41,530,000 / tgju 41,508,700) va abshode ye tgju jofte bid/ask esh be dorosti `isDerived=false` ast.
- [ ] **P1-19** — `ProviderAction` / `ProviderSpreadAction` (ba `spread_version` bump + revision)
- [ ] **P1-20** — `PricingServiceProvider` + `Config/pricing.php` + **test e bind**
- [ ] **P1-21** — Fake ha (`InMemorySnapshotStore`، `ArrayHotCache`، `FakeTimeSeries`، `FrozenClock`) + test e kol e pipeline **bedun e Docker**
- [ ] **P1-22** — Seeder e provider ha (tgju + estjt، `is_active=false`) + spread e wildcard
  ⚠️ Bayad `freshness_seconds` ro baraye tgju override kone ru `xau-oz-usd`، `xag-oz-usd`، `xcu-lb-usd` va `xau-coin-gerami-irr` (~90000 = yek rooz): in chahar ta tu feed e tgju faghat RUZANE be-rooz mishan، vali default e `Instrument::freshnessSeconds()` baraye jahani 180s ast va har tick radeshun mikone.
- [ ] **P1-23** — Test e taab-avari: Influx down → spool؛ Redis down → PG fallback؛ PG down → polling edame
- [ ] **P1-25** — Retry baraye khata haye gozara ye shabake (`cURL 35 TLS`) tu `FetchStage`. Fe'lan har shekast provider ro baraye un tick FAIL mikone؛ ba yek retry e kutah (~300ms) aksar e in ha jobran mishan.
- [ ] **P1-24** — 🔴 **Check e cross-provider baraye AVVALIN tick** (rakhne ye kashf-shode tu `P1-05`)
  Band e motlagh khata ye 10x ro **nemigire** (tavarrom e rial — tozih tu `Instrument::sanityBand()`)، va enheraf e tick-be-tick baraye avvalin tick e yek (provider, symbol) e jadid `previous` nadare. Pas yek provider e taze ba unit e eshtebah mitune avvalin gheymat esh ro bedun e moghe'iat benevise.
  Hall: avvalin tick ba gheymat e provider haye **digar** baraye hamun symbol moghayese she (in site e moghayese ast — in data ro darim). Ekhtelaf > ~20% → rad + hoshdar e admin.

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
