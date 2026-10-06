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

**⚠️ ASSET haye UI az noskhe ye ghabli (Desktop/priceboard) avorde shodan — 2026-10-06:**
`resources/views/components/**` (form kit: input/select/jalali-date/otp-input/media-picker/…، panel kit: sidebar/modal/datatable/toasts/…، captcha، layout e panel)، `resources/js/alpine/**` (11 component: login، datatable، jalali-date، media-upload، …)، `resources/views/kit/**` (safhe haye namayesh e kit)، module e **Media** (upload + ketabkhane، 21 file) va **Panel** (dashboard + navigation، 12 file).
🔴 **Hich kodum REGISTER NASHODAN** (provider hashun tu `bootstrap/providers.php` nist، js ha import nashodan) — bi-asar-and ta Phase 3. Tu P3 bayad: review + register + test + hamahangi ba memari (Livewire e ma vs controller e una) + token haye theme. `HtmlSanitizer` (baraye rich-text) amdan nayamade — age lazem shod az Desktop biad.

**Phase**: 2 tamum (P2-08 ✓، review e code e P2-09 anjam shod) → **Phase 3**
**Task e fe'li**: `P3-01` (token haye theme) — P3-00 ba'd az layout

**🔎 CODE REVIEW e kol anjam shod — 2026-10-06 (bakhsh e review e P2-09):**
Kol e doc ha + kol e code khunde shod. 6 fix:
1. 🔴 **Fatal e penhan tu `Common\Dto`**: `TableRequestDto`/`TableResultDto` (va `MediaDto`/`UploadMediaDto`) `final readonly` budan vali az `Dto` e NON-readonly ers mibordan — avvalin autoload fatal midad (empirik ta'eed shod) + `from()` e abstract ham implement nashode bud. Hall: `extends Dto` hazf (hich chiz az base masraf nemishod)، readonly mund. Negahban: `ClassLoadabilityTest` har 162 class e `app/` ro load mikone.
2. 🔴 **Default e `sms.driver` = `log`** — driver-i ke hazf shode bud؛ bedun e `SMS_DRIVER`، avvalin send ba `unknownDriver` miterkid. Default shod `kavenegar` (loud) + test e negahban tu `SmsDriversTest`.
3. 🔴 **`DatabaseSeeder` gheyr-idempotent bud**: `User::factory()->create(['email' => 'test@example.com'])` e baghi-mande az skeleton — `db:seed` e dovvom unique violation midad. Hazf shod.
4. `login.js` shomarande ro ba raghame FARSI render mikard (`toPersian`) — khalaf e ghanun e «raghame Latin hame ja». Latin shod.
5. `BoardQuery::board()` jadval e tartib ro dakhel e HAR moghayese ye usort misakht — hoist shod.
6. Docblock e tekrari tu `TickReportDto`.
Do barresi ye baz e P2-09 (symbol-az-DB، Influx↔Timescale) **tasmim e sahebe proje** mikhan — mund baraye jalase (jozyat tu P2-09 e paeen).
**E2E (Playwright) rah oftad — 2026-10-06**: `npx playwright test` az HOST؛ `e2e/login.spec.ts` **6/6 sabz** (flow e kamel: shomare → bypass-code → auto-fill → mizkar؛ refresh ba shomarande ye server؛ code e ghalat؛ token haye `@theme` resolve mishan؛ rtl/fa/mobile bedun e overflow). `otp:flush` (local-only) + `AdminSeeder` (admin e avvaliye — MakeAdminCommand hazf shod be khaste ye sahebe proje). MCP e playwright be `.mcp.json` ezafe shod (session e badi active mishe). CLAUDE.md ba rule haye noskhe ye ghabli kamel shod (Hargez/Workflow/E2E/Zaban).
🔴 **Dars haye e2e e emruz** (hame tu TASKS e paeen sabt-and): (1) `npm run build` TANHA kafi nist — **Octane manifest e Vite ro per-worker cache mikone**، bad az har build `octane:reload` vajeb e vagarna HTML asset e hash e ghadimi (404) ro serve mikone va Alpine kolan load nemishe؛ (2) `otp-input.js` ja monde bud (Desktop ~40 alpine-component e dige ham dare — P3-00 sync kone)؛ (3) flush e e2e bayad limiter e IP ro ham pak kone vagarna run haye ghabli test haye badi ro flaky mikonan؛ (4) `SETEX key 0` cooldown ro pak NEMIKONE — `clearCooldown` e sarih be port umad.
**Akharin kar**: `P2-08` + review (2026-10-06) — **779 test (3290 assertion)، hame sabz**؛ e2e **6/6 sabz** ba screenshot e zende ye login + dashboard (`screenshots/`، git-ignore). Pint tamiz.
🔴 **Dars haye e2e e in session**: (1) **`public/hot` (Vite dev server) e2e ro flaky mikone** — dev server e sard Alpine ro dir init mikone va `fill` e Playwright ghabl az Alpine mishine، bad `:value="phone"` e khali input ro pak mikone. Ghabl az e2e: `npm run build` + `dc stop vite` + `rm public/hot` + `octane:reload`؛ bad `dc start vite`. (2) Node ru HOST nasb nabud (brew install shod — Node 26) va `node_modules` ham nabud؛ `npm install` + `npx playwright install chromium` anjam shod. (3) Admin e dev DB seed nashode bud — `db:seed --class=AdminSeeder` lazem bud (verify redirect be `/` miraft chun `is_admin=false`).
**Phase 1 amalan tamum ast** — faghat `P1-28` (marja' e e'temad) baz munde، ke AMDAN be ba'd az Phase 3 moghul shode: hall esh yek toggle ya «ta'eed e dasti» tu panel ast، pas sakhtan esh ghabl az vojud e panel ya'ni hads zadan e UI. `scripts/resilience-check.sh` ru mohit e vaghei **29/29 sabz**.
**ENGINE ZENDE-ST**: `price-worker` tick mizane، `BoardQuery` tablo ye vaghei ro ba moghayese ye do provider bar migardune.
**Nime-kare**: hich.
**Gam e ba'di**: `P2-08` — test haye takmili (rate limit e kamel az HTTP، enumeration az HTTP، `TrustProxies`+OTP ba ham) + screenshot e Playwright/Chrome (in bar nashod — extension nasb nabud؛ smoke e curl sabz bud). Ba'd `P2-09`.
**⚠️ `P1-22` (Seeder) ghablan tu `P1-19` anjam shod** — seeder az Action ha rad mishe va re-seed gheyr-mokhareb ast.

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
- 🔴🔴 **FAKE bayad GHARARDAD ro emza kone، na yek nasakhe ye MEHRABUN TAR az un.** `ArrayHotCache::putBoard()` MERGE mikard dar hali ke `RedisHotCache` yek `SET` ru yek kelid mizane (JAYGOZIN). Un vagerai yek bug e vaghei ro az **cheshm e kol e test suite** penhan kard: tu production tablo har shab AB MIRAFET، vali tu test fake merge mikard va tablo hichvaght kuchik nemishod — pas hich test-i natunest un ro bebine. Ba **mutation check** kashf shod (kharab kardan e `wholeBoard` hich test-i ro ghermez nakard)، na ba khundan e code. Alan gharardad tu docblock e port sarih neveshte shode.
- ⚠️ **Commit message ro HAMISHE ba heredoc e quote-shode (`-m "$(cat <<'MSG' … MSG)"`) bede.** Yek bar ba `-m "..."` e sade dadam va shell backtick haye dakhel e matn ro EVAL kard — message e commit bi-seda kharab shod (kalame haye beyn e backtick ha hazf shodan) va majbur be amend shodim.
- ⚠️ **`str.replace()` e bi-assert tu script e edit e file، SAKET hichi nemikone.** Do bar `docs/TASKS.md` ro «be-rooz kardam» (P1-20 va P1-21) va hich taghiri sabt nashod chun matn e needle farg dasht — checkbox ha `- [ ]` mundan va yaddasht ha gom shodan. Hamun class e bug e «test e sabz ke hichi check nemikone». Tu har script e edit، `assert old in s`.
- 🔴 **Test e jadid ro ba MUTATION besanj، na ba sabz budanesh.** Tu `P1-20` yek test neveshtam ke store e cache e candle ro check mikard — sabz bud، vali vaghti bind ro amdan kharab kardam **baz ham sabz mund**: `pricing.cache_store` ro `array` gozashte budam ke hamun default e test bud، pas `->store('array')` va `->store()` az ham ghabl e tashkhis nabudan. Ghanun: tu test، meghdar e config bayad ba default FARGH kone. Do ta test e digar ham hamin tor bi-ma'ni budan: `assertNotContains('pricing:tick', …)` ru yek array e KHALI pass mishod (regex e ghalat).
- ⚠️ **`git checkout -- <file>` ru file e commit-nashode = az dast raftan e kar.** Vasat e mutation check az `git checkout` baraye bargashtan estefade kardam، va un file ro be akharin COMMIT bargardund — ya'ni hame ye taghirat e commit-nashode ye `P1-20` ru `PricingServiceProvider` pak shod. Baraye bargashtan e movaghat `cp` be yek file e backup bezan، na `git checkout`.
- 🔴 **Validation ro ba config e VAGHEI ye projeh miyzan kon، na ba hads.** Do «invariant» neveshtam ke har do config e khod e seeder ro rad mikardan va test e khodam un ha ro gereft:
  (1) «`timeout` bayad az `poll_interval_seconds` kamtar bashe» — estjt `timeout = 20` ba fasele ye 15s dare chun un endpoint andaze-giri shode 6.2–9.1s javab mide. Un shart eshtebah bud: halghe ye worker ba `sleep $((15 - $(date +%s) % 15))` ru grid ham-taraz mishe، pas tick e 20 saniye-i YEK tick ja mindaze، na drift e daemi. Jash saghf e motlagh e 60s neshast (`Http::pool` montazer e HAME mimune، pas timeout e 300s ya'ni 5 daghighe bi-gheymat az HAME ye provider ha).
  (2) «tazegi <= 86,400» — seeder baraye symbol haye jahani ye tgju **90,000** mizare chun un ha faghat RUZANE be-rooz mishan. Saghf shod yek hafte.
  Dars: **seeder ro az hamun Action rad bede** ta config e ghabl e ghabul mohal beshe — alan hamin tor ast.
- ⚠️ **Pint `fully_qualified_strict_types` mitune file e `scripts/` ro beshkune**: FQN ro kutah kard vali `use` e un file BA'D az masraf bud. PHP import ha ro az noghte ye e'lam be ba'd e'mal mikone، pas `use` e ba'd az masraf kar nemikone — `scripts/board-probe.php` fatal shod. `use` ha bayad bala ye file، ghabl az `require` ha bashan.
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
- [x] **P1-11** — `SourceFixtureMatrixTest` — **har instrument e har source، meghdar e DAGHIGH e minor** (29 test)
  → Jadval e kamel: tgju **16** quote (shamel e jofte bid/ask e `xau-melted-g-irr` va `xag-925-g-irr` ke ghablan HICH assert e meghdari nadashtan)، estjt **9** quote.
  → 🔴 **Mohafez e kamel budan** (`test_*_matrix_is_exhaustive`): majmu'e ye kelid haye jadval ba majmu'e ye kelid hayi ke Source VAGHE'AN az fixture misaze moghayese mishe. Pas map e jadid bedun e radif e jadval -> FAIL. Bedun e un، yek `foreach` ru jadval faghat chizi ro test mikone ke ghablan neveshte shode — ya'ni symbol e jadid SAKET bi-test mimune (hamun class e «test e sabz ke hichi check nemikone»).
  → 🔴 **Moghayese ye do manba' e MOSTAGHEL**: har symbol e moshtarak (9 ta) bayad zir e 3% ekhtelaf bashe. Ghavi-tarin mohafez e in file chun do source kamelan mostaghel-and (JSON e rial vs HTML e toman ba separator e `٫`). Lista SAKHT-CODE nashode — az eshterak e do fixture hesab mishe. Andaze-giri: bishtarin ekhtelaf 1.28% (rob' e sekke — premium e maghaze-i). Test e ghabli faghat **3 az 9** symbol ro pushesh midad.
  → `scale` e har quote ba `instrument->scale()` moghayese mishe — vagarna yek adad e USD ba scale 0 zakhire mishe va 10,000 barabar kuchik mishe.
  → Adad ha az do file e test hazf shodan va yek ja jam' shodan (do ja baraye be-rooz kardan ya'ni yeki aghab mimune).
  → **Mutation check**: hazf e yek radif e jadval -> 1 fail؛ bardashtan e `×10` e estjt (hamun khata ye 10x) -> **10 fail**؛ `scale` e USD -> 0 -> **29 fail**.

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
- [x] **P1-19** — `ProviderAction` / `ProviderSpreadAction` (62 test)
  → `ProviderAction`: se invariant ke validation e form NEMITUNE bede (be halat e ghabli ya be code e app vabaste-and): **`key` ba'd az sakht GHOFL ast** (hoviat e tag e Influx + kelid e Redis + `price_changes`؛ Influx 2 rename e tag nadare pas avaz kardanesh kol e tarikhche ro YATIM mikone)، `driver` bayad tu `SourceManager` sabt bashe، va taghir e `poll_interval_seconds` bayad `next_poll_at` ro SEFR kone.
  → `setFlags()` jodagane: faghat 4 flag؛ yek toggle e lista nabayad hoviat ya zamanbandi ro avaz kone. Fa'al kardan e dobare `health_state` ro reset mikone (vagarna panel provider ro ghermez neshun mide ghabl az avvalin talash).
  → `ProviderSpreadAction::sync()`: upsert + revision + bump، **(a)(b) tu yek transaction، (c) BA'D az commit** — bump e ghabl az neveshtan yek reader ro vadar mikone spread e ghadim ro zire version e JADID cache kone (javab e ghalat ta akhar e TTL)؛ bump e ba'd az commit bad tarin halatesh yek cache miss ast.
  → **Save ye bi-taghir hich revision va hich bump nemizane.** Form 15 radif (har `Instrument::cases()`) mide؛ bedun e in moghayese har taghir e yek symbol، 14 revision e ja'li minevise va kol e cache e chart ro batel mikone. `comparable()` ba 4 raghame ashar `round` mishe chun soton `decimal(12,4)` ast — vagarna `0.50001` har bar «avaz shode» be nazar mirese.
  → `sync()` radif haye na-amade ro **hazf NEMIKONE**؛ hazf faghat ba `remove()` e sarih، va `remove()` yek revision e `is_removal` minevise (migration e ezafe-shi) — hazf e override gheymat e namayeshi ro AVAZ mikone pas bayad tu daftarche bashe.
  → `ProviderKind` enum jaye string e kham: `BoardQuery` ba `!== 'platform'` moghayese mikard، yek typo tu DB hame ro saket «moshtagh» mikard. `providerKinds()` dige `pluck()` nemizane (pluck cast e Eloquent ro rad mikone).
  → 🔴 **`EloquentSnapshotStore::all()` alan faghat provider haye FA'AL (va soft-delete-nashode) ro mide.** `join` e kham scope e `is_active`/SoftDeletes ro nemibine، pas masir e fallback e Postgres provider e khamoosh ro bar migardund dar hali ke masir e Redis un ro nemide — do masiri ke bayad eyn e ham bashan، daghighan tu bad tarin lahze (ghat'i ye Redis) vagera mishodan. Se test tu `BoardQueryTest`.
  → 🔴 **Seeder alan az HAMUN Action ha rad mishe** — va `is_active` va spread haye MOJUD ro dast nemizane (ghablan `db:seed` har provider-i ke admin roshan karde bud ro khamoosh mikard va hame ye spread ha ro sefr). (ba `spread_version` bump + revision)
