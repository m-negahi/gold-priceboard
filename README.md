# PriceBoard — تابلو قیمت

Tablo ye **gheymat e lahze-i ye tala، sekke va noghre** ke chand manba' e Irani ro hamzaman mikhune، kenar e ham moghayese mikone، va hamun feed ro be sorat e **API e foroushi** (ba wallet، plan e eshteraki va factor e rasmi) be kasb-o-kar ha mide.

> Falsafe ye proje: **hich adadi hads zade nemishe.** Har gheymat az yek manba' e vaghei miad، az chand laye sanity rad mishe، ba tarikhche sabt mishe — va age manba'-i oftad، nafar e ba'di bi-vaghfe jash ro migire.

![Tablo ye omumi — moghayese ye chand manba'](docs/screenshots/public-board.png)

---

## Fehrest

- [Emkanat](#emkanat)
  - [Site e omumi](#1-site-e-omumi)
  - [Motor e gheymat](#2-motor-e-gheymat-pricing-engine)
  - [Manba' haye gheymat](#3-manba-haye-gheymat)
  - [Panel e admin](#4-panel-e-admin)
  - [API e foroushi + panel e moshtari](#5-api-e-foroushi--panel-e-moshtari)
  - [Mali va hesabdari](#6-mali-va-hesabdari)
  - [Vorud، karbaran va dastresi](#7-vorud-karbaran-va-dastresi)
  - [Taab-avari va monitoring](#8-taab-avari-va-monitoring)
- [Sakhtar e fani](#sakhtar-e-fani)
- [Quickstart (local)](#quickstart-local)
- [Test](#test)
- [Mostanadat](#mostanadat)

---

## Emkanat

### 1) Site e omumi

- **Tablo ye moghayese** (safhe ye asli): har nemad × har manba'، ba کمترین/بیشترین، darsad e taghir، jahat (sabz/ghermez)، badge e kohnegi، va marker e «محاسبه‌شده» baraye gheymat haye derived. Gheymat ha be **toman** namayesh dade mishan (zakhire hamishe rial e). SSR + poll e 15 saniye ba ETag + flash e taghir — bedun e reload.
- **Safhe ye har nemad** (`/price/{symbol}`): chart e zende، moghayese ye manba' ha، nemad haye ham-goruh، va SEO ye kamel (canonical/OG، JSON-LD e `Product`/`AggregateOffer` ba gheymat e daghigh، sitemap e per-symbol، robots e dynamic).
- **Board e TV** (`/tv`): tamam-safhe، zamine ye tire، font be andaze ye kashi (`cqi`)، sa'at/tarikh e shamsi، Wake Lock، full-screen — baraye namayeshgar e maghaze. Har kashi **yek** gheymat e mo'asser e (hamun adadi ke API mide).
- **Sefr request e biruni**: font self-host (Vazirmatn)، chart self-host (KLineCharts — TradingView tu Iran block e)، hich CDN-i. Ba e2e tazmin shode.
- RTL e kamel، raghame Latin hame ja، responsive ta 375px.

| | |
|---|---|
| ![Safhe ye nemad ba chart va SEO](docs/screenshots/public-instrument.png) | ![Board e TV e tamam-safhe](docs/screenshots/public-tv.png) |

### 2) Motor e gheymat (Pricing Engine)

Ghalb e proje: yek **pipeline e 5 marhale-i** (`Fetch → Validate → Enrich → Persist → Publish`) ke har 15 saniye tavasot e yek worker e joda (grid-aligned، bedun e drift) ejra mishe.

- **Fetch e hamzaman** ba `Http::pool` — kond-tarin provider tool e tick ro moshakhas mikone، na jam' e hame. (estjt ~7s javab mide؛ seri mizadim، provider e sevom tick ro mishkast.)
- **Retry e hooshmand** faghat baraye khata haye gozara ye ettesal (TLS/connect/reset) — timeout va 4xx/5xx amdan retry nemishan؛ retry ha ham hamzaman-and va be budget e baghi-mande clamp mishan.
- **Sanity e chand-laye**: band e motlagh e har nemad، enheraf e tick-be-tick، check e **cross-provider ba miane** baraye avvalin gheymat e har provider e jadid (zed e khata ye 10× va toman/rial)، va **marja' e e'temad** (trust anchor) ke admin entekhab mikone.
- **Tazegi (freshness) per-(provider, nemad)**: feed haye Irani shab ha va jom'e ha yakh mizanan، nemad haye jahani akhar e hafte ye jahani — har kodum saghf e khodeshun ro daran؛ gheymat e kohne ba **badge e sen** mimune، gom nemishe.
- **Gheymat hichvaght float nist**: hamishe `bigint` e minor unit + `scale`، parse ba bcmath. Spread e har manba' ru **read** e'mal mishe، na write — adad e kham e tarikhche hich vaght dast nemikhore.
- **Zarabān e nevisande (heartbeat)**: age writer e tablo az kar oftad (hatta OOM e saket e Redis)، tablo khodkar be Postgres fallback mikone va `degraded` e'lam mishe.
- **Spool e Influx**: ghat'i ye InfluxDB hich tick-i ro nemindaze — tick ha tu Redis spool mishan، ba replay bar migardan va candle haye hamun baze **dobare sakhte mishan** (surakh e daemi tu chart peyda nemishe).

### 3) Manba' haye gheymat

Har manba' yek adapter e `QuoteSourcePort` e ba **fixture ye vaghei** va test e adad-be-adad:

| Manba' | No' | Chi mide | Nokte ye fani |
|---|---|---|---|
| اتحادیه طلا و جواهر تهران (estjt) | marja' | 9 nemad | HTML scrape ba DOMXPath؛ separator e `٫`؛ HTTP/1.1 ejbari |
| تی‌جی‌جی‌یو (tgju) | marja' | 16 nemad (+jofte ye abshode) | timestamp e per-symbol؛ feed e 963 kelid-i ke faghat map-shode ha khunde mishan |
| نرخ (nerkh) | marja' | 14 nemad | `MAZANEH` → **abshode** (÷4.3318)؛ **noghre ye 999/925 e mohasebati** az ons×dollar e khod e feed |
| وال‌گلد (wallgold) | platform | abshode + noghre 925 (jofte) | Bearer token؛ har (symbol،side) yek request e joda |
| طلای ملی (melligold) | platform | abshode (jofte) | challenge e cookie ye ArvanCloud handle mishe |
| اکوگلد (ecogold) | platform | abshode، 3 sekke، noghre 999 (jofte) | toman ba 8 raghame ashar |
| طلایین (tlyn) | platform | abshode، mesghal، 3 sekke، ons (jofte) | gheymat be hezar-toman |
| **API اختصاصی** | dakheli | hame ye nemad ha | gheymat e khod e ma — paeen ro bebin |

- **«18 ayar» e platform ha dar vaghe' abshode ye moamelati-e** — hame be `xau-melted-g-irr` map shodan ta moghayese sadegh bashe.
- **Olaviat e per-nemad**: baraye har nemad zanjire ye manba' ghabel e tanzim e (masalan abshode: aval اتحادیه، bad tgju، …). Feed e mo'asser hamishe avvalin manba' e **taze** ye zanjire-ast.
- **«API اختصاصی»** (provider e dakheli): gheymat e khod e ma ke **khodkar** az zanjire ye olaviat por mishe، admin mitune per-nemad override kone (halat e «فقط دستی»، import e Excel)، va age hame ye manba' ha ghat' shan، feed e foroushi az hamin zende mimune — ba alarm.
- Ezafe kardan e manba' e jadid yek dastur-e-amal e 7 ghadame dare (`.claude/skills/add-price-provider`) — fixture ye vaghei، test e exhaustive، va moghayese ba tgju ejbari-e.

### 4) Panel e admin

Panel e kamel e Farsi/RTL ru `/dashboard` (Tailwind v4 + Alpine، theme e token-base):

- **میزکار**: stat card ha، salamat e har manba' (latency، nemad، rad، khata)، gauge، jadval e moghayese ye zende.
- **منابع قیمت**: tanzimat + salamat e har manba' tu yek kart؛ roshan/khamoosh e **bedun e reload** (SPA)؛ spread e per-nemad ba preview e server-side؛ marja' e e'temad؛ logo az ketabkhane ye Media.
- **تنظیم قیمت نمادها**: zanjire ye olaviat e har nemad (drag/bala/paeen)، vaziat e «API اختصاصی»، import/export e Excel ba preview e radif-be-radif.
- **ماتریس نمادها**: har khune = gheymat + sen + ekhtelaf e beyn e manba' ha؛ arzun-tarin/geran-tarin har radif.
- **نمودار قیمت**: candle/khati az 5 daghighe ta **salane ba taghvim e shamsi** (hafte az shanbe)؛ overlay e chand manba' ru yek chart ba tooltip e ekhtelaf؛ deep-link e share-pazir.
- **پایش**: gozaresh e har tick (chi ghabul/rad shod va chera) + **mini-Telescope e upstream**: har request e vaghei be provider ha ba header/body/duration/retry، ba redaction e secret ha.
- **مشتریان**: CRUD، disable/enable (session ha fori mioftan)، sharj/kasr e dasti ye wallet ba dalil va audit، chart e masraf e API، virayesh ba audit e ghabl/ba'd.
- **تیکت‌ها**: inbox، goft-o-gu ba peyvast e private (link e emza-dar)، gozar e vaziat ba tarikhche، SMS e etela'-resani.
- **دسترسی (RBAC)**: naghsh/permission e ghabel e ta'rif tavasot e khod e admin، guard e «akharin modir».
- **سئو / رسانه**: editor e `seo_meta` e har safhe؛ ketabkhane ye upload ba joloogiri az hazf e file e dar-hal-e-estefade.

| | |
|---|---|
| ![Mizkar](docs/screenshots/panel-dashboard.png) | ![Manba' haye gheymat](docs/screenshots/panel-providers.png) |
| ![Tanzim e gheymat e nemad ha](docs/screenshots/panel-priorities.png) | ![Chart e admin ba overlay e manba' ha](docs/screenshots/panel-charts.png) |
| ![Matrix e nemad ha](docs/screenshots/panel-matrix.png) | ![Payesh e upstream](docs/screenshots/panel-upstream.png) |

### 5) API e foroushi + panel e moshtari

- **Auth e HMAC**: emzaye canonical ba client/secret، nonce ye yek-bar-masraf (fail-closed)، skew e ±30s، decoy-HMAC zed e timing.
- **Endpoint ha**: `GET /v1/{instruments, providers, prices, prices/{symbol}, effective, candles, wallet}` — hame ba ETag (pasokh e 304 **pul nemigire**).
- **Billing e per-call**: reserve e atomic ba Lua ru Redis + sabt tu ledger e PG (manba' e haghighat)؛ **fail-closed** (Redis ya PG ghat' → 503، service ye majani nemidim).
- **Idempotency e vaghei**: `X-Pb-Request-Id` e sabet → hamun pasokh ta 24 sa'at bedun e kasr e dobare؛ hamun id ba request e motefavet → 409.
- **Refund e khodkar** ru khata/304 — va age DOROST hamun lahze DB ghat' bud، refund tu saf e paydar mimune va scheduler replay esh mikone (pul e moshtari gom nemishe).
- **Plan e eshteraki**: admin plan misaze (moddat، sahmie ye kol، saghf e ruzane، nerkh e mazad)؛ call e dakhel e sahmie majani، mazad pay-as-you-go؛ saghf e ruzane → 429.
- **Panel e self-serve** (`/account`): sakht e site (credential yek bar namayesh)، kharid e plan az wallet، sharj ba dargah e **Vandar** (verify e idempotent)، ledger، chart e masraf، ticket.
- **Ta'eed e site**: sabt e site → ticket e khodkar → estelam e **enamad** → ta'eed e admin → API fa'al.
- **KYC ba Zohal**: shahkar (mobile↔code melli)، hoviat e haghighi، sherkat، code posti — gozaresh e khodkar ru ticket (har estelam pul dare؛ faghat yek bar + dokme ye ejra ye dobare).
- **Factor e rasmi** ba shomare ye motavali (`PB-YYYY-NNNNNN`)، snapshot e **gheyr-ghabel-taghir** az hoviat e ta'eed-shode (faghat az profile e approve-shode ye KYC).

### 6) Mali va hesabdari

- `wallet_transactions` **append-only** — ba **trigger e database** (UPDATE/DELETE/TRUNCATE rad mishe، hatta cascade).
- Hesabdari ye **do-tarafe** (journal) baraye har harekat e pul.
- Safhe ye «امور مالی»: daftar e kol e hame ye karbaran ba filter/search/CSV، **gozaresh e mahane be taghvim e Jalali**، tasviye ye dargah، sodur e factor e dasti، sharj e kif e pul.
- «Virayesh/hazf» e sanad vojud nadare — faghat **sanad e bargasht** (ebtal) ba dalil، admin va reference be sanad e asli. Idempotent.

![Safhe ye mali](docs/screenshots/panel-finance.png)

### 7) Vorud، karbaran va dastresi

- **Login faghat ba mobile + OTP** — hich password-i zakhire nemishe.
- Code ha **hash e HMAC** tu Redis (dump e Redis bedun e APP_KEY bi-estefade-ast)، yek-bar-masraf ba consume e atomic، 3 ghalat = batel.
- **Rate limit e joda baraye phone va IP** (ersal va verify)، enumeration-safe (pasokh baraye shomare ye mojud/namojud byte-be-byte yeki-e)، zed e session fixation.
- Karbar e disable-shode: session haye baz fori mioftan، OTP e dorost ham rad mishe، API haye site hash 403 migiran.
- Audit e kamel e taghirat e profile (ki، kay، ghabl/ba'd)؛ taghir e hoviat ba'd az KYC، ta'eed ro **khodkar batel** mikone.

![Lista ye moshtarian](docs/screenshots/panel-users.png)

### 8) Taab-avari va monitoring

Senario haye ghat'i **ru mohit e vaghei** test shodan (`scripts/resilience-check.sh`، 30 check):

| Chi ghat' she | Chi mishe |
|---|---|
| InfluxDB | tick ha spool mishan؛ ba replay bar migardan va candle ha dobare sakhte mishan — **hich gap-i** |
| Redis | tablo be Postgres fallback mikone va khodesh ro `degraded` e'lam mikone |
| PostgreSQL | tick amdan rad mishe (bedun e sanity nemineveisim) vali tablo az Redis zende mimune |
| Yek provider | baghi edame midan؛ feed e mo'asser be manba' e ba'di ye zanjire mire |
| Hame ye provider ha | «API اختصاصی» (akharin gheymat e salem) serve mishe + alarm tu panel |

- **Horizon** (queue) va **Telescope** (faghat local، ba redaction) posht e permission.
- `pricing:influx:health`: 5 check e salamat e candle/task/spool — scheduler har 5 daghighe.
- Log e structured e JSON per-line baraye har tick (`pricing.upstream` / `pricing.stored` / `pricing.rejected`) — amade ye Filebeat/Kibana.

---

## Sakhtar e fani

**Stack**: Laravel 13 · PHP 8.5 · PostgreSQL 18 · Redis 8 (yek instance، DB0/1/2) · InfluxDB 2.7 · Octane/Swoole · Alpine 3 · Tailwind v4 · Vite 8 · KLineCharts 10 (self-host) · Docker · Playwright.

### Me'mari: Modular Monolith + Ports & Adapters + CQRS-lite

```
app/Modules/{Module}/
├── Domain/           ← PHP e khales: ValueObject، Enum، Service، Port (interface)
│                        hich chiz e Laravel inja nist — na Facade، na Eloquent، na config()
├── Application/      ← use-case ha: Action (write) · Query (read) · Dto · Pipeline
└── Infrastructure/   ← tanha jayi ke donya ye birun hast: Eloquent، Redis، Http، Influx
```

- UI (Controller/Blade) **faghat** `Action`/`Query` seda mizane — hich Eloquent e mostaghim. Yek test e me'mari (`LayerRulesTest`) in ghanun ha ro ru **source code** enforce mikone + Larastan level 6.
- Har integration e biruni yek **Port** e ba adapter + fake + bind + **test e bind**. Kol e pipeline e gheymat bedun e Docker/DB test mishe.
- Zire Octane hich halat e moshtarak e khatarnaki nist: client haye mutable bind nemishan (Influx va Zohal ba `Http` e khod e Laravel-and، na SDK).

### Module ha

| Module | Kar |
|---|---|
| `Pricing` | motor e gheymat: source ha، pipeline، sanity، spread، olaviat، board/TV/chart/SEO feed |
| `Api` | HMAC auth، charge e per-call، plan/eshterak، usage، inspector |
| `Wallet` | kif e pul e append-only + idempotency |
| `Billing` | dargah e Vandar، topup، factor e rasmi |
| `Finance` | daftar e kol، journal، gozaresh e mahane، ebtal/eslah |
| `Auth` | OTP، SMS (Kavenegar)، karbaran، audit |
| `Access` | RBAC (naghsh/permission e dynamic) |
| `Ticket` | ticket + peyvast e private + gozar e vaziat |
| `Kyc` | onboarding، estelam haye Zohal، ta'eed e sath |
| `Seo` | meta/JSON-LD/sitemap/robots + editor |
| `Media` | upload + ketabkhane + usage tracking |
| `Panel` / `Common` | shell e dashboard، component ha، helper ha (Money، Jalali، PhoneHelper، …) |

### Jaryan e yek tick (har 15 saniye)

```
price-worker (container e joda، grid-aligned)
   │
   ▼
Fetch ──── Http::pool e hamzaman + retry e khata ye gozara + log e upstream
   ▼
Validate ─ band e motlagh · enheraf e tick-be-tick · tazegi ye per-(provider,nemad)
           · cross-provider e avvalin tick (miane) · trust anchor
   ▼
Enrich ─── jahat، band e rooz (reset ba nime-shab e Tehran)
   ▼
Persist ── Postgres (snapshot) + Redis (tablo ye dagh، yek kelid) + Influx (tick)
           + backup e khodkar e «API اختصاصی»       ← hame isolated: yek sink boyofte، baghi zende-and
   ▼
Publish ── bump e version + heartbeat
```

### Se store، se naghsh

| Store | Naghsh |
|---|---|
| **PostgreSQL** | manba' e haghighat: akharin snapshot ha، provider ha، karbar/wallet/ledger (~35 jadval، migration hamishe ezafe-shi) |
| **Redis** (DB0 session/queue · DB1 cache · DB2 prices، `noeviction`+AOF) | tablo ye dagh tu **yek kelid** (yek round-trip)، lock e tick، mirror e billing، spool |
| **InfluxDB 2.7** | tick haye kham (30 rooz) + candle haye `1m…1d` ba task haye Flux؛ haftegi/mahane/salane **ba taghvim e Jalali tu PHP** tajmi' mishan |

### Keyfiat

- **1,446 test e PHP** (6,433 assertion) — SQLite in-memory + Redis e test (DB13/14/15)؛ hich test-i be provider/dargah/SMS e vaghei network nemizane (fixture haye vaghei tu `tests/Fixtures/`).
- **127 spec e Playwright** (e2e) ru stack e vaghei ye Docker — az login e OTP ta kharid e plan va tasviye.
- **Mutation check** ru mekanism haye hassas: test-i ke ba kharab kardan e code sabz bemune، hichi ro sabt nakarde.
- **Larastan level 6** + Pint + test e me'mari.
- Fake haye port **gharardad e vaghei ro emza mikonan** (contract test ru har do implementation) — fake ye mehraban-tar az adapter mamnu'.

---

## Quickstart (local)

```sh
cp .env.example .env            # credential ha + APP_KEY ro khodet por kon؛ .env ro commit nakon
docker compose -f docker-compose.local.yml up -d --build

dc="docker compose -f docker-compose.local.yml exec octane"
$dc php artisan migrate --force                 # hamishe ezafe-shi — hich migrate:fresh ru data ye vaghei
$dc php artisan db:seed                         # ProviderSeeder (hame gheyr-fa'al) + AdminSeeder
$dc php artisan pricing:influx:provision --tokens   # bucket/task haye Influx + token haye read/write → .env
docker compose -f docker-compose.local.yml up -d --force-recreate   # ba'd az har taghir e .env

npm install && npm run build
$dc php artisan octane:reload                   # ba'd az har build e asset
```

Ba'd: tu panel (`/dashboard/providers`) provider haye lazem ro **fa'al** kon va yek marja' e e'temad (trust anchor) entekhab kon. Container e `price-worker` har 15 saniye `pricing:tick` mizane؛ dasti ham mishe:

```sh
$dc php artisan pricing:tick --force     # yek tick e kamel، gozaresh tu console (--json ham dare)
$dc php artisan pricing:influx:health    # salamat e candle ha / task ha
```

| Chi | Koja |
|---|---|
| Site e omumi (board + chart) | http://localhost:8300 · `/tv` board e TV |
| Panel e admin | http://localhost:8300/dashboard (login ba mobile + OTP؛ `BYPASS_OTP` faghat local) |
| Hesab e customer | http://localhost:8300/account |
| API e foroushi | `GET /v1/{instruments,providers,prices,prices/{symbol},effective,candles,wallet}` ba HMAC |
| Horizon / Telescope | `/horizon` · `/telescope` (faghat admin ba permission e `monitoring.view`؛ Telescope faghat local) |
| Vite HMR · Postgres · Redis · Influx | 5373 · 55434 · 56381 · 8386 |

Port ha amdan shift shodan ta ba projeh haye dige conflict nakhoran — avazeshun nakon bedun e `docker ps -a`.

Kelid haye ekhtiari ye `.env`: `WALLGOLD_API_KEY` (manba' e wallgold)، `VANDAR_API_KEY` (dargah)، `ZOHAL_TOKEN` (KYC)، `KAVENEGAR_*` (SMS) — bedun eshun hamun bakhsh gheyr-fa'al e vali hichi nemishkane.

## Test

```sh
$dc php artisan test --compact        # PHPUnit: SQLite in-memory + Redis DB13/14/15 (dev dast nemikhore)
$dc composer typecheck                # Larastan L6
npx playwright test                   # e2e az HOST؛ stack bayad bala bashe؛ 1 worker
$dc vendor/bin/pint --dirty
```

Test e PostgreSQL e mali (`tests/Integration/FinancePostgresTest.php`) faghat ba `PB_TEST_PG_DATABASE=priceboard_verify_*` ejra mishe — hargez ru DB e dev.

## Mostanadat

- [`docs/TASKS.md`](docs/TASKS.md) — **koja hastim** (aval in ro bekhun)
- [`docs/PLAN.md`](docs/PLAN.md) — plan va tasmim ha (chi va chera)
- [`docs/architecture.md`](docs/architecture.md) — laye ha، pipeline، manba' ha، mali، schema، ops، test
- [`docs/dashboard-design.md`](docs/dashboard-design.md) — token ha va component haye theme
- [`CLAUDE.md`](CLAUDE.md) · [`app/Modules/CLAUDE.md`](app/Modules/CLAUDE.md) · [`resources/views/CLAUDE.md`](resources/views/CLAUDE.md) — ghanun haye kar
- `.claude/skills/` — dastur e amal e gam-be-gam (`add-price-provider`، `pricing-architecture`، `influx-timeseries`، `dashboard-ui`، `docker-dev`)
