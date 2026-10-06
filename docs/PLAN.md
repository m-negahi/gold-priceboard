# PriceBoard — Plan e Sakht

> ## ⚠️ Taghirat e BA'D az tasvib (hengam e sakht) — aval in ro bekhun
> In plan manba' e «chi va chera» ye AVALIYE ast. Vaziat e lahze-i tu `docs/TASKS.md` ast va
> un HAMISHE bar in moghaddam e. Mavared e zir hengam e sakht avaz shodan (jozyat + dalil tu
> entry e hamun task tu TASKS):
>
> | Chi | Plan migoft | Alan | Koja |
> |---|---|---|---|
> | Tartib e Stage ha | Fetch → Normalize → Validate → Persist → Publish | **Fetch → Validate → Enrich → Persist → Publish** (Normalize dakhel e Source ha-st؛ Validate GHABL az Enrich ta `dayHigh` ba meghdar e kharab aludeh nashe) | `P1-15`, `P1-20` |
> | SMS e dev | driver e `LogSms` e joda | **hazf shod** — `FakeSms` khodesh log mizane (tashkhis e sahebe projeh) | `P2-05` |
> | Retry e fetch | nabud | khata haye GOZARA ye ettesal (cURL 35/7/…) yek bar retry، clamp be budget؛ timeout HARGEZ retry nemishe | `P1-25` |
> | Avvalin tick e provider | faghat band e motlagh | **check e cross-provider** (miane ye provider haye dige، saghf 20%) | `P1-24` |
> | Radif e bayat ru tablo | moshakhas nabud | tablo akharin-ma'lum e HAME ro negah midare؛ `hide_after_seconds` **NESBI** (`freshness + hide`) va ru READ | `P1-26` |
> | Yakh-zadegi ye Redis | nabud | **zarabān e nevisande** + fallback e khodkar be PG + `degraded` | `P1-27` |
> | Schema | — | `providers.kind` (reference/platform)، `provider_spread_revisions.is_removal`؛ migration haye ezafe-shi tu asli ha merge shodan (ghabl az production) | `P1-19`, 2026-10-06 |
> | `users` | — | `User` tu module e Auth؛ `phone` hoviat e login؛ `is_admin` fillable NIST | `P2-03` |
> | UI e Phase 3 | Livewire e sefareshi az sefr | **kit e amade az noskhe ye ghabli** (form/panel components، Alpine، Media/Panel module) — `P3-00` integration | 2026-10-06 |
> | Barresi haye baz | — | Influx↔Timescale va symbol-az-DB tu **code review e P2-09** tasmim mishan | `P2-09` |


<!-- In file ba `docs/TASKS.md` joft ast: inja «chi va chera», unja «koja hastim». -->


> Hālat e ejra va gam e ba'di → **`docs/TASKS.md`**.

## Context

`/Users/m.negahi/Projects/priceboard` faghat Docker scaffolding dare — hich code e Laravel nist، git repo ham nist. Hadaf: site e moghayese ye gheymat e tala/noghre/mes beyn e provider haye Irani + foroosh e API be customers.

Marhale ye avval `tgju`، `estjt` va `nerkh` (`live.nerkh.io/api/prices`، JSON e toman ba timestamp e Jalali). Platform ha: milli، talasea، technogold az API e khodeshun (2026-10-06)؛ baghi (wallgold، taline، hamrahgold، melligold) ba'dan faghat **yek adapter e jadid + yek radif dar `providers`** mishan.

`~/Projects/hg_website` **marja' e stack** ast (Laravel 13 / PHP 8.5 / PG 18 / Redis 8 / Octane / Livewire 4 / Tailwind 4)، **vali olgu ye me'mari nist** — khaste ye sahebe projeh in ast ke me'mari **standard tar va behtar az un projeh** bashe.

### Tartib e kar
**Panel aval، website e UI ba'd.**

| # | Phase | Mohtava |
|---|-------|---------|
| 0 | Bootstrap | `docs/PLAN.md` + Docker + Influx + port ha + Laravel + git + plugin ha |
| 1 | Pricing engine | domain + adapter ha + tick pipeline (**bedun e UI**) |
| 2 | Auth | login ba mobile + OTP (Kavenegar) |
| 3 | **Panel e admin** | theme e dashboard + provider ha + spread + salamati + customer ha |
| 4 | Wallet + API | har call az wallet kasr mishe |
| 5 | Website UI | board e TV، jadval e moghayese، chart، SEO |

---

## Decisions

| Mozu | Entekhab | Chera |
|------|----------|-------|
| Me'mari | **Ports & Adapters + CQRS-lite** ba vocabulary e Action/Query/Dto/Enum | Standard، test-pazir، domain mostaghel az Influx/Redis/PG |
| Comment ha va console output | **Finglish** | Khaste ye sahebe projeh |
| Theme e dashboard | `images/dashboard-prefer.webp` (Donezo) | Bakhsh e "Theme" |
| Chart | KLineCharts 10.0.3 core (Apache-2.0، self-host) | TradingView ru host block ast → sefr request e biruni |
| Panel | Livewire e sefareshi (na Filament) | Yekdasti ba ghanun e "hich Eloquent dar laye ye UI" |
| Time-series | InfluxDB 2.7 OSS | v3 Core rad shod: query faghat ~72h |
| Postgres | Akharin gheymat + data ye relational | Backfill e Redis |
| Redis | Gheymat e dagh | — |
| Tarikhche | Az tick haye khodemun | — |
| Realtime | Polling e 15s | — |
| SMS | **Faghat Kavenegar** fe'lan | Fallback ba'dan — contract az rooz e avval amade |
| Billing | **Wallet** — har masraf kasr mishe | Jozyat ba'dan |

---

## Me'mari ye module e Pricing (jadid — na copy az hg_website)

### Chera na hamun olgu

| Eshkal dar hg_website | Hall e ma |
|---|---|
| `PriceProvider` se kar ro ghati karde (`fetch()` + `series()` + `name()`). Chart e ma az tick haye khodemun miad pas `series()` tu contract e provider jaygah nadare | Do port e joda: `QuoteSourcePort` va `TimeSeriesPort` |
| `PwaDriver::enrich()` **mostaghim Redis mikhune va minevise** — driver be infrastructure chasbide | Driver **faghat motarjem**: payload → `RawQuoteDto[]`. Enrich yek domain service ast ke snapshot e ghabli ro **argument** migire |
| `PricingTickCommand` chagh: lock + fetch + sync + alert + warm + broadcast ba try/catch haye parakande | Command **nazok** → yek Action → **pipeline e marhale-i** ke har marhale policy e shekast esh ro **e'lam** mikone |
| `price_quotes` bo'd e provider nadare (`symbol` unique) | `(provider, symbol, side)` az rooz e avval |
| `unsignedBigInteger` e rial — `xau-oz-usd = 4140.19` ja nemishe | `bigint` e **minor unit** + `scale` posht e VO e `Price` |
| `Cache::forget` tu Action، kelid ha string e parakande | Yek `CacheKey` + `spread_version` tu hame ye kelid |
| `PricingServiceProvider` `PricePayload` ro import nakarde → `scoped()` ru class e namojud → memo **hichvaght kar nakarde** (bug e zende) | Har bind e container yek test e assert dare |