- [x] **P1-20** — `PricingServiceProvider` + `Config/pricing.php` + `PricingBindingTest` (33 test)
  → Se bind e sarih: `IngestTickAction`، `ProviderSpreadAction`، `CandleQuery`. Har se yek param e `?LoggerInterface` daran va autowire un ro be channel e **default** resolve mikone، na `pricing` — pas `providers_unavailable`، `lock_unavailable`، `health_write_failed` va `spread_version_bump_failed` tu log e OMUMI mioftadan. Yek shekast e saket e monitoring.
  → `pricing.log.enabled` hala vaghe'an khunde mishe → `NullLogger`. Un kelid tu config bud vali hichvaght khunde nemishod (`PRICING_LOG=false` hich kari nemikard).
  → `pricing.cache_store` e jadid. `CACHE_STORE=database` chart ro mindakht ru **Postgres** — hamun DB-i ke cache baraye mohafezat azash hast (series e `h1` e yek-sale = 8,760 candle).
  → Channel e `pricing` `'name' => 'pricing'` gereft — bedun esh Monolog esm esh ro az `app.env` migereft va har record «local» neshun mid.
  → Test ha: port → adapter، **memoization**، har 15 chiz e module sakhte mishe، tartib e Stage ha، channel e logger، store e cache، merge e config، zamanbandi. 🔴 **SDK e Influx hargez bind nemishe** va 🔴 **`pricing:tick` hargez zamanbandi nemishe** (`drain`/`health` bayad bashan).
  → **Ba mutation check sanjide shod** (5 kharab kardan e amdi). Yek test SABZ mund vaghti bind ro kharab kardam — `pricing.cache_store` ro `array` gozashte budam ke hamun default e test bud. Eslah shod.
