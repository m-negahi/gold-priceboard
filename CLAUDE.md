# CLAUDE.md — PriceBoard

Noghte ye vorud. Ghanun haye joz'i tu file haye paeen-tar-and va **khodkar load mishan** vaghti tu un folder kar mikoni.

## Project

- **Chi hast**: site e moghayese ye gheymat e **tala / noghre / mes** beyn e provider haye Irani (tgju, estjt, va ba'dan platform ha) + **foroosh e API** be customers.
- **Stack**: Laravel 13 · PHP 8.5 · PostgreSQL 18 · Redis 8 · **InfluxDB 2.7** · Octane/Swoole · Livewire 4 · Alpine 3 · Tailwind v4 · Vite 8 · Docker
- **Chart**: KLineCharts 10 (self-host — TradingView ru host e ma **block** ast)

## ⚠️ Ghanun e shomare yek: zaban

**Hame ye comment ha, docblock ha, va output e console be Finglish neveshte mishan.**
Na Farsi, na English.

```php
// ✅ Dorost
// Agar provider timeout khord, baghi provider ha nabayad ghat' shan.
$this->info("Gheymat be-rooz shod: {$count} symbol.");

// ❌ Ghalat
// اگر provider تایم‌اوت خورد...
// If the provider times out...
```
Estesna: matn e **UI** (Blade / label e panel) Farsi ast — un chizi-e ke karbar mibine.

## Folder Map

```
/                             # in file — entry + workflow
├── app/Modules/CLAUDE.md     # me'mari ye backend — auto-load tu app/Modules/**
├── resources/views/CLAUDE.md # frontend — auto-load tu resources/views/**
├── .claude/skills/           # dastur e amal e gam-be-gam (paeen)
└── docs/
    ├── PLAN.md               # plan e kamel e projeh (manba' e haghighat)
    ├── TASKS.md              # ⬅️ GHABL AZ HAR KAR INO BEKHUN
    ├── architecture.md       # laye ha + port/adapter, amigh
    └── dashboard-design.md   # token ha + component haye theme
```

## ⬅️ Shoru' e har session

```bash
cat docs/TASKS.md
```
Bakhsh e **«Hala koja hastim»** mige akhari koja bud va gam e ba'di chi-e. Ba'd az **har** task e tamum shode، hamun lahze `- [x]` kon + yek khat benevis ke chi shod. Agar vasat e rah tasmimi gerefti ke tu `docs/PLAN.md` nist → tu bakhsh e **«Tasmim haye vasat e rah»** sabt kon.

## Skills (`.claude/skills/`)

| Skill | Kay |
|---|---|
| `add-price-provider` | provider e jadid ezafe mikoni (wallgold, taline, …) |
| `pricing-architecture` | har taghiri tu `app/Modules/Pricing/**` |
| `influx-timeseries` | Flux ya task e Influx minevisi/avaz mikoni |
| `dashboard-ui` | safhe ye panel misazi |
| `docker-dev` | mohit ro ejra/debug mikoni |

## Docker (dev)

```bash
docker compose -f docker-compose.local.yml up -d
docker compose -f docker-compose.local.yml exec octane php artisan <cmd>
docker compose -f docker-compose.local.yml exec octane composer <cmd>
docker compose -f docker-compose.local.yml exec octane php artisan test
```

| Service | URL / Port |
|---|---|
| App (Octane) | http://localhost:8300 |
| Vite HMR | http://localhost:5373 |
| Postgres | `localhost:55434` |
| Redis (cache/queue) | `localhost:56381` |
| Redis (prices) | `localhost:56382` |
| InfluxDB | http://localhost:8386 |
| Panel | http://localhost:8300/dashboard |

In port ha amdan shift shodan ta ba projeh haye digar (`hg_*` 8100, `b2b_*` 8200, `livewire_learn_*` 8000) conflict nakhoran. **Hichvaght avazeshun nakon bedun e check kardan e `docker ps -a`.**

Taghir e `.env` ba `octane:reload` sovar **nemishe** → `up -d --force-recreate`.

## Tartib e phase ha

Panel aval، website e UI ba'd.

| # | Phase | Hālat |
|---|---|---|
| 0 | Bootstrap (docs, docker, laravel) | — |
| 1 | Pricing engine (bedun e UI) | — |
| 2 | Auth (mobile + OTP) | — |
| 3 | Panel e admin | — |
| 4 | Wallet + API | — |
| 5 | Website UI (board, chart, SEO) | — |

Hālat e daghigh tu `docs/TASKS.md`.

## Non-negotiable ha

- **Comment/console: Finglish.** (bala)
- **`Domain/` hich chiz e Laravel nemibine** — na Facade، na Eloquent، na `Http::`. → `app/Modules/CLAUDE.md`
- **Hich Eloquent e mostaghim tu Livewire/Blade/Controller.** Hame az `Action`/`Query`.
- **Hich hex / `style=""` / `<style>` tu Blade.** Hame Tailwind + token e `@theme`. → `resources/views/CLAUDE.md`
- **Alpine baraye UI-only؛ Livewire baraye data ye server-driven.**
- **Icon: faghat Tabler.**
- **Migration hamishe ezafe-shi.** Hich `migrate:fresh` ru data ye vaghei.
- **`wallet_transactions` append-only** — hich `update`، hich `delete`.
- **Gheymat hamishe `bigint` e minor unit + `scale`** — hargez float. → `app/Modules/CLAUDE.md`
- **Spread ru `read` e'mal mishe، hargez `write`.** Dalil tu `docs/PLAN.md` §1.7.
- Ba'd az har task e UI: **Playwright snapshot/screenshot** + moghayese ba `images/dashboard-prefer.webp`.

## Commit

Type: `feat / fix / refactor / docs / chore / test`. Commit e ma'ni-dar، na batch tu akhar e session.