### Laye ha
```
app/Modules/Pricing/
├── Domain/                      ← PURE. hich Facade, Eloquent, Http.
│   ├── ValueObjects/   Price · Symbol · Spread · Freshness · TickTime
│   ├── Enums/          Instrument · Metal · QuoteCurrency · Unit · Side
│   │                   · SpreadMode · Interval · Direction · HealthState
│   ├── Services/       SpreadPricer · QuoteEnricher · PriceSanity
│   └── Ports/          QuoteSourcePort · SnapshotStorePort
│                       · HotCachePort · TimeSeriesPort · ClockPort
├── Application/
│   ├── Actions/        (WRITE) IngestTickAction · ProviderAction
│   │                   · ProviderSpreadAction · RecordHealthAction
│   ├── Queries/        (READ)  BoardQuery · CandleQuery · ProviderQuery · SpreadQuery
│   ├── Pipeline/       TickPipeline + Stages/{Fetch,Validate,Enrich,Persist,Publish}Stage
│   └── Dto/            RawQuoteDto · TickDto · SnapshotDto · CandleDto
│                       · BoardRowDto · TickReportDto · SinkResultDto
├── Infrastructure/              ← hame ye chiz e "biruni" faghat inja
│   ├── Sources/        TgjuQuoteSource · EstjtQuoteSource · MockQuoteSource
│   ├── Persistence/    EloquentSnapshotStore · Models/{Provider,ProviderSpread,
│   │                   PriceSnapshot,PriceChange}
│   ├── Cache/          RedisHotCache · CacheKey
│   ├── TimeSeries/     InfluxTimeSeries · Flux · FluxCsv · InfluxSpool
│   └── Support/        NumberParser · PersianText
├── Console/Commands/   pricing:tick · pricing:influx:{provision,drain,health}
├── Config/pricing.php
└── Providers/PricingServiceProvider.php
```

### Ghanun haye sakhtari
1. **`Domain/` hich chiz e Laravel nemibine** → kol e mantegh ba PHPUnit e khali test mishe، bedun e Docker.
2. **Port interface، Adapter implement.** Influx avaz she → **yek file**.
3. **Source faghat motarjem ast.** Hich cache، DB، enrich.
4. **Action = yek use-case e neveshtan. Query = yek use-case e khundan.**
5. **Command nazok** — faghat Action seda mizane va `TickReportDto` chap mikone.
6. **Pipeline ba policy e sarih**: har Stage `critical` ya `isolated` ast. Jaye try/catch haye parakande.
7. **Eloquent faghat tu `Infrastructure/Persistence`.** CRUD e admin rahat ba Eloquent؛ **pipeline e gheymat faghat ba port ha** — in marz amdi ast.
8. `declare(strict_types=1)` + `final readonly` hame ja (be joz Model va Livewire).

### Test-paziri
Har port fake e in-memory dare (`InMemorySnapshotStore`، `ArrayHotCache`، `FakeTimeSeries`، `FrozenClock`) → `tests/Unit/Pricing/` kol e pipeline ro bedun e Docker test mikone. hg_website in ro nadare (unja baraye test e tick bayad DB bala bashe).

---

## Phase 0 — Bootstrap

### 0.0 Rules + Skills + Docs — **avvalin chizi ke neveshte mishe**