- [x] **P1-21** — Takmil e fake ha + `FetchStageTest` (+27 test)
  → 🔴 **`FetchStage` hich test nadasht.** `TickPipelineTest` (vahed، bedun e Laravel) un ro amdan kenar mizasht chun `Http::pool` facade mikhahe. `FetchStageTest` (18 test) ba `Http::fake()` + `preventStrayRequests()`: non-2xx، body e non-JSON ba HTTP 200، `ConnectionException` az pool، throw vasat e parse، masir e POST، `payloadHash` az body e kham، label haye unmapped، masir e mostaghim vs pool، va **izole budan**.
  → `FakeQuoteSource`: `pooled()` / `posting()` (ghablan `request()` hamishe `null` bud pas masir e pool az fake ghabl e dastres nabud)، `unmapped()`، `failsOnce()`، `lastPayload()`، `parseCount()`.
  → 🔴 **`failWritesOnly()`** ru `ArrayHotCache` va `FakeTimeSeries` — write miterke، read salem. `redis-prices` ba `noeviction` daghighan hamin ro mikone. Ghablan fake ha faghat «hame chiz ghat'» ro model mikardan.
  → Se test e jadid tu `TickPipelineTest`: OOM e Redis، rad shodan e write e Influx ba 422، va bazyabi ye khodkar.
- [x] **P1-22** — Seeder e provider ha — **tu `P1-19` anjam shod** (`ProviderSeederTest`، 7 test)
  → Seeder az hamun Action haye panel rad mishe، pas config e ghabl e ghabul mohal ast. `is_active` va spread haye MOJUD dast nemikhoran (ghablan `db:seed` har provider-i ke admin roshan karde bud ro khamoosh mikard va hame ye spread ha ro sefr mikard).
  ⚠️ Bayad `freshness_seconds` ro baraye tgju override kone ru `xau-oz-usd`، `xag-oz-usd`، `xcu-lb-usd` va `xau-coin-gerami-irr` (~90000 = yek rooz): in chahar ta tu feed e tgju faghat RUZANE be-rooz mishan، vali default e `Instrument::freshnessSeconds()` baraye jahani 180s ast va har tick radeshun mikone.
- [x] **P1-23** — Taab-avari ru mohit e VAGHEI: `scripts/resilience-check.sh` + `scripts/resilience-probe.php` (**30 check، hame sabz**)
  → Se senario: `influxdb` ghat' → spool por (33 khat) → `start` + `drain` → **gap por shod**؛ `redis-prices` ghat' → tablo be Postgres fallback kard va khodesh ro `degraded` e'lam kard؛ `postgresql` ghat' → tick **amdan** rad mishe vali tablo az Redis zende mund.
  → ⚠️ Senario C ba matn e `docs/PLAN.md` farg dare: plan goft «polling edame»، vali implementation amdan tick ro rad mikone chun stage e load e snapshot `critical` ast — bedun e gheymat e ghabli، `PriceSanity` (enheraf) va `QuoteEnricher` khamoosh mishan. Neveshtan ba mohafez haye khamoosh BADTAR az nanveshtan ast: yek khata ye 10x mirese be Influx va unja DAEMI mishe. Chizi ke vaghe'an mohem ast — zende mundan e site — barghararast.
  → 🔴 **`pricing:tick --force`** ezafe shod. Bedun esh yek tick e DASTI ke bala-fasele ba'd az tick e worker ejra she «hich provider e fa'ali baraye poll nist» mide (chun `next_poll_at` 15s jolo-st) — un payam mesl e yek khata ye **config** be nazar mirese، na «15 saniye sabr kon». Va daghighan hamun ravesh e kari-e ke skill e `add-price-provider` mige. Grid dast nemikhore (timestamp hanuz floor mishe → idempotent).
  → 🔴 **Shekl e JSON e `TickReportDto`**: `sinks`/`providers`/`unmapped` hala HAMISHE object-and. `json_encode` array e khali ro `[]` mikard va array e kelid-dar ro `{}` — ya'ni `sinks` do shekl e motefavet dasht va `sinks.influx` tu yek tick e rad-shode aslan ghabl e address dadan nabud. Phase 4 in payload ro MIFROUSHE. `rejections` amdan list mimune.
- [x] **P1-25** — Retry baraye khata haye GOZARA tu `FetchStage` (+21 test)
  → `Domain/Services/TransientFailure.php` — siasat e retry، PURE (match ru MATN e khata، na ru class، chun `Domain/` nabayad `ConnectionException` e Illuminate ro bebine).
  → **Retry mishe**: faghat khata haye sath e ETTESAL ke SARI' miterkan — `cURL 6` (resolve)، `7` (connect)، **`35` (TLS — hamun ke vaghe-an didim)**، `52` (empty reply)، `56` (reset). Handshake e TLS tu ~100-500ms miofte، pas talash e dobare amalan majani-e.
  → 🔴 **Retry NEMISHE**: `cURL 28` (**timeout**) — mohem tarin tasmim e in task. Timeout ya'ni budget e KAMEL e un provider ghablan kharj shode (`estjt` 20 saniye!)، pas retry un ro DO barabar mikone: gerun tarin shekast، bad tarin kandida baraye takrar. Hamchenin 5xx/429 (300ms ba'd hamun javab ro midan va yek round-trip e 6-9s kharj mikonan)، 4xx (eshtebah e CONFIG، na ghat'i)، va khata ye parse (talash e dobare DAGHIGHAN hamun javab ro mide — bayad DIDE she، na takrar).
  → 🔴 **Retry ham HAMZAMAN ast**: hame ye provider haye shekast-khorde tu YEK pool e dovom، na poshte sar e ham. Vagarna retry khodesh tabdil mishe be hamun fetch e seri ke `Http::pool` baraye hazf esh sakhte shode.
  → 🔴 **Timeout e talash e dovom be budget e BAGHI-MUNDE clamp mishe** (`min(request.timeout, remaining)`)، va zir e 1.5s aslan retry nemishe. Bedun e in، retry mitune tick ro az grid rad kone — ya'ni baraye jobran e yek surakh tu YEK series، yek surakh tu HAME ye series misazim (chun `Http::pool` montazer e hame mimune va halghe ye worker slot e ba'di ro ja mindaze). Ru mohit e zende: tick 7.75s tool mikeshe، pas retry ~7s ja dare.
  → `ProviderHealthDto::$attempts` (+ `retried` tu payload). Provider-i ke HAR tick retry mikhahe dar hale KHARAB SHODAN ast — un ro ba yek «salem» e sade neshun dadan، hamun degradation e aram ro penhan mikone. **Soton e DB ezafe nashod** amdan (jadval e `providers` dampened ast ta 23k update e ruzane nakhore)؛ adad tu report + log e `pricing` hast va `TickMonitor` (`P3-09`) un ro neshun mide.
  → Config e `pricing.fetch.max_attempts` / `retry_delay_ms`. `max_attempts = 1` kamelan khamoosh mikone (test esh hast).
- [x] **P1-27** — 🔴 **yakh-zadegi ye SAKET e tablo** — hall shod ba **zarabān e nevisande**
  → `HotCachePort::putHeartbeat()` / `heartbeatAt()`. `PublishStage` har tick un ro mineviseh. Zarabān KHODESH yek write ast، pas tu halat e OOM (`noeviction` + memory e por) be-rooz NEMISHE — va hamin un yakh-zadegi ro ghabl e kashf mikone، dar hali ke `isAvailable()`، `board()` va `PING` hame salem be nazar mirasidan.
  → `BoardQuery` agar zarabān az `pricing.board.freeze_after_seconds` (120s) ghadimi tar bashe، store e dagh ro ghabl e e'temad nemidune → Postgres + `degraded=true`. Bonus: **worker e morde** ro ham hamin tor mifahmim.
  → ⚠️ Zarabān tu `PublishStage` ast، na `PersistStage`: un yeki vaghti hich snapshot va hich hold nabashe FORI bar migarde — ya'ni har SHAB (bazaar e baste، hame `stale`) zarabān motevaghef mishod va false alarm mikhordim.
  → ⚠️ «Hame ye radif ha bayat-an» ba «hich kas nemineviseh» YEKI NIST — test e jodagane dare.
