# PriceBoard

Tablo ye moghayese ye gheymat e **tala / noghre / mes** beyn e provider haye Irani (tgju، estjt، nerkh، milli، talasea، technogold، tlyn) + foroosh e API be customer ha (wallet، plan e eshteraki، factor).

**Stack**: Laravel 13 · PHP 8.5 · PostgreSQL 18 · Redis 8 (yek instance، DB0/1/2) · InfluxDB 2.7 · Octane/Swoole · Livewire 4 · Alpine 3 · Tailwind v4 · Vite 8 · KLineCharts 10 (self-host) · Docker.

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
$dc php artisan pricing:tick --force     # yek tick e kamel، gozaresh e Finglish tu console (--json ham dare)
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

## Ghanun haye kelidi (kholase — kamel tu `CLAUDE.md`)

- Comment/console/gozaresh **Finglish**؛ matn e UI Farsi؛ adad ha ba raghame Latin.
- `Domain/` hich chiz e Laravel nemibine؛ hich Eloquent tu Livewire/Blade/Controller (faghat `Action`/`Query`).
- Gheymat hamishe `bigint` e minor unit + `scale` — hargez float. Hame ye mablagh ha **rial**؛ faghat quote haye jahani (ons/noghre/mes) USD.
- Spread ru **read** e'mal mishe، na write. `wallet_transactions` va `finance_journals` append-only.
- Hich test-i be provider/Zohal/Vandar/Zibal/Kavenegar e vaghei network nemizane — fixture tu `tests/Fixtures/`.

## Test

```sh
$dc php artisan test --compact        # PHPUnit: SQLite in-memory + Redis DB13/14/15 (dev dast nemikhore)
php vendor/bin/phpstan analyse            # Larastan L6 (`composer typecheck`)
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