Hadaf: har agent e dige-i (ya khod e man tu session e ba'di) ba khundan e in ha befahme projeh chetor kar mikone — **ghabl az yek khat code**.

**(a) Rules — `CLAUDE.md` × 3** (Claude Code khodkar load eshun mikone)
| File | Key vaghti kar mikoni ru… | Mohtava |
|---|---|---|
| `/CLAUDE.md` | hame ja (entry point) | brand، stack، jadval e port ha، dastur haye Docker، tartib e phase ha، **Finglish bodan e comment va console**، link be baghi doc ha |
| `/app/Modules/CLAUDE.md` | `app/Modules/**` | marz e `Domain`/`Application`/`Infrastructure`، jadval e «che laye-i mitune che chizi ro seda bezane»، Port & Adapter، Action/Query/Dto/Enum، `final readonly` + `declare(strict_types=1)`، koja migration/route/view/config mire |
| `/resources/views/CLAUDE.md` | `resources/views/**` | token haye `@theme`، mamnu'iat e hex/`style=""`، Alpine vs Livewire، RTL/typography، icon e Tabler |

**(b) Skills — `.claude/skills/<name>/SKILL.md`**
Chizi ke rules nemitune bede: **dastur e amal e gam-be-gam** baraye kar haye tekrari.

| Skill | Kay seda mishe | Chi yad mide |
|---|---|---|
| `add-price-provider` | ezafe kardan e provider e jadid (wallgold، taline، …) | 7 gam: radif e `providers` → class e `QuoteSource` → map e `{key → symbol, side, unit}` → **fixture e vaghei** → test e har instrument → spread e default → check e panel. Ba tale haye ma'lum (unit e per-instrument، staleness، separator) |
| `pricing-architecture` | har taghiri tu `app/Modules/Pricing/**` | che chizi tu `Domain/` mojaz ast va che chizi na، chetor port e jadid ezafe mishe، chera Source nabayad cache bebine |
| `influx-timeseries` | neveshtan/taghir e Flux ya task | bucket ha، tag vs field، **`timeSrc:"_start"`**، **`drop(_field)` ghabl az `set`**، **`timezone.fixed(3h30m)`**، float budan e `count`، `sort→limit→sort`، allowlist e zed e injection |
| `dashboard-ui` | sakhtan e safhe ye panel | token ha، component haye theme (shell، stat card، hachure، pill، gauge)، ghanun e `brand` vs `up/down`، checklist e RTL |
| `docker-dev` | ejra/debug e mohit | port ha، `docker compose exec octane …`، chera taghir e `.env` ba `octane:reload` sovar nemishe، log ha koja-an |

**(c) Docs — `docs/`**
`docs/PLAN.md` (hamin sanad)، `docs/architecture.md`، `docs/dashboard-design.md`، va **`docs/TASKS.md`**.

**`docs/TASKS.md` — checklist e ghabl e edame.** Agar session ghat' shod، az hamun file edame midim. Ghanun ha:
- Har task yek checkbox e `- [ ]` ba yek shenase (`P1-03`).
- Ba'd az **har** task e tamum shode، hamun lahze `- [x]` mishe + yek khat e «chi shod» (file haye asli).
- Bala ye file yek bakhsh e **`## Hala koja hastim`** ba task e fe'li + har chizi ke nime-kare mune (migration e neveshte-nashode، test e fail).
- Har tasmimi ke vasat e kar gerefte mishe va tu plan nist → dar bakhsh e **`## Tasmim haye vasat e rah`** sabt mishe.
Natije: ba `cat docs/TASKS.md` mishe dar yek negah fahmid koja boodim va gam e ba'di chi-e.

Sakhtar e avvaliye (kholase — file e kamel moofasal tar):
```
P0  bootstrap   doc ha · compose(influx + redis e vahed + price-worker) · port ha
                · laravel install · ext check · git init · plugin ha
P1  engine      Domain(VO/Enum/Port/Service) · NumberParser+PersianText · migration ha
                · TgjuQuoteSource · EstjtQuoteSource · adapter ha(PG/Redis/Influx)
                · TickPipeline · pricing:tick · influx:provision/drain/health · test ha
P2  auth        OtpStore · KavenegarSms · Send/VerifyOtpAction · OtpThrottle
                · SignIn · middleware ha(TrustProxies/Normalize*/Latinize) · test ha
P3  panel       token haye theme · layout e shell · ProviderList/Form · SpreadForm
                · ProviderHealth · TickMonitor · InstrumentMatrix
P4  wallet+api  wallets+ledger · HMAC auth · Lua debit · endpoint ha · ETag
P5  site        board e TV · jadval e moghayese · KLineCharts · SEO
```

### 0.1 Port ha
Gerefte shode: `8000, 8080, 8100, 8200, 5173, 5273, 5432, 6379, 55432, 55433, 56379, 56380`. `COMPOSE_PROJECT_NAME=pb`.

| Service | Env | Host port |
|---|---|---|
| Octane | `OCTANE_PORT` | **8300** |
| Vite | `VITE_PORT` | **5373** |
| Postgres | `DB_EXTERNAL_PORT` | **55434** |
| Redis (hame e connection ha) | `REDIS_EXTERNAL_PORT` | **56381** |
| InfluxDB | `INFLUX_EXTERNAL_PORT` | **8386** |

⚠️ compose default esh `b2blanding` ast → `pb`. Va `vite` ro `${VITE_PORT}:${VITE_PORT}` map mikone → `vite.config.js` bayad `port: 5373, strictPort: true`.

### 0.2 Docker
- **`influxdb:2.7-alpine`** (tag ta'eed shod) + volume haye `influxdb_data`/`influxdb_config`، `DOCKER_INFLUXDB_INIT_MODE=setup`، healthcheck `influx ping`.
- **`price-worker`** ba loop e grid-aligned:
  `while :; do php artisan pricing:tick; sleep $((15 - $(date +%s) % 15)); done`
  (`sleep 15` e sade drift mikone va tick ha ru grid e 15s nemioftan — baraye idempotency e Influx mohem ast.)
- **Redis e vahed** (darkhast e 2026-10-06): DB0 session/queue/OTP, DB1 cache, DB2 prices. `noeviction`, AOF `everysec`, maxmemory e pish-farz 512MB. Kelid haye gheymat bedun e TTL; jadval e mojud az Redis e ghadimi ba hefz e TTL/data entegal yaft. Test ha DB13/14/15. Hame logical DB ha yek memory/failure domain daran; PG fallback e board, monitor e memory va queue ejbari ast.
- `reverb` lazem nist. `scheduler` + `horizon` hamun.

### 0.3 Laravel install
- `livewire/livewire: ^4.3` **sarih** (tu hg_website faghat transitive az Pulse miad — bug).
- `influxdata/influxdb-client-php:^3.9` + `guzzlehttp/guzzle`.
  ⚠️ `ext-curl`/`ext-json`/`ext-mbstring` mikhahe. Dockerfile sarihan `curl` e PHP ro install **nemikone** — avval `php -m | grep -E 'curl|mbstring|json'`؛ nabud → `docker-php-ext-install`. `php-http/discovery` tu `allow-plugins`.
- Mikhahim: `octane`, `horizon`, `pulse`, `telescope`, `sanctum`, `morilog/jalali`, `blade-tabler-icons`, `laravel-responsecache`, `sentry`.
- Nemikhahim: `reverb`, `mpdf`, `commonmark-highlighter`, `laravel-permission`.
- npm: `alpinejs` + collapse + focus، `klinecharts@^10.0.3`، `tailwindcss@4` + `@tailwindcss/vite`، `vite@8`، `laravel-vite-plugin`.
- PHPUnit (na Pest)، `phpunit.xml` ba `force="true"` ru hame ye env — jologiri az pak shodan e DB e dev.

### 0.4 `git init` + commit e avval
### 0.5 Plugin ha
`playwright`، `frontend-design`، ekhtiari `superdesign` — az `claude-plugins-official` ba `/plugin`.

---

## Theme e dashboard (az `images/dashboard-prefer.webp`)

Marja' e asli **`dashboard-prefer.webp`** (Donezo) ast؛ `dashboard.png` (Shopeers) marja' e dovom baraye chegali ye jadval va stat card.

### Zaban e basari
| Element | Ghanun |
|---|---|
| Page | Background e khakestari ye roshan؛ kol e app dakhel e yek **shell e gerd e bozorg** (radius ~32px) — hālat e "floating panel" |
| Sidebar | Shafaf/kheyli roshan. Group ha ba label e riz e UPPERCASE (`MENU`، `GENERAL`). Item e fa'al: **navar e sabz e samt** + matn e tire ye bold. Gheyr e fa'al: icon e outline + matn e muted |
| Topbar | **Card e gerd e jodagane**: search e pill-shekl ba badge e shortcut، dokme haye gerd e icon، avatar + nam/email |
| Stat card ha | 4 ta. **Avali "featured"**: gradient e sabz e tire ba matn e sefid. Baghi sefid. Har kodum: dokme ye gerd e `↗` bala-rast، adad e bozorg، pill e riz (`5 ▲`) + matn e moghayese |
| Chart | Bar haye **capsule** (kamelan gerd). Fa'al = sabz، gheyr e fa'al = **hachure ye mورب e khakestari** — in hachure emza ye in theme ast |
| Gauge | Nim-donut ba darsad e bozorg vasat + legend ba dot |
| Card e tire | Sabz e tire ba **texture e topographic/swirl** + dokme ye pill (promo e sidebar، Time Tracker) |
| Pill e status | Completed=sabz e soft، In Progress=zard e soft، Pending=ghermez e soft |
| Kolli | Radius e balaye hame ja، whitespace e saakhavatmand، border haye narm/na-mojud، saye haye kheyli nazok |

### Token ha (`resources/css/app.css`، Tailwind v4 `@theme`)
`--color-brand-50…900` (sabz e Donezo)، `--color-ink-*` e garm، `--color-canvas` / `--surface` / `--surface-2` / `--line`، `--radius-xs…3xl` (ta ~32px baraye shell)، `--shadow-xs…lg` e narm، `--gradient-brand-a/b` (card e featured va card e tire)، `--pattern-hachure` (background e tekrari ye SVG).
Utility ha: `@utility shell-app`، `card-p`، `stat-card`، `pill-badge`، `hachure`.

### 🔴 Yek nokte ye vaghei ye design
Dar bazaar e mali **sabz = so'ud va ghermez = nozul** ma'ni ye semantic dare. Agar rang e brand ham hamun sabz bashe، tu board/jadval moshakhas nist ke sabz «brand» ast ya «gheymat bala raft». Pas:
- **Brand** = sabz e tire ye Donezo (`brand-700/800`) — faghat baraye chrome: sidebar e fa'al، dokme ye asli، card e featured.
- **Semantic e gheymat** = yek emerald e roshan tar baraye `up` va rose baraye `down` — **token haye jodagane** (`--color-up` / `--color-down`)، hargez `brand-*`.
- Tala/noghre/mes har kodum yek accent e riz (amber / slate / copper) faghat baraye icon va chip e symbol.
In tafkik tu `docs/dashboard-design.md` sabt mishe ta Phase 5 (board e TV) ham hamun ro estefade kone.

---

## Phase 1 — Pricing engine (bedun e UI)

### 1.1 Instrument
"Au/Aux" → standard e vaghei **`XAU`/`XAG`/`XCU`** (ISO 4217: `X` + element). Format: `{metal}-{form}[-{purity|size}]-{quote}`، kebab e lowercase.

| Symbol | Label | Unit | Quote | scale | Vazn (g) | Side e native |
|---|---|---|---|---|---|---|
| `xau-18k-g-irr` | طلای ۱۸ عیار | gram | IRR | 0 | 1 | — |
| `xau-24k-g-irr` | طلای ۲۴ عیار | gram | IRR | 0 | 1 | — |
| `xau-melted-g-irr` | آبشده نقدی | gram | IRR | 0 | 1 | **bale** |
| `xau-mesghal-irr` | مثقال / مظنه تهران | piece | IRR | 0 | 4.6083 | — |
| `xau-coin-emami-irr` | سکه امامی | piece | IRR | 0 | 8.133 | — |
| `xau-coin-bahar-irr` | سکه بهار آزادی | piece | IRR | 0 | 8.133 | — |
| `xau-coin-half-irr` | نیم سکه | piece | IRR | 0 | 4.0665 | — |
| `xau-coin-quarter-irr` | ربع سکه | piece | IRR | 0 | 2.0332 | — |
| `xau-coin-gerami-irr` | سکه گرمی | piece | IRR | 0 | 1 | — |
| `xau-oz-usd` | انس طلا | ounce | USD | 4 | 31.1035 | — |
| `xag-999-g-irr` | نقره ۹۹۹ | gram | IRR | 0 | 1 | — |
| `xag-925-g-irr` | نقره ۹۲۵ | gram | IRR | 0 | 1 | — |
| `xag-oz-usd` | انس نقره | ounce | USD | 4 | 31.1035 | — |
| `xcu-lb-usd` | مس (پوند) | pound | USD | 4 | 453.592 | — |
| `usd-irr` | دلار | currency | IRR | 0 | — | — |

### 1.2 Gheymat — `bigint` e minor unit + `scale`
`decimal` tu PHP string bar migardune va har arithmetic bcmath mikhahe؛ `bigint` e rial e khali `4140.19` ro ja nemide. Pas `minor = major × 10^scale`، `IRR→0`، `USD→4`. `scale` **ru har radif va tu har payload e Redis** ta hich reader-i baraye tafsir e adad be enum niaz nadashte bashe.
Shart: **int e kham hargez az VO birun nemire** (`Price::plus()` ru cross-currency throw mikone). Agar in discipline negah dashte nashe → `decimal(24,6)` + bcmath. **Ghati kardan e do ta failure mode esh saket ast.**

### 1.3 Source e tgju — `GET https://call1.tgju.org/ajax.json`
Ta'eed shode: HTTP 200، `{current: {<key>: {p,h,l,d,dp,dt,t,ts}}, …}`، 963 symbol.
Map: `geram18`، `geram24`، **`tgju_gold_irg18`→side=ask** + **`tgju_gold_irg18_buy`→side=bid**، `mesghal`، `sekee`، `sekeb`، `nim`، `rob`، `gerami`، `silver_999`، `silver_925`، `ons`، `silver`، `copperp` (fallback `base_global_copper2`)، `price_dollar_rl`.

⚠️ Se chiz tu data ye vaghei:
1. `p` string ba comma (`"266,400,000"`)، ba'zi vaght `\t` e jolosh (`"\t8520000"`).
2. **Faghat ~389 az 963 symbol taze-an.** `zinc` ts=2021-06-28، `geram18_buy` ts=2018-05-29 → filter ru `ts` **ejbari**.
3. `*_buy` ha morde-an be joz `tgju_gold_irg18` + `_buy` — tanha jofte bid/ask e zende.
Gheymat **Rial**، `ts` vaght e Tehran **bedun e offset** → `Carbon::parse($ts, 'Asia/Tehran')->utc()`.

### 1.4 Source e estjt — `POST .../wp-admin/admin-ajax.php` body `action=new_price`
Ta'eed shode: HTTP 200، `{gold: "<table html>", coin: "<table html>"}`.

⚠️ Panj chiz:
1. **User-Agent e browser + HTTP/1.1 ejbari** — vagarna timeout (test shod).
2. **Toman ast** → `×10`. (`طلا ۱۸ عیار = ۲۶٫۶۸۱٫۸۰۰` toman = 266,818,000 rial ↔ tgju 266,400,000 ✓)
3. Separator e hezargan **`٫` (U+066B)** ast — ke *ma'nan* separator e **ashar** e Arabi-e. Ghanun e global e "U+066B = ashar" `۱٫۲۳۴٫۵۶۷` ro mikone `1.234` → khata ye **۱,۰۰۰,۰۰۰ barabari**. Policy e separator **har-source-jodagane**، na heuristic e global.
4. 🔴 **`upstream_unit` nabayad per-provider bashe.** estjt «انس طلا» ro **USD** mide va baghi ro **toman**. Yek `upstream_unit=toman` e provider-level ons ro ×10 mikone → `$39,005` ke onghadr bavar-pazir hast ke az zir e cheshm rad beshe. **Unit tu entry e map e har instrument** mineshine؛ `providers.upstream_unit` faghat default. Baraye har instrument e har source yek test ba fixture.
5. Parse ba `DOMDocument`+`DOMXPath`، **na regex**. Map ba **label e fold-shode** (`PersianText::fold()`: `ي→ی`، `ك→ک`، hazf e ZWNJ/tatweel، raghamha→Latin) chun «طلای» ZWNJ dare va «۱۸» Farsi ast. **`Str::slug()` mamnu'** — Farsi ro kolan mindaze. Label e unmapped → log e `pricing.unmapped` ba byte haye kham. «مظنه تهران» → `xau-mesghal-irr` (hamun instrument، yek radif e moghayese ye vaghei).

### 1.5 Schema (Postgres)
```php
// providers
key(32,unique) · driver(32) · name · name_fa · website · logo_path
is_active · in_comparison · on_board · in_chart
timeout(10) · poll_interval_seconds(15) · next_poll_at
freshness_seconds(900) · hide_after_seconds(600) · upstream_unit · sort · options jsonb
last_attempt_at · last_success_at · last_failure_at · last_error(500)
last_latency_ms · last_symbol_count · last_rejected_count
consecutive_failures · health_state(12) · last_payload_hash(32) · timestamps · softDeletes
index(is_active, sort) · index(is_active, next_poll_at)
```
`driver` joda az `key` amdi: ba'dan panj maghaze ba hamun shape e `admin-ajax.php` = **panj provider ru yek driver**.

```php
// provider_spreads
provider_id · symbol(32)            // ya '*' = wildcard
buy_mode(8) percent|amount · buy_value decimal(12,4)    // ELAMATDAR: manfi kam mikone
sell_mode(8)              · sell_value decimal(12,4)
is_enabled · freshness_seconds(nullable) · sort(nullable) · unique(provider_id, symbol)
```
Resolve: `(provider,symbol)` → `(provider,'*')` → sefr. `amount` tu **major unit e quote currency**.
Esm `provider_spreads` **na** `price_groups`: Phase 4 yek mehvar e **kamelan jodagane** mikhahe (`api_price_groups`) — spread e provider = «chi neshun midim»، spread e customer = «chi mohasebe mikonim». Ghati kardaneshun ba'd az avvalin surat-hesab **ghabl e bargasht nist**.

```php
// provider_spread_revisions  (append-only)
provider_id · symbol · buy_mode · buy_value · sell_mode · sell_value
changed_by(64) · effective_from · created_at · index(provider_id, symbol, effective_from)
```
Tu hamun transaction e `ProviderSpreadAction`. Avvalin bar ke maghaze-i be nerkh e namayesh dade shode e'teraz kone، in javab ast.

```php
// price_snapshots  — tanha jadval e "akharin"
provider_id · symbol(32) · side(4) last|bid|ask
quote_currency(3) · scale(tinyint)
raw_minor bigint       // daghighan chi provider goft — currency-unified, BEDUN e spread
prev_minor · day_open_minor · day_high_minor · day_low_minor
change_pct decimal(9,4) · direction(8)
has_native_sides · upstream_at · upstream_lag_seconds · quoted_at
unique(provider_id, symbol, side) · index(symbol, side) · index(quoted_at)
```
`side` tu unique chun `xau-melted-g-irr` vaghe'an do radif e upstream dare. ~50 radif alan، ~300 ba shesh provider.
**Hich `buy_minor`/`sell_minor`** — mochtag-and (1.7). Zakhireshun ba'd az taghir e spread meghdar e bayat ja mizare.

```php
// price_changes — audit e append-only (FAGHAT vaghti raw_minor taghir karde)
provider_id · symbol · side · quote_currency · scale · raw_minor
upstream_at · observed_at · index(provider_id, symbol, side, observed_at)
```
~5MB/mah. Chera lazem ast: Influx 2 OSS replication nadare، tick e kham ba'd az 30 rooz pak mishe، candle haghighat e tick-level ro mibare. In jadval `pg_dump`-pazir ast، manba' e dovom baraye rebuild e Influx، va javab e «gheymat tu lahze T chand bud» baraye API bedun e vabastegi be Influx.

```php
// influx_backfills — provenance
bucket · provider_id · symbol · source(64) · range_start · range_stop · points
```

### 1.6 InfluxDB
| Bucket | Measurement | Manba' | Retention |
|---|---|---|---|
| `ticks` | `price` | write path | **30d** |
| `candles_1m` | `ohlc` | `ticks` | 90d |
| `candles_5m` | `ohlc` | `candles_1m` | 180d |
| `candles_15m` | `ohlc` | `candles_1m` | 400d |
| `candles_1h` | `ohlc` | `candles_15m` | 1095d |
| `candles_4h` | `ohlc` | `candles_1h` | ∞ |
| `candles_1d` | `ohlc` | `candles_1h` | ∞ |

Yek bucket be ezaye har interval (na tag e `interval`) chun retention tu Influx 2 **khasiat e bucket** ast. Measurement hame yeki (`ohlc`) ta yek template e read ba faghat esm e bucket parameterize she.
**Cascade** (na hame az `ticks`): sakhtan e `candles_1d` az bucket e 30-rooze ba'd az yek ghat'i ye chand-hafte-i ye task **ghabl e jobran nist**. OHLC-of-OHLC daghigh ast.

**Tag**: `provider`, `symbol`, `side`, `quote`. **Field**: `ticks` → `value` + `lag`؛ candle → `open,high,low,close,count` — **hamash float، hatta `count`**، chun int/float mismatch tu `acc.count + r.count` shayeh tarin marg e saket e task ast.
Cardinality ≈ **165 series**. Influx ta 10⁶. **Hichi az headroom kharj nakon**: `unit`، `metal`، `purity`، `label`، `scale`، `upstream_ts` **hargez tag nashan**.

**Timestamp e floor-shode ru grid e 15s** — `intdiv(time(),15)*15`، yek timestamp baraye kol e tick:
1. **Idempotency**: hoviyat e point = `(measurement, tagset, field, timestamp)` → replay e spool overwrite mikone na duplicate.
2. **Hamtarazi beyn e provider ha** baraye `pivot`. Timestamp e ms e floor-nashode har pivot ro nim-khali mikone.

**Query**: `m5`–`d1` az bucket e candle؛ **`m1` zende az `ticks`** ba `aggregateWindow` (hamishe current، bedun e vabastegi be latency e task).
⚠️ Se chiz ke shekastanesh asan ast:
- **`timeSrc: "_start"` ejbari** — default `"_stop"` kol e chart ro yek candle jolo mindaze.
- **`drop(columns:["_field"])` ghabl az `set(key:"_field",…)`** — `_field` tu group key ast؛ `set` e tanha group key e bayat ja mizare va `union`/`pivot` saket soton mindaze.
- `sort(desc) → limit → sort(asc)` baraye **akharin** N bar؛ `limit` e khali ghadimi tarin ha ro mide (bug e chart e khali). `n` server-side ru 5000 cap.

**Task ha** (6 ta، `pricing:influx:provision` e idempotent، Flux tu `resources/influx/tasks/*.flux` checked-in): `every` **joda az** andaze ye bar، lookback ≥ 2 bar → bar e emruz pey-dar-pey refresh mishe، hich partial-bar stitching lazem nist. `1d` har 15 daghighe ba `-50h`.
🔴 **`option location = timezone.fixed(offset: 3h30m)` ru task haye `4h`/`1d` ejbari.** Bedun esh `window(every: 1d)` be nime-shab e UTC = 03:30 Tehran align mishe → **har candle e ruzane do rooz e kari ye Irani ro sovar mishe** — eshtebah be shekli ke mah ha kasi nemifahme. `timezone.fixed` na `location(name:)` chun Iran az 2022 DST nadare. Offset tu `config/pricing.php`.

**Do token**: `WRITE_TOKEN` (write-only ru `ticks`، worker) va `READ_TOKEN` (read-only، web). Token e web lo bere، kasi natune tarikhche ja'l kone.
**Hajm**: ~950k point/rooz → **~25–50 MB/rooz**، 30 rooz ≈ 0.8–1.5 GB.

### 1.7 Spread — **ru read e'mal mishe. Hamishe.**
1. **Spread e bake-shode yek pele ye ja'li tu chart mizare.** Admin 14:00 spread ro +0.5%→+0.8% mikone → candle haye ghabl ba ghadim va ba'd ba jadid → **discontinuity e 0.3% ke harekat e gheymat nist**. Kol e ma'ni ye chart e teknikal in ast ke shekl esh ma'ni dare — MA/RSI/Bollinger un pele ro signal e vaghei mikhunan. Hamin yek dalil kafi-e.
2. **Tarikhche ye Influx ghabl e re-spread nist** — va ba'd az dur rikhtan e meghdar e kham، hich rah e durosti nadare.
3. **Meghdar e kham tanha vaghe'iyat e ghabl e audit ast.** Yek `+50` ke eshtebah tu field e `percent` type shode mishe **rollback e config**، na hadese ye data-loss.
4. **Hazine ye read sefr ast** — map ~50 radif، `SpreadPricer` e bi-halat.

Do ta'ahod:
- **Chart bayad hamun spread e board ro begire** — vagarna close e akharin candle ≠ gheymat e board va har karbar un ro bug gozaresh mikone. `applySeries()` ru har chahar ta ye OHLC. Har do mode monotone-and pas `high ≥ low` khod-be-khod hefz mishe.
- 🔴 **Yek `spread_version` e monotonic** (`INCR pb:spreads:v`، mirror tu settings)، tu transaction e `ProviderSpreadAction` bump mishe، va tu **hame ye** kelid e cache e gheymat-dar + har ETag miad.

Se ghanun e `SpreadPricer`: `change_pct`/`direction` **hargez** tanzim nemishan (spread e darsadi ru darsad e taghir neutral ast)؛ high/low ba delta ye sell shift va ba'd clamp؛ spread-i ke gheymat ro ≤ 0 kone → fallback be `raw`، na sefr.

### 1.8 Normalization
**Invariant: tanha jayi ke comma، raghame Farsi، ya toman ro mishnase، Source ast.**
`NumberParser::toMinor()` — **bcmath، hargez float**، round e half-up:

| Source | thousands | decimal |
|---|---|---|
| tgju (IRR) | `[',']` | `null` |
| tgju (USD) | `[',']` | `'.'` |
| estjt | `['٫','٬',',']` | `null` |
| nerkh (IRR) | `[',']` | `null` |
| nerkh (USD) | `[',']` | `'.'` |
| milli (rial/mg) | `[',']` | `'.'` scale 3 (×1000) |
| talasea (toman/mg) | `[',']` | `'.'` scale 3 (×1000) ×10 |
| technogold (toman/g) | `[',']` | `null` ×10 |

**Staleness**: `lag = tickAt - upstream_at` → reject agar `lag > effectiveFreshness` ya `lag < -120s`.
`effectiveFreshness` **bayad per-instrument bashe**: `usd-irr`/`xau-oz-usd` taghriban 24/5 (180s)، `xag-925-g-irr` mitune yek sa'at nakhore، sekke ha faghat sa'at e bazaar. Yek threshold e global ya gheymat e 2021 ro rah mide ya instrument haye kond ro mindaze.
**Bazaar e baste feature ast na bug**: shab/jom'e `ts` ghofl mishe → hich chiz neveshte nemishe → gap e vaghei tu chart. **Forward-fill nakon.**
estjt ts nadare → `md5` e HTML (`last_payload_hash`): bi-taghir >10 daghighe tu sa'at e bazaar → `degraded` + hoshdar، **vali tick edame mide** (gheymat e bi-taghir tu bazaar e aram tabi'i-e؛ candle e saf sadegh tar az gap ast).

`PriceSanity` — do check e mostaghel: band e motlagh e har instrument (khata ye 10×، soton e eshtebah، flip e arz) + saghf e enherāf e tick-be-tick (10% IRR / 5% USD). Rad shode → log e `pricing.rejected` va **hich ja neveshte nemishe**. Yek parse e bad ke be `candles_1d` berese dorugh e daemi ast va pak kardanesh `delete` e predicate-i + cascade e re-run mikhahe. Rad kardan tu darvaze 20 khat ast.
⚠️ Gap e vaghei ye 12% (devaluation) in guard ro trip mikone → **faghat avvalin breach rad mishe؛ tick e ba'di ta'eed kard، paziresh mishe**.

### 1.9 Tick pipeline
| # | Stage | Policy | Kar |
|---|---|---|---|
| 0 | Lock | isolated | `Cache::lock('pricing:tick', 14)`. **Redis down → bedun e lock edame** (lock guard e performance ast na correctness، chun hame ye sink ha idempotent-and) |
| 1 | — | critical | `TickTime` = floor e grid e 15s |
| 2 | — | critical | `ProviderQuery::activeForTick()` — Redis(30s) → PG → `:last` (bi-TTL). **PG down nabayad polling ro motevaghef kone** |
| 3 | Fetch | isolated/provider | `Http::pool`. **Shekast e yeki hargez be baghi nemirese** |
| 4 | Normalize | isolated/provider | parse + unit + symbol map |
| 5 | Validate | isolated/quote | staleness + `PriceSanity` |
| 6 | Persist | **isolated/sink** | Redis → Postgres → Influx |
| 7 | Publish | isolated | warm e payload |

Khoruji `TickReportDto`: chi gerefte shod، chi rad shod va chera، har sink chi shod. Log e JSON + chap e console (Finglish).
**Faghat lock e gerefte shode va sefr provider (har do amdi) mitunan tick ro motevaghef konan. Hich chiz tu Persist.** Hame `Log::warning` (na `report()`) — vagarna Sentry ba 5,760 event e yeksan tu yek ghat'i flood mishe.

### 1.10 Tartib e sink ha — **Redis → Postgres → Influx**
Redis aval chun chizi ast ke har bazdid-konande mikhune. PG dovom chun backfill e payedar ast. Influx akhar chun gom shodan e yek tick = gap e 15 saniye-i tu candle e 90 rooze — **kam-zarar tarin**.
Dalil e asli (na «durability») **asymmetry e shekast** ast: PG down + tartib e PG-aval → Redis be-rooz nemishe va board yakh mizane؛ Redis-aval board ro kamelan zende negah midare.
**Invariant e test-shodani**: PG hargez bishtar az yek tick aghab e Redis nist، va Redis hamishe az PG backfill-pazir ast.
- **Redis**: yek pipeline — `HSET pb:q:{provider}:{symbol}:{side}`، `SET pb:board`، `INCR pb:board:v`، `HSET pb:health:{provider}`. **Bedun e TTL.**
- **Postgres**: yek `upsert()` e `INSERT … ON CONFLICT` (khodesh atomic، transaction lazem nist). **Na `updateOrCreate` tu halghe** — hg_website un kar ro mikone va ba 50 radif 50 round-trip tu har tick mide bi-hich fayede. Ba'd `price_changes` faghat baraye radif haye taghir-karde.
- **Influx**: yek POST e `SYNCHRONOUS` ba timeout e 2s. Shekast → `InfluxSpool` (`RPUSH` + `LTRIM 0 39999` ≈ 2 sa'at)، replay ba `pricing:influx:drain` har daghighe. Salem chun `(tagset, timestamp)` = upsert.
- **Health**: Redis har tick؛ PG **faghat ru taghir e `health_state` ya agar >60s** — vagarna 23k HOT update tu rooz.

### 1.11 Octane safety
🔴 `WriteApi` e `BATCHING` buffer e mutable ast: singleton zire Octane → buffer beyn e request ha mimune، mitune tavasot e request e bi-rabt flush she، memory leak mikone، va ba recycle e worker saket khali mishe. `CurlMultiHandler` e Guzzle ham handle e persistent dare ke share kardanesh beyn e coroutine haye Swoole response ha ro ghati mikone.
- `singleton` **mojaz**: `PriceManager`، `InfluxTimeSeries` — `final readonly`، faghat config e immutable، **tu har method client e taze**.
- `InfluxDB2\Client`/`WriteApi`/`QueryApi` — **hargez bind nashan** (na singleton، na scoped، na bind).
- Write faghat tu worker e CLI، faghat `SYNCHRONOUS`، client tu method sakhte va `close()` (`finally`).
- **Read: aslan SDK estefade nakon.** `Http::withBody($flux,'application/vnd.flux')->post('/api/v2/query')` + `FluxCsv::toCandles()` e ~40 khati → yek class e kamel e khatar e Octane az request path hazf mishe، yek HTTP stack baraye kol e app، va parse e mostaghim be shape e KLineCharts jaye alloc kardan e `FluxTable`/`FluxRecord` (series e `h1` e yek-sale = 8,760 × 5 ≈ 44k record).
- **Flux injection**: `symbol`/`interval`/`side` az enum، `provider` az lista ye key e DB، `from`/`to` cast be int، + `Flux::tag()` ke har chi gheyr e `[a-z0-9_\-]` ro rad mikone. Support e query-parameter e SDK za'if ast pas **allowlist e sakht defa' e asli ast، na laye ye ezafe**.

---

## Phase 2 — Auth: login ba mobile + OTP
```
Auth/Domain/Ports/{OtpStorePort,SmsPort}
Auth/Application/Actions/{SendOtpAction,VerifyOtpAction}
Auth/Infrastructure/Otp/RedisOtpStore      // code HASH shode, na plaintext
Auth/Infrastructure/Sms/{KavenegarSms,FakeSms,SmsManager}
Auth/Support/OtpThrottle · Livewire/SignIn · Http/Middleware/EnsureAdmin
```
**SMS faghat Kavenegar**، posht e `SmsPort` → fallback ba'dan **yek file**. Dev/test/demo: `FakeSms` (log + hafeze — `LogSms` e joda hazf shod، bebin changelog).
- Code **hash shode** tu Redis، `expires_in` 120s، `resend_cooldown` 60s، `max_attempts` 3 → ba'd az 3 ghalat code **batel** (na faghat shomaresh).
- **Enumeration safety**: javab baraye shomare ye mojud va namojud yeksan. User vaghti sakhte mishe ke OTP ta'eed she.

**Rate limit — ham phone ham IP:**
| Kelid | Limit |
|---|---|
| `otp:send:phone:{phone}` | 3 / saat |
| `otp:send:ip:{ip}` | 10 / saat |
| `otp:verify:phone:{phone}` | 5 / saat |
| `otp:verify:ip:{ip}` | 20 / saat |
| `otp:cooldown:{phone}` | 60s |

🔴 **Rate limit e IP bi-ma'ni ast agar `TrustProxies` kar nakone** — bedun esh `$request->ip()` hamishe IP e proxy ast va **hame ye karbar ha ru yek bucket mioftan**.

---

## Middleware ha (port az hg_website)
Se ta esm borde shod؛ tu hg_website **chahar file**-and (parse e adad do ghesmat dare):

| File | Laye | Kar |
|---|---|---|
| `TrustProxies` | `prepend` (avval) | IP e vaghei + scheme. **Octane-safe**: `Symfony::setTrustedProxies()` e static ba process e long-running kar nemikone → `REMOTE_ADDR`/`HTTPS` mostaghim set mishan. `X-Forwarded-For` **az rast** khunde mishe (chap-tarin ghabel e ja'l ast). CIDR haye CDN e Irani dakhelesh |
| `NormalizeStringMiddleware` | `web` #1 | **input**: `ي→ی`، `ك→ک`، raghamha → English. **Ghabl** az phone |
| `NormalizePhoneMiddleware` | `web` #2 | **input**: `phone/mobile/tel/*_phone` → E.164. Natunest → meghdar e asli (rad kardan kar e validation) |
| `LatinizeDigits` | `web` (akhar) | **output**: raghamha ye munde tu HTML → Latin. ⚠️ **JSON e Livewire rad mishe، chun snapshot checksum dare va bazneveshtanesh state e component ro kharab mikone** |

---

## Phase 3 — Panel e admin (tamarkoz e asli)
Theme e bala + Livewire e sefareshi. Auth: OTP + `EnsureAdmin`. Route: `/dashboard`.

- `ProviderList` / `ProviderForm` — `is_active`، `in_comparison`، `on_board`، `in_chart`، timeout، `poll_interval_seconds`، freshness، `options`، sort، logo.
- `ProviderSpreadForm` — yek radif baraye **har** `Instrument::cases()` (na faghat save-shode ha)، `between:-100,100`، va **preview e live e server-side ba hamun `SpreadPricer`** ke API va board estefade mikonan (na copy e JS ke saket vagera she).
- `ProviderHealth` — `health_state`، `consecutive_failures`، `last_error`، latency، `last_symbol_count`/`last_rejected_count`.
- `TickMonitor` — akharin `TickReportDto` ha: che symbol-i rad shod va chera (stale / out_of_band / deviation / unmapped). **Safhe ye asli ye debug.**
- `InstrumentMatrix` — `provider × symbol` ba sen e har khune.
- `CustomerList` / `WalletLedger` — Phase 4.

🔴 **Label e `is_derived`.** Ma adad hayi ro ba label e «nerkh e kharid/foroosh e maghaze X» neshun midim ke dar vaghe' arithmetic e ma ru yek mid price ast. Ru site-i ke kol e arzesh esh e'temad ast، hamin chizi ast ke shekayat misaze. Az rooz e avval: `has_native_sides` namayesh dade she (marker + footnote «نرخ خرید/فروش محاسبه‌شده»)، jofte vaghei (`xau-melted-g-irr` e tgju) vaghei mark she، va `is_derived` tu payload e API ham biad. Retrofit ba'd az customer e billing-dar kheyli gerun tar ast.

---

## Phase 4 — Wallet + API
**Billing = wallet**: customer sharj mikone va **har masraf kasr mishe**. (Jozyat ro sahebe projeh tu hamun marhale tozih mide — inja faghat shape reserve mishe ta schema ye Phase 1/3 ba'dan refactor nakhahe.)
```php
// wallets
owner_id · balance_minor bigint · currency(3) · is_blocked
// wallet_transactions — ledger e append-only, HARGEZ update/delete
wallet_id · direction(credit|debit) · amount_minor · balance_after_minor
reason(32)     // topup | api_call | adjustment | refund
reference(64)  // request id — baraye idempotency
meta jsonb · created_at · unique(wallet_id, reason, reference)   // kasr e dobare mamnu'
```
`balance_minor` **cache** ast؛ manba' e haghighat ledger ast → ekhtelaf ghabl e kashf.

- Auth: `client_id` + HMAC-SHA256، canonical `METHOD\nURI\nTS\nNONCE\nsha256(body)`، skew ±30s، nonce TTL 90s، decoy-HMAC baraye timing attack.
- Retry e API (review e 2026-10-06): `X-Pb-Request-Id` ta 128 byte va scoped be client ast. Nonce e auth har bar taze. Hamun ID + method/URI/body/If-None-Match e yeksan → **hamun response e cache-shode** ta 24 saat, `X-Pb-Replayed: 1`, `X-Pb-Cost: 0`; data e taze ba charge e ghabli serve nemishe. ID e masraf-shode ba request e motefavet ya cache e az-dast-rafte → 409 va ID e jadid lazem ast. Lock e moshtarak jolo e ejra e hamzaman ro migire (409 + Retry-After: 1). Cache/lock failure → 503. Reference e ledger hash e client+ID, ba prefix e refund ham zir e 64 byte ast; reference haye ghadimi baraye jologiri az double-charge check mishan.
- Har call: reserve → ejra → error/304 → **refund**. Tasmim atomic ba **Lua** ru Redis (Octane concurrent ast؛ read-then-write e joda az limit rad mishe)، ledger e PG manba' e haghighat.
- ⚠️ **Baraye wallet fail-CLOSED ast** (bar khalaf e model e quota ke fail-open bud): Redis down → nemitunim kasr konim → service nemidim، vagarna majani midim.
- Endpoint ha: `/v1/instruments`، `/v1/providers`، `/v1/prices`، `/v1/prices/{symbol}`، `/v1/candles`، `/v1/wallet`.
- **ETag ru snapshot** — tafavot e beyn e kasr kardan baraye 5,760 poll e bi-taghir tu rooz va kasr baraye taghir haye vaghei. Customer motavajeh mishe kodum ro mikonim.

---

## Phase 5 — Website UI
- **Board e TV**: font e bozorg، contrast e bala، flash e sabz/ghermez، full-screen.
- **Jadval e moghayese**: radif = symbol، soton = provider، + kamtarin/bishtarin/miangin.
- **Chart (KLineCharts core)**: `import()` e lazy. **Na `@klinecharts/pro`** — un (0.1.1) `solid-js` ro miare va ba Alpine/Livewire va token haye ma mijange. Core khodesh indicator (MA/EMA/MACD/RSI/BOLL/KDJ) va overlay haye tarsim (trendline، Fibonacci) dare — **toolbar ro khodemun ba Alpine misazim** ta RTL/Farsi va token dast e khodemun bemune. Rang az token haye CSS، na hardcode. Mehvar e Jalali **bedun e kitabkhane** ba `Intl.DateTimeFormat('fa-IR-u-ca-persian-nu-latn')`.
- `volume` = **tedad e tick** (KLineCharts in key ro mikhahe؛ pane e sefr e saf shekaste be nazar mirese). Envelope: `{quote, precision, displayDivisor: 1, spreadVersion, degraded, data}` — `displayDivisor` baraye IRR=1 ast; UI/API/wallet hame rial. USD e ons/jahani ba scale e khodesh USD mimune.
- **Cache**: do tabaghe (fresh motanaseb e interval: m1 15s … d1 900s + `:last` 6 saat) → ghat'i e Influx mishe «bayat vali durost» na chart e khali. Worker har tick series e default ro warm mikone.
- 🔴 **Quantize kardan e `from`/`to` ru marz e interval ejbari** — agar `to` e har karbar `now()` bashe، har request kelid e yekta misaze va hit rate **sefr** ast.
- ⚠️ **Chart rooz e avval morde ast**: `d1` yek candle dare. KLineCharts ba se bar «software e kharab» be nazar mirese na «dataset e javun». (a) `Interval::availableFrom()` → interval haye bi-data **disabled** beshan، (b) **ekhtiari** backfill e yek-bare az endpoint e khod e tgju (`api.tgju.org/v1/market/indicator/summary-table-data/{symbol}`) — hamun host-i ke alan poll mikonim، pas vendor e jadid va surface e ban e jadid nadare، va chun **hamun provider** ast ba tick haye khodemun yekdast stitch mishe. Sahebe projeh «az tick haye khodemun» ro entekhab karde — in faghat yek dastur e opt-in ast agar chart e rooz e avval ghabl e ghabul nabud.
- **SEO**: `seo_meta` e polymorphic + `SeoResolver` + JSON-LD (`Product`/`Offer`/`FAQPage`) + sitemap index + `robots.txt` e dynamic.
- **Polling**: Livewire baraye **first paint** (SEO)، refresh e 15s ba Alpine `fetch` ru `GET /api/board.json` ba **ETag** e `b{boardVersion}:s{spreadVersion}`. `wire:poll.15s` e khali ya'ni yek round-trip e kamel e Livewire be ezaye har bazdid-konande har 15 saniye baraye payload-i ke baraye hame **byte-identical** ast.

---

## Phase 6 — Karbaran · Phase 7 — Ticket + KYC · Phase 8 — Site + plan + pardakht

Ezafe shod 2026-10-06 (darkhast e sahebe proje). **Task ha، API e Zohal va soal haye baz: `docs/TASKS.md` (Phase 6/7/8)** — inja faghat osul:

- **Har darkhast e karbar = ticket** (ertegha ye sath/KYC، ta'eed e site، poshtibani) ba audit e har taghir e vaziat.
- **KYC ba Zohal** posht e yek Port (shahkar، hoviat، sherkat، code posti، enamad)؛ har call pul dare → yek bar ejra + «اجرای دوباره» e admin. Test ha bedun e network (fixture)، dev ru sandbox.
- **Site ta ta'eed kar nemikone**: key/secret hamun moghe dade mishe vali `ApiHmacAuth` faghat site e `approved` + karbar e active ro ghabul mikone. Client haye mojud ba backfill `approved` mimunan.
- **Karbar e disable** = na login، na API.
- **Madarek ru disk e private**، download e emza-dar.
- Kharid e eshterak az wallet kasr mishe; call haye dakhel e sahmie e kol/rooz majani, ezafe ba nerkh e plan az wallet. Bedun e eshterak e faal PAYG e pish-farz. Zibal posht e Port; verify e idempotent va invoice e immutable.

## `CLAUDE.md` (se file)
1. `/CLAUDE.md` — entry: brand، stack، port ha، Docker، workflow.
2. `/app/Modules/CLAUDE.md` — marz e `Domain`/`Application`/`Infrastructure`، Port & Adapter، Action/Query/Dto/Enum.
3. `/resources/views/CLAUDE.md` — frontend + token haye theme.

### Non-negotiable ha
- **`Domain/` hich chiz e Laravel nemibine.**
- **Hich Eloquent e mostaghim tu Livewire/Blade/Controller.**
- **Comment ha va console output: Finglish.**
- **Hich hex / `style=""` / `<style>` tu Blade** — hame token e `@theme`.
- Alpine baraye UI-only؛ Livewire baraye data ye server-driven. Icon: faghat Tabler.
- Migration ezafe-shi. `wallet_transactions` append-only.
- Ba'd az har task e UI: **Playwright snapshot/screenshot**.

---

## Verification
1. **Docker**: hame `healthy`؛ `curl localhost:8300/up` → 200؛ hich conflict ba `hg_*`/`b2b_*`/`livewire_learn_*` vaghti har se bala-and. `php -m | grep -E 'curl|mbstring'`.
2. **Domain (Unit، bedun e Docker)**: `NumberParser` (comma، `٫`، `\t`، raghame Farsi، toman→rial)، `PriceSanity`، `SpreadPricer` (se invariant)، `QuoteEnricher`، kol e `TickPipeline` ba fake e har port.
3. **Source ha (fixture e vaghei)**: `tgju.json` va `estj_price.json` e **download shode tu hamin session** → `tests/Fixtures/`. Baraye **har instrument e har source** yek assert ru meghdar e minor (daghighan jaye bug e 10×).
4. **Tick**: `php artisan pricing:tick` → snapshot por، Redis set، `price_changes` faghat baraye taghir ha، Influx row dare، `TickReportDto` tu console.
5. **Taab-avari (har sink jodagane)**: `stop influxdb` → tick rad mishe bedun e crash، spool por، ba'd e `start` + `drain` **hich gap** nemune. Hamin baraye `redis` (→ PG fallback e board; session/queue ham unavailable) va `postgresql` (→ polling edame، board zende).
6. **Influx tasks**: `pricing:influx:health` → akharin `candles_1m` < 3 daghighe، akharin `candles_1d` emruz (Tehran)، hame ye task run `success`. ⚠️ Test e `timezone.fixed`: candle e ruzane 00:00 **Tehran** shoru she na 03:30.
7. **Spread**: avaz kon → `spread_version` bump، board **va** chart hamzaman avaz shan (asli tarin jaye vagerayi).
8. **OTP**: rate limit e phone **va** IP jodagane، code e hash-shode، enumeration safety، `max_attempts` code ro batel kone.
9. **Panel (Playwright)**: `/dashboard` → snapshot + screenshot، moghayese ba `images/dashboard-prefer.webp`.
10. `php artisan test` + `vendor/bin/pint --dirty`.


## Baseline e takmil e 2026-10-07

- Panel: `providers` salamat+config, `priorities` olaviat+manual-only+backup+Excel, `users` hoviat/audit, `tickets` madarek+gozaresh e Zohal, `seo` override metadata, `access` role/permission.
- Mali: chart of accounts, journal do-tarafe, daftar kol/tafsili/filter/CSV, reversal/correction, settlement e dargah, plan ha, site approval, invoice e immutable; customer `/account` ba wallet/site/subscription/usage.
- Rial e sahih dar kol e platform; faghat quote haye USD e jahani USD mimunan. Upstream toman faghat tu adapter be rial normalize mishe.
- Migration ha ba dastur e sarih tajmi shodan; create haye asli schema e nahayi ro misazan. Data migration e opening balance joda ast. Database e mojud reset nashod; migration haye baadi additive-and. Runbook va invariant ha dar `docs/architecture.md`.