- [ ] **P1-28** — «marja' e e'temad» (`trust anchor`). `P1-24` yek labe ye tiz dare ke test esh hast (`test_whoever_arrives_first_becomes_the_reference`): agar yek provider e GHALAT tu halat e bootstrap avval ja bioftad، provider e DOROST e ba'di rad mishe. Fe'lan jologiri AMALIATI-e (seeder `is_active=false`، roshan kardan e yeki-yeki + check e `TickMonitor`، va radd ba barchasb e `cross_provider:` tu panel ma'lum ast). Hall e code-i: yek flag e `is_trust_anchor` ru `providers` ke faghat un provider be onvan e marja' hesab beshe — ya ejaze ye «ta'eed e dasti» tu panel.
- [x] **P1-26** — tablo radif haye BAYAT ro negah midare، va `hide_after_seconds` ru READ e'mal mishe
  → `PersistStage::wholeBoard()`: tablo = **akharin-ma'lum e HAME** overlay shode ba chizi ke in tick umad. Ghablan faghat `snapshots` neveshte mishod، pas shab ha (bazaar e baste، aksar e quote ha `stale`) kelid e tablo AB MIRAFET dar hali ke Postgres akharin-ma'lum e hame ro dasht — do masiri ke bayad eyn e ham bashan، har shab vagera mishodan. Alan Redis BE SAKHT mirror e Postgres ast (test: `test_the_hot_board_and_postgres_hold_the_same_rows`).
  → 🔴 **`hide_after_seconds` NESBI ast، na motlagh**: `hide = effectiveFreshness(instrument) + hideAfterSeconds`. Do dalil ejbari: (a) default haye jadval MA'KUS-and (`freshness=900`، `hide_after=600`) — ba ma'ni ye motlagh radif tu 600s makhfi mishod GHABL az inke tu 900s «bayat» e'lam she، ya'ni badge e bayat HICHVAGHT dide nemishod؛ (b) tazegi PER-INSTRUMENT ast (`xau-oz-usd` e tgju 90,000s) — yek saghf e motlagh e 600s un symbol ro BARAYE HAMISHE makhfi mikard.
  → E'mal ru **READ** tu `BoardQuery`، na ru write: sen har saniye avaz mishe pas nabayad bake beshe، va admin ke tanzim ro avaz kone bayad FORI asar bebine. (Hamun mantegh e spread.)
  → Ru mohit e zende: 24 radif tu kelid، `BoardQuery` 22 ta mide (2 ta makhfi shodan)، 1 radif ba badge e bayat، `degraded=false`.
- [x] **P1-24** — 🔴 **Check e cross-provider baraye AVVALIN tick** (+16 test)
  → `PriceSanity::crossProvider()` — pure، static. `ValidateStage` faghat vaghti seda mizane ke `previous` NABASHE.
  → Rakhne: band e motlagh khata ye 10x ro **nemitune** begire (tavarrom e rial band ha ro az yek martabe pahn tar karde) va enheraf e tick-be-tick be `previous` niaz dare ke avvalin tick nadare. Pas provider e taze ba vahed e eshtebah avvalin gheymat esh ro bedun e moghe'iat minevesht — va chun tick haye ba'di ba hamun meghdar e ghalat SAZEGAR-and، hargez kashf nemishod: yek dorugh e DAEMI.
  → **Saghf 20%**: provider ha vaghe'an ba ham farg daran (andaze-giri ye zende: ons 0.27%، tala ye 18 ayar 0.26%)، vali chizi ke bayad gerefte she az yek martabe bozorgtar ast (toman jaye rial = 90%، khata ye 10x = 900%). Ru tick e zende: **sefr radd e cross_provider** — noise nemisaze.
  → **MIANE، na miangin**: miangin ba yek marja' e kharab kharab mishe (yek marja' e 10 barabari miangin ro ~4 barabar mikone va un vaght gheymat e DOROST rad mishe). Miane ba yek outlier tekun nemikhore.
  → `holdAsOutlier` nemide: ghanun e «do outlier» be yek snapshot e ghabli niaz dare ke hamun chizi-e ke nadarim، va >20% ekhtelaf ba bazaar yek eshtebah e CONFIG ast (vahed، map، soton)، na harekat e bazaar.
  → **Faghat avvalin tick**: provider e ja-oftade hargez cross-check nemishe — vagarna yek maghaze ye geran ba'd az chand rooz kamelan khamoosh mishe. Un kar e check e enheraf ast.
  → **Ba mutation check sanjide shod، va DO ta az test ha bi-ma'ni budan**: (a) test e «provider e ja-oftade cross-check nemishe» ba yek drift e 7.7% neveshte bude budam — un zir e saghf e 20% bud pas ba ejra shodan e check ru HAR tick ham sabz mimund؛ eslah: se gam e +8% (har kodum zir e saghf e enheraf) ta jam' esh ~26% beshe. (b) Test e «marja' e khodesh hesab nemishe» hargez farg nemizasht.
  → ⚠️ **Shart e «marja' e khodesh» amdan test NASHODE** va tu docblock sadeghane neveshte shod: mutation check neshun dad bardashtan esh hich test-i ro ghermez nemikone، chun miane ba do marja' natije ro barnemigardune VA yek provider aslan nemitune chand radif e ghalat ja bioftad vaghti marja' e durost vojud dare.

---

## Phase 2 — Auth (mobile + OTP)

- [x] **P2-01** — Middleware ha + helper ha (+76 test)
  → ⚠️ Panj middleware va do helper **ghablan tu P0 neveshte shode budan** (662 khat) vali **sabt nashode budan va SEFR test dashtan**. `SecurityHeaders` kolan nabud.
  → 🔴 `TrustProxiesTest` (16 test) — in test AMNIATI-e، na raftari: agar XFF eshtebah parse she، yek attacker ba yek header rate limit e IP e OTP ro dor mizane. Pushesh: ja'l az manba' e GHEYR e mored e e'temad (hame ye 4 header nadide gerefte mishan)، zanjire ye chand-laye (az RAST khunde mishe)، `X-Real-IP` e bazneveshte-shode ba IP e khosusi، entry e ja'li ye prepend-shode، CIDR e IPv4 (marz e `/30`)، IPv6 (`::1`، `fc00::/7`، public)، `REMOTE_ADDR` e nabud.
  → 🔴 `LatinizeDigits` **fail-CLOSED** shod: ghablan shart «agar content-type MA'LUM ast va html nist rad kon» bud، pas pasokh e BEDUN e header dast mikhord. Baraye Phase 4 (API ba HMAC) un yek bomb bud — avaz kardan e yek raghame vasat e body emza ro mishkune va payam mishe «emza ghalat» bedun e hich sarnakh.
  → `SecurityHeaders` e jadid. **HSTS faghat ru HTTPS**: frestadanesh ru `http://localhost:8300` un domain ro baraye MAH ha HTTPS-only mikone va dev e hame ye projeh haye digar ru hamun `localhost` ro misheknae. CSP amdan NIST — ba nonce lazem ast (Alpine/Livewire inline script daran) va yek CSP e `unsafe-inline` hess e amniat mide bedun e amniat؛ tu `P3-03` miad.
  → `PhoneHelperTest` (26 test): 11 shekl e vorudi ye vaghei hame be YEK rishte miresan (`users.phone` `unique` ast va hoviat e login — do khoruji ya'ni yek nafar DO hesab). Tartib e `0098` ghabl az `98` test shod. `mask()` baraye log.
  → `StringHelperTest` (10 test): `ي`/`ی` do codepoint-and va `=` eshun barabar nist — bedun e normalize، jostoju ye Farsi natije nemide.
  → **Hame ye comment ha/docblock haye English be Finglish tabdil shodan** (ghanun e #1) — shamel e `BaseAction`/`BaseQuery`/`Dto` ke az P0 munde budan.
  → Esm e `fullBytes` tu moghayese ye CIDR e IPv6 ghalat bud (vaghe'an NIBBLE ast) — eslah shod.
- [x] **P2-02** — Sabt tu `bootstrap/app.php` + `MiddlewareOrderTest` (11 test)
  → 🔴 **`TrustProxies` JAYGOZIN shod، na EZAFE** (`replace()`). Framework khodesh un ro tu stack e global dare (jaygah e 3)؛ `prepend` kardan e male ma ya'ni HAR DO ejra mishan va `REMOTE_ADDR` do bar bazneveshte mishe. `replace()` jaygah ro ham hefz mikone — bayad ghabl az `HandleCors` bashe.
  → 🔴 **Bug e vaghei ke peyda shod**: `NormalizeRouteParams` ro aval `append` kardam va jaygah e **10** shod — BA'D az `SubstituteBindings` (7). Un amalan middleware ro BI-ASAR mikone: route-model binding ba parameter e KHAM ejra mishe، pas `/providers/۱۰۲۲` ghabl az normalize shodan 404 mide. `prepend` shod. Do test: yeki tartib، yeki RAFTAR (binding ba shenase ye Farsi bayad model ro peyda kone).
  → 🔴 **`LatinizeDigits` PREPEND shod، na APPEND** (zed e shohud): pipeline tu-dar-tu-st، pas code e ba'd az `$next()` baraye AVVALIN middleware AKHAR ejra mishe. Yek filter e khoruji bayad HTML e NAHAYI ro bebine، pas bayad BIRUNI TARIN bashe.
  → `SecurityHeaders` global (web + API). Normalize ha faghat `web` — ru `api` amdan nist: yek API e foroshi bayad ghararad e SARIH dashte bashe، na input-i ke saket bazneveshte mishe (tasmim tu Phase 4).
  → **Mutation check har 4 tartib ro gereft**: LatinizeDigits -> append (1 fail)، RouteParams -> append (2 fail)، TrustProxies -> prepend (1 fail)، NormalizeString ba'd az Phone (1 fail).
  → ⚠️ Yek test e khodam OVER-CLAIM mikard: «middleware e daxeli» e man sath e ROUTE bud، pas hamishe dakhel e group ejra mishe va ba `append` ham sabz mimund. Docblock esh eslah shod ta begire chi ro vaghe'an sabt mikone.
  → Ru mohit e zende ta'eed shod (ba'd az `octane:reload`): 5 header e amniati ru `/up`، va **HSTS ru HTTP NIST**.
- [x] **P2-03** — Migration e `users` + model e `User` tu module e Auth (9 test)
  → Migration e EZAFE-shi: `phone` e **unique NOT NULL** (E.164، 16 char)، `phone_verified_at`، `is_admin` (default false)، `last_login_at`؛ va `name`/`email`/`password` **nullable** shodan — karbar ba phone+OTP sabt-e-nam mikone va tu un lahze hich kodum ro nadare. Migration ru jadval e POR amdan fail mishe (karbar e bi-hoviat nabayad saket sakhte beshe).
  → `User` az `app/Models` be `app/Modules/Auth/Infrastructure/Persistence/Models` montaghel shod (ghanun e me'mari: model faghat tu `Infrastructure/Persistence`). `config/auth.php`، `DatabaseSeeder` va `UserFactory` be-rooz shodan.
  → 🔴 **`is_admin` FILLABLE NIST** + test e sarih: yek `$user->update($request->all())` e bi-deghat tu HAR controller e ayande kafi-e ta privilege escalation beshe. Set kardanesh faghat ba `forceFill()`.
  → 🔴 `is_admin` **default e sath e MODEL** ham dare (`$attributes`)، na faghat DB: bedun e un، `User::create()->is_admin` ta `refresh()` `null` ast va check e authorization ru instance ye taze javab e ghalat mide. (Test khodesh in ro gereft.)
  → Factory default esh karbar e VAGHEI ye OTP ast (phone e verify-shode، bedun e password/email) — ta test haye ayande na-khaste karbar e email-dar nasazan. `admin()` ba `forceFill`.
  → `password`/`remember_token` tu `$hidden` + test (model mostaghim tu payload e Livewire miad).
- [x] **P2-04** — `OtpStorePort` + `RedisOtpStore` + `OtpCode` (33 test)
  → `OtpCode` (Domain، pure): tolid ba `random_int(0, 10^d - 1)` + pad — **sefr e avval mojaz** (`random_int(100000, 999999)` 10% e fazaye kelid ro hazf mikone؛ test e amari esh hast). Hash = **HMAC ba APP_KEY** (na sha256 e khali — code e 6 raghami faghat 10^6 halat dare va sha256 e khali offline tu millisecond brute mishe؛ HMAC ya'ni dump e Redis bedun e APP_KEY bi-estefade-ast). Phone tu HMAC hast (hash e yek shomare baraye shomare ye digar replay nemishe). Verify ba `hash_equals`. Kelid e khali throw mikone.
  → `RedisOtpStore` ru Redis e **omumi** (OTP cache e vaghei-e — evict ya'ni «dobare befrest»). Code-hash + shomaresh e talash tu **YEK kelid** (ba ham expire/evict mishan — hich «shomaresh e bi-code» ya «code e bi-shomaresh»). `recordFailedAttempt` ba **Lua ye sharti**: `HINCRBY` ru kelid e nabude kelid e YATIM e bi-TTL misaze.
  → 🔴 **`OtpStoreContractTest`: har raftar ru HAR DO implementation** (Redis e vaghei + fake) — e'mal e mostaghim e dars e P1-26 («fake bayad gharardad ro emza kone»). Va hamun avvalin ejra yek bug e VAGHEI gereft: prefix e dasti tu EVAL — **phpredis khodesh KEYS haye EVAL ro ham prefix mizane**، pas kelid do bar prefix mikhord va `EXISTS` hichvaght peyda nemikard. (Hamun dars e Redis e P1، variant e EVAL. Empiri ta'eed shod.)
  → `put` e jadid shomaresh ro ham **sefr** mikone (resend = shoru' e kamel)؛ cooldown **mostaghel** az entry (batel shodan e code ghofl e resend ro baz nemikone).
  → ⚠️ `.env` e dev `BYPASS_OTP=true` dare va container un ro OS-env mikone — `TestCase` hala config e OTP ro ham mikhkube (hamun masir e ma'ruf e DB). Esm e env ha ba `.env` e mojud e P0 yeki shodan (`OTP_EXPIRES_IN`، `OTP_LENGTH`).
  → `AuthServiceProvider` + sabt + `AuthBindingTest` (hamun ghanun e bind-test).
- [x] **P2-05** — `SmsPort` + se driver + `SmsManager` (14 test)
  → Port **FAGHAT `sendOtp(phone, code)`** — na `send(text)` e omumi: matn-sazi tu laye haye bala ya'ni code tu string ha va log ha michake، va `verify/lookup` e Kavenegar (template) tahvil esh az matn e azad behtar ast. Method e jadid rooz-i ke payam e gheyr-OTP lazem shod.
  → `KavenegarSms` ba do dam e MA'LUM (az hg_website): 🔴 **khata ha ba HTTP 200 miyan** (`return.status` e body check mishe — vagarna shomare ye namotabar va e'tebar e tamam-shode har do «movafagh» dide mishan) va 🔴 **API key tu URL ast** (`ConnectionException` e Laravel URL e kamel ro tu payam esh dare — exception e ma HICH jozyati pass nemide؛ test e leak dare). Receptor ba format e MAHALLI (`PhoneHelper::toLocal`).
  → `FakeSms` tu **app** ast na tests (khaste ye asli: `SMS_DRIVER=fake` gozine ye vaghei ye env ast) + `assertSentTo`/`lastCodeFor`/`failWith`. 🔴 Singleton — vagarna instance e action va instance e test yeki nistan va har assert «hichi ferestade nashode» mide (test esh hast).
  → `LogSms` (dev): tanha ja-i tu kol e system ke code e plaintext sabt mishe — amdi، chun dev SMS nadare.
  → `SmsManager`: driver e nashenas hamin ja miterke (typo ye `SMS_DRIVER` nabayad ta avvalin login penhan bemune).
  → 🔴 **Bug e vaghei tu `PhoneHelper::mask()` peyda shod**: vorudi-i ke ba E.164 match nemishod DAST-NAKHORDE barmigasht — fail-OPEN baraye method-i ke kol e karesh amn kardan e log ast. Alan fail-CLOSED: har raghame match-nashode setare mishe. (Test e `KavenegarSms` gereft esh.)
- [x] **P2-06** — `SendOtpAction` / `VerifyOtpAction` + `OtpThrottle` (22 test، 4 mutation gerefte shod)
  → `OtpThrottle`: phone **va** IP jodagane (faghat phone → attacker ba shomare haye jadid SMS-khoone ro khali mikone؛ faghat IP → botnet yek shomare ro bombaran mikone). Send: 3/saat/phone + 10/saat/IP؛ verify: 5 + 20. `clearVerifies` FAGHAT phone ro pak mikone — IP e attacker ba har movafaghiat sahmie ye taze nemigire.
  → `SendOtpAction`: 🔴 enumeration-safe (HICH check e vojud e karbar — raftar baraye mojud/namojud byte-be-byte yeksan، test esh hast). 🔴 `hit`+`cooldown` FAGHAT ba'd az SMS e MOVAFAGH: shekast e Kavenegar sahmie nemikhore va code e yatim ham batel mishe — karbar fori retry mikone. Zakhire GHABL az ersal. Bypass → code tu result (faghat dev).
  → `VerifyOtpAction`: 🔴 karbar FAGHAT ba'd az ta'eed sakhte mishe (send hich user nemisaze — jadval e users ashghal nemishe). 🔴 ba'd az 3 ghalat code BATEL (hatta code e dorost e ba'di fail mishe — test). Yek-bar-masraf. 🔴 «ghalat/monghazi/nabude» hame YEK payam (enumeration — test e uniqueness e payam ha). `hitVerify` GHABL az check.
  → Mutation: batel-sazi، yek-bar-masraf، hit-ghabl-az-SMS، payam e jodagane — har 4 ta fail dadan.
- [x] **P2-07** — Login e do-marhale + `EnsureAdmin` (18 test) — ⚠️ ba **controller e nazok + Alpine** piade shod، na Livewire: UI/flow e amade ye noskhe ye ghabli (login.blade + alpine/login.js) hamin stack ro dasht va «khili aly» bud؛ backend be Action haye IN proje vasl ast (HMAC، enumeration-safe، throttle).
  → `LoginController` e nazok: show/code/cooldown/reset/verify/logout. **Step ro SERVER tasmim migire** (session + `PendingOtpQuery` e jadid ru `OtpStorePort::ttlRemaining` e jadid): refresh be step e code bar migarde ba shomarande ye server. 429 e cooldown `retry_after` mide ta client countdown dorost neshun bede. Bad az verify: `session()->regenerate()` (zed e fixation) + redirect e admin be panel.
  → 🔴 `EnsureAdmin`: gheyr-admin **404** migire na 403 — 403 be karbar e login-shode mige «in masir VOJUD DARE»، ya'ni masir e panel ro ta'eed mikone.
  → BYPASS_OTP: code tu pasokh e JSON (faghat zire bypass kelid esh HAST) + session ta refresh ham kart e «حالت آزمایشی» bemune؛ UI e amade dokme ye auto-fill dare.
  → Front: `app.js` (auto-register e `alpine/*`)، `lib/*` (request/digits/…)، layout e `site` (chrome fe'lan render nemishe — component hash Phase 5)، font e Vazirmatn (`@fontsource-variable`). **Vite build sabz.** `/dashboard` fe'lan placeholder ast (DashboardQuery e noskhe ghabli be Pricing e UN vabaste-ast → P3-00).
  → 🔴 Se bug e zir-sakhti peyda/fix shod: (1) `blade-icons` component e generic e `<x-icon>` ro gerefte bud va bar `icon.blade.php` e kit moghaddam bud — generic disable shod (`<x-tabler-*>` salem e)؛ (2) container `APP_ENV=local` ro OS-env mikone → `runningUnitTests()` false → **CSRF tu test ha fa'al** (har POST 419) — `TestCase` hala `$app['env']='testing'` ro force mikone (hamun khanevade ye dars e phpunit)؛ (3) `setContent` e Illuminate `original` ro ham jaygozin mikone → `LatinizeDigits` har `assertViewHas` ro mishkast — original hefz mishe.
  Agar `BYPASS_OTP=true`: (a) code e tolid-shode zir e form neshun dade she، (b) dokme ye «پر کردن خودکار» kenar e input ke code ro mizare tu input. `false` bashe hich kodum nabashe (eyn e production) — **test baraye har do hālat**.
- [x] **P2-09** — 🔴 **CODE REVIEW e KOL e projeh** (khaste ye sahebe projeh، 2026-10-05) — ba'd az tamam shodan e Phase 2. Kol e code ta inja: Pricing + Common + Auth. Ba `/code-review` + barresi ye dasti ye memari.
  → **Review anjam shod (2026-10-06)** — kol e Pricing + Common + Auth + asset haye Media/Panel khat-be-khat khunde shod؛ 6 fix (lista tu «Hala koja hastim») + yek audit e kamel e Media/Panel baraye P3-00 (lista tu khod e P3-00). Me'mari salem bud: marz e Domain/Application/Infrastructure hich ja naghz nashode، bind ha test daran، fake ha gharardad ro emza mikonan.
  ⚠️ **Do tasmim hanuz BAZ ast (faghat sahebe proje)**: (1) symbol-az-DB (hazine ~2-4 rooz refactor، faide: symbol e jadid bedun e deploy)؛ (2) Influx→TimescaleDB (hazine ~1-2 rooz posht e `TimeSeriesPort`، faide: hazf e yek container + Flux + spool). Hich kodum FORI nist؛ pishnahad: ba'd az Phase 3 tasmim gerefte she.
  Hamchenin: barresi ye «symbol ha az DB» (khaste ye 2026-10-05): aya `Instrument` enum + map haye dakhel e Source beran tu jadval (`instruments` + `provider_symbol_maps`) ta symbol e jadid az feed e MOJUD bedun e deploy ezafe she? Hazine: ~2-4 rooz refactor + az dast raftan e type-safety tu Domain. Faide: tgju 963 key dare va kolli symbol e dige (platin، arz ha، …) faghat ba yek radif e map fa'al mishod. Tasmim ba sahebe projeh.
  Hamchenin (khaste ye 2026-10-05): barresi ye jeddi ye **Influx → TimescaleDB** — Timescale extension e khod e PG ast (continuous aggregate = candle، retention، compression، hame SQL tu YEK db) va mitune Influx + Flux + spool + 2 token + 1 container ro hazf kone. Swap posht e `TimeSeriesPort` mahdud ast (~1-2 rooz). Hazine/faide daghigh sanjide beshe؛ ta'viz faghat ba tasmim e sahebe projeh.
- [x] **P2-08** — Test: rate limit e phone **va** IP jodagane، enumeration safety، `max_attempts` code ro batel kone
  → `tests/Feature/Auth/OtpHttpHardeningTest.php` (7 test، 55 assertion) — hame az rooye **HTTP** (middleware + controller + Action ba ham): limit e phone ba IP haye taze (4om → 429 ba `retry_after > 60`)، limit e IP ba shomare haye taze (11om → 429، hamun shomare az IP e dige → 200)، pasokh e send baraye karbar e mojud/namojud **byte-be-byte** yeki، khata ye verify baraye «ghalat» va «hich vaght ferestade nashode» yeki، 3 ghalat code ro misuzune (code e dorost e ba'di ham 422 + hich user sakhte nemishe)، va **TrustProxies+OTP ba ham**: posht e proxy e trusted har client e XFF bucket e IP e khodesh ro dare؛ XFF e ja'li az manba' e untrusted limit ro dor nemizane.

---

## Phase 3 — Panel e admin

- [x] **P3-01** — `resources/css/app.css`: token haye `@theme` (brand/ink/canvas/surface/up/down/metal، radius ta 3xl، shadow، gradient، `hachure`) + utility ha
  → Ramp e sabz e Donezo (`brand-50..950`) + canvas/surface-2/line e garm + radius xs..3xl + saye haye narm + `metal-*` + utility ha (`shell-app`، `card-p`، `card-p-lg`، `bg-brand-grad`، `hachure`) be `@theme` e mojud ezafe shod.
  🔴 **Esm haye token e kit e ghabli HEFZ shodan be onvan e alias** (`--color-brand` → `brand-600`، `brand-2` → `700`، `gold` → `metal-gold`، …): kol e re-theme e abi→sabz tu YEK file oftad، hich blade-i dast nakhord. e2e e token ha sabz munde.
  ⚠️ Deviation e sabt-shode: `up` e doc (#00B368) be onvan e MATN AA fail mishe → `up` matn e tire mund va #00B368 shod `up-strong` (fill e flash/arrow). Tozih tu khod e css.
- [x] **P3-02** — Font (local، bedun e CDN) + typography e RTL
  → Az ghabl amade bud (kit e P2-07): Vazirmatn **Variable** self-host az `@fontsource-variable` (hich CDN)، `--font-sans/mono`، line-height haye Farsi (`--bp-leading-*` ta 1.95)، `.num` (tnum) va `.ltr`. Hich kari lazem nashod — faghat ta'eed.
- [x] **P3-03** — Layout e `shell-app` + sidebar + topbar
  → `x-layouts.panel` bazneveshte shod: shell e Donezo (canvas + card e gerd e bozorg + saye ye narm)، topbar be sorat e **card e gerd e jodagane** (doc §4)، sidebar e shafaf ru surface-2 ba item e fa'al e Donezo (navar e `brand-700` e samt + bg e surface + bold). `panel-sidebar.js` sakhte shod (`panelSidebar` — rail e baz/baste ba localStorage + tooltip e rail e baste؛ ghablan component e Alpine esh aslan vojud nadasht va sidebar morde bud). `Navigation.php` az no baraye IN proje neveshte shod (ghablan ~13 route e proje ye Desktop ro dasht ke `route()` ro mishekast) — faghat `panel.dashboard` zende، baghi `null` = badge e «به‌زودی». Class haye physical e sidebar logical shodan (`border-e`، `start-full`، `ms-auto`). `/dashboard` placeholder hala dakhel e layout e vaghei render mishe. e2e 6/6 sabz، screenshot gerefte shod.
  ⚠️ **`images/dashboard-prefer.webp` tu repo NIST** — doc ha behesh eshare mikonan vali folder e `images/` hich vaght commit nashode. Moghayese ye basari ba file e marja' momken nabud؛ az spec e `docs/dashboard-design.md` peyravi shod. Sahebe proje agar file ro dare، `images/` ro ezafe kone.
- [x] **P3-00a** — Integration e bakhsh e **Panel** (2026-10-06):
  → `PanelServiceProvider` register shod (bedun e command). 🔴 4 file e MORDE ye proje ye ghabli hazf shodan (`SeedDashboardCommand`، `SeedDashboardDemoAction`، `DashboardQuery`، `DashboardController` — be Pricing e Desktop import dashtan؛ register kardaneshun har artisan ro mishekast). Blade haye `panel::` be onvan e marja' e UI mundan.
  → Alias e `local-only` tu bootstrap + route haye kit (`/dashboard/kit` — index/date/slug/request/flash) + fix e esm e view ha (`kit.*`) va route e flash. Kit ZENDE ast.
  → 🔴 3 component e Alpine e gom-shode sakhte shodan: `panel-help` (drawer + seen e localStorage)، `panel-modal` (dismissible policy)، `panel-tabs` (WAI-ARIA، arrow e RTL-aware، hash sync). Ghablan esm e method hashun (`close`/`open`) be native haye `window` fall-through mikard → «Illegal invocation» ru har safhe. Kit alan sefr khata ye JS dare.
  → Tasmim e memari (hamun olgu ye P2-07): **controller e nazok + Alpine**، na Livewire — kit e amade hamin stack ro dare va sahebe proje pasandid.
- [ ] **P3-00b** — Integration e bakhsh e **Media** (migration e `media`/`media_usages` + route ha + register + fix e model-binding e controller) — **ba P3-06 miad** (ProviderForm logo mikhad). Baghi ye yaddasht haye audit e paeen hanuz bargharar-and (richText/Tiptap، mediaLibrary، charts.js، …).
  **🔎 Audit e kamel anjam shod (2026-10-06) — chizhayi ke MOGHE'E register mishkanan:**
  - 🔴 `PanelServiceProvider` ro register NAKON ta `SeedDashboardCommand` hast: constructor esh `SeedDashboardDemoAction` mikhad ke be class haye NAMOJUD e proje ye ghabli import dare (`Pricing\Models\{Instrument,PriceQuote,PriceSource}`، `Setting\Queries\SettingQuery`، …) — register yani HAR artisan command miterke. `DashboardQuery` e Panel ham hamin tor: bayad az sefr ru `BoardQuery` e in proje bazneveshte she، fix-up nemishe.
  - 🔴 Media hich migration-i nadare (`media`، `media_usages`) va hich route-i؛ route haye lazem: index/table/store + update/destroy ru `{table}/{id}`.
  - 🔴 `sidebar.blade.php` baraye HAR item e `Navigation` `route()` seda mizane — ~13 esm e route e namojud (`panel.instruments.index`، `panel.media.index`، …). Ta route ha sakhte nashodan، item ha tu `Navigation.php` bayad `null` shan vagarna avvalin safhe ba `x-layouts.panel` miterke.
  - 🔴 10 component e Alpine tu blade ha estefade mishan vali file e js nadaran: `panelSidebar`، `panelModal`، `panelHelp`، `panelTabs`، `mediaLibrary`، `mediaPage`، `dashboard`، `richText` (Tiptap ham nasb nist)، `slugField`، `captchaPuzzle`. (Desktop ~40 component dasht — sync lazem.)
  - URL haye hardcode e `/panel/...` tu `dashboard.blade.php` va `kit/index.blade.php` (prefix e in proje `/dashboard` ast)؛ `KitController` view e `panel.kit.*` render mikone vali file ha tu `resources/views/kit/`-and؛ alias e middleware e `local-only` hich ja register nashode؛ `charts.js` `lightweight-charts` import mikone ke nasb nist (proje `klinecharts` dare).
  - Convention: `icon.blade.php` yek map e SVG e dasti-e (na Tabler — ghanun e proje)؛ 2 ta `style="display:none"` tu `jalali-date.blade.php`؛ `Media` model `final` ast (khalaf e ghanun) va `$guarded=[]`؛ `MediaController` route-model-binding e mostaghim dare؛ chand comment e English/Farsi tu Media/Panel/js.
  - Bundle: `menu-builder.js` va `tool-form.js` masraf-konande nadaran vali tu glob e app.js bundle mishan؛ 7 file e `lib/` ham orphan-and.
- [x] **P3-04** — Component ha: `stat-card`، `pill`، `health-badge`، `gauge`، `bar-chart` (capsule + hachure)
  → Har 5 ta sakhte shodan tu `resources/views/components/panel/` ba namespace e **`x-panel.*`** (na `dash::` e doc — kit e mojud hamin namespace ro dasht؛ do namespace gij-konande bud. Deviation e sabt-shode). `stat-card` featured ba `bg-brand-grad` + dokme ye `↗` ba `rtl:-scale-x-100`؛ `pill` ba tone haye ok/warn/down/up/info/flat + `inverted` baraye card e tire؛ `health-badge` ru `pill` + dot؛ `gauge` nim-donut e SVG (stroke-dasharray — bedun e JS)؛ `bar-chart` capsule + `hachure` baraye gheyr e fa'al (height e dadei ba `--bar-h` + utility، na style e tarrahi).
- [x] **P3-05** — `/dashboard` — safhe ye asli (stat card ha + salamati)
  → `DashboardQuery` e Panel (jam'-bandi ye `BoardQuery` + `ProviderQuery` + **`ProviderHealthQuery` e jadid e Pricing**) + `DashboardController` e nazok + view e `panel::dashboard`: 4 stat card (featured: gheymat e zende)، lista ye salamat e manba' ha (latency/symbol/rad/retried/khata)، gauge ye darsad e salamat، jadval e moghayese (instrument × provider، toman ba `displayDivisor`، jahat ba `up`/`down`، badge e kohnegi، marker e `*` e derived + footnote). Placeholder va `dashboard-help` e ghadimi hazf shodan.
  → `ProviderHealthQuery`: merge e DB (state/consecutive — manba' e haghighat) + hot cache (latency/symbol/error e lahze-i). ⚠️ `state` AMDAN az DB: DTO ye hot per-tick ast va `consecutiveFailures=0` dare — state e hot hamishe dorugh e «healthy» mishod. Ba mutation check sabet shod (test ghermez shod).
  → Test: `DashboardPageTest` (2) + `ProviderHealthQueryTest` (3). e2e be h1 e «میزکار» update shod. **784 test (3309 assertion) sabz، e2e 6/6.**
  → Ru mohit e zende: tgju+estjt ba ravesh e skill roshan shodan (`--force` tick: tgju 16 symbol/2 ghabul e pish-az-bazaar، estjt 9 symbol — 11 radif ru tablo، gauge 100%، jadval ba adad e vaghei).
  🔴 **Mohit e dev: token haye Influx e `.env` BI-E'TEBAR-and (401)** — volume e Influx yek ja reset shode va faghat token e admin munde؛ tick ha be spool miran (emn). Hall (faghat sahebe proje — `.env`): `dc exec octane php scripts/reset-influx-tokens.php` → `dc exec octane php artisan pricing:influx:provision --tokens` → do token e chap-shode ro tu `.env` bezar → `dc up -d --force-recreate` → `pricing:influx:drain`.
- [x] **P3-06** — `ProviderList` + `ProviderForm`
  → `ProviderController` e nazok (index/create/store/edit/update/flags) + `ProviderSaveRequest` (shape؛ invariant ha hamun `ProviderAction`) + `ProviderQuery::panelRows()/findByKeyOrFail()/formValues()` (Blade array migire، na Model). View ha: `panel::providers.index` (jadval e server-rendered + toggle e roshan/khamush ba POST — datatable e AJAX baraye ~6 radif overkill) va `panel::providers.form` (3 bakhsh: shenase/zamanbandi/namayesh؛ key tu edit readonly va az ROUTE miad pas form nemitune avazesh kone). `x-form.select` prop e `value` gereft (kit pre-select nadasht). Nav e «منابع قیمت» roshan shod.
  → 6 test e HTTP (`ProviderCrudTest`): sakht (khamush-e-default)، driver e nashenas rad، **key e ghofl** (form e `key` e avazi ta'sir nadare)، flags faghat 4 flag (timeout e ersal-shode asar nakard)، lista، 404 e gheyr-admin. **790 test sabz، e2e 6/6.** Screenshot: `screenshots/providers.png` + `provider-form.png`.
  ⚠️ **Logo be P3-00b mokul shod** — media-picker be module ye Media niaz dare.
- [x] **P3-07** — `ProviderSpreadForm` (har `Instrument::cases()` + preview e **server-side** ba hamun `SpreadPricer`)
  → `ProviderSpreadController` (edit/update/remove/preview) + `SpreadSaveRequest` + `SpreadQuery::formRows()/preview()` + `spread-form.js` (faghat transport: debounce 400ms → endpoint e preview؛ HICH mohasebe ye gheymat tu JS). 16 radif (wildcard + 15 instrument، hamishe HAME — na faghat save-shode ha)؛ preview e avvaliye server-side render mishe؛ hazf e override faghat ba dokme ye sarih (`formaction` e PUT ru hamun form) va revision e `is_removal` minevise.
  → 🔴 **Fallback e preview `SnapshotStorePort::all()` NIST** — gharardad e un «faghat fa'al» e، vali flow e vaghei spread ro GHABL az roshan kardan e provider tanzim mikone؛ `SpreadQuery` mostaghim az `price_snapshots` (Model، read-only — mojaz tebgh e jadval e dastresi) mikhune ta provider e khamoosh ham preview dashte bashe.
  → 5 test (`ProviderSpreadPageTest`): 16 radif، save → revision faghat baraye taghir (save ye dovvom e bi-taghir sefr revision)، darsad e kharej az band rad، remove + `is_removal`، **preview daghighan arithmetic e `SpreadPricer`** (±1% ru 266,400,000). **795 test sabz، e2e 6/6.** Screenshot: `screenshots/spreads.png` (ba data ye zende ye estjt).
- [x] **P3-08** — `ProviderHealth`
  → `MonitorController::health` + `panel::monitor.health`: jadval e kamel (state az DB، latency/symbol/rad az hot، shekast e payapey، akharin movafagh، akharin khata) ba hamun `ProviderHealthQuery` e P3-05.
- [x] **P3-09** — `TickMonitor` (chi rad shod va chera) — safhe ye asli ye debug
  → **Engine**: `HotCachePort::putTickReport()/tickReports()` (gharardad: jadid tarin aval، saghf 50) + `RedisHotCache` (`pb:ticks:reports`، LPUSH+LTRIM) + `ArrayHotCache` e fake ba HAMUN gharardad + sabt e izole tu `IngestTickAction` (monitoring hich vaght tick ro nemikoshe). `TickReportQuery` baraye khundan.
  → Safhe: har tick yek card — vaght e Tehran، pill e ghabul/rad/duration، pill e har provider (latency + `↻` e retry)، pill e har sink (`ok`/fail + adad e spool)، radd ha GROUP-shode ba dalil (kohne/kharej-az-band/enheraf/beyn-manba'i) + label haye unmapped. Ru mohit e zende DO chiz ro fori ma'lum kard: dore ye vaghei ~30s ast (yeki dar miyan «hich provider...» — raftar e sanadi) va influx 401 ba 11 khat spool dar har tick.
  → ⚠️ Yek bug e khodam hamin ja gereftam: kelid e sink tu payload `ok`-e na `succeeded` — pill e hame ye sink ha ghermez mishod؛ test e gharardad e kelid ha ezafe shod.
  → Test: `TickReportLedgerTest` (gharardad ru HAR DO impl: tartib، saghf e 50، limit) + `MonitorPagesTest` (tick e VAGHEI ba driver e mock bedun e network → daftarche → safhe؛ kelid haye payload). 
- [x] **P3-10** — `InstrumentMatrix` (`provider × symbol` + sen e har khune + marker e `is_derived`)
  → `InstrumentMatrixQuery` — AMDAN az `BoardQuery` nemikhune: un radif e makhfi ro hazf mikone dar hali ke «chera ru tablo nist» daghighan hadaf e in safhe-ast. Har khune: sen + tone (taze/kohne/makhfi) + `bid/ask` e native + `*` e derived. **801 test sabz، e2e 6/6.** Screenshot ha: `monitor.png`/`health.png`/`matrix.png`.
- [x] **P3-11** — Verify ba Playwright + moghayese ba `images/dashboard-prefer.webp`
  → `e2e/panel.spec.ts` (6 test، yek session e login baraye kol e suite): mizkar (stat + salamat + jadval + **gradient e brand e card e featured resolve mishe**)، nav e sidebar، lista/form e provider (key readonly + por، driver pre-select)، spread (16 radif + preview e `←`)، payesh e zende (gozaresh ba pill e tgju)، salamat + matrix. **Kol e e2e: 12/12 sabz.**
  → Moghayese ba `dashboard-prefer.webp` momken nabud — file tu repo NIST (bala ro bebin)؛ az spec e `dashboard-design.md` peyravi shod.

---

## Phase 4 — Wallet + API

- [ ] **P4-01** — Migration e `wallets` + `wallet_transactions` (append-only، `unique(wallet_id, reason, reference)`)
- [ ] **P4-02** — `WalletAction` (credit/debit) + `WalletQuery` (balance az ledger)
- [ ] **P4-03** — Migration e `api_clients` + `api_price_groups` (mehvar e **jodagane** az `provider_spreads`)
- [ ] **P4-04** — HMAC auth middleware (+ decoy-HMAC، nonce، skew)
- [ ] **P4-05** — Debit e atomic ba Lua + refund ru error/304 — **fail-CLOSED**
- [ ] **P4-06** — Endpoint ha + `ETag` ru snapshot
- [ ] **P4-07** — `CustomerList` + `WalletLedger` tu panel e ADMIN
- [x] **P4-08** — Test: debit e dobare mamnu'، refund، fail-closed vaghti Redis down ast
  → Tu `ApiChargeTest`/`ApiHmacAuthTest`/`WalletLedgerTest` (bala).

**Darkhast haye sahebe proje (2026-10-06، vasat e session):**
- [ ] **P4-09** — Model e karbari: **website + plan** — karbar website ezafe mikone (masalan tala.ir)، baraye HAR website yek client_id/secret migire، bad **plan** mikhare (masalan 1200 call/rooz): kharid e plan wallet ro be andaze ye gheymat e plan SHARJ mikone va masraf pay-as-you-go kasr mishe. Saghf e ruzane ye plan tu `ChargeApiCall` e'mal mishe (429).
- [ ] **P4-10** — Panel e CUSTOMER (self-serve): lista ye website ha، sakht e website (credential yek bar namayesh)، kharid e plan، mande/ledger e wallet.
- [ ] **P4-11** — **API inspector** (mini-Telescope): har call e API ba request/response/payload/khata/hazine/duration sabt she va tu panel dide she.
- [ ] **P4-12** — UX e behtar e `/dashboard/monitor`: tozihat e tick mafhoomi-tar (provider/symbol/zaman/driver/dalil e daghigh e har rad ba MEGHDAR)، + tozih e saghf haye enheraf tu rahnama (5% USD / 10% IRR / ta'eed e do-tayi / 20% e cross-provider).

---

## Phase 5 — Website UI

- [ ] **P5-01** — Layout e site e omumi + token haye moshtarak
- [ ] **P5-02** — Jadval e moghayese (SSR + `/api/board.json` ba ETag)
- [ ] **P5-03** — Board e TV (full-screen، font e bozorg، flash)
- [ ] **P5-04** — KLineCharts + toolbar e Alpine + `Interval::availableFrom()`
  + Interval e `1w`/`1M` (haftegi/mahane): bucket e joda NEMIKHAD — az `candles_1d` (retention ∞) ba `aggregateWindow(every: 1w|1mo)` dar lahze ye query sakhte mishan (~365 radif dar sal، arzun). Range e delkhah (`from`/`to`) alan tu `CandleRequestDto` hast.
  + Chart e KHATI: hamun data ye OHLC، faghat style e KLineCharts (`area`/`line` — `close` ha ro vasl mikone). Hich storage e jadid nemikhad.
  + Mahdudiat e tabi'i ye zoom e gozashte: tick e kham 30 rooz، m5 180 rooz، h4/d1 ta abad — `availableFrom()` hamin ro be UI mige.
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
