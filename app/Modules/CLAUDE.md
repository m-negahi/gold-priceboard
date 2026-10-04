# CLAUDE.md — Backend (`app/Modules/`)

**Khodkar load mishe** vaghti tu `app/Modules/**` kar mikoni. Marja' e vahed baraye sakht e module.
Baraye `Pricing` be khosus، skill e `pricing-architecture` ro ham bebin.

## Modular monolith + Ports & Adapters + CQRS-lite

Har module tu `app/Modules/{Module}/` khodkafa-st va **se laye** dare:

```
app/Modules/{Module}/
├── Domain/                   ← PURE PHP. hich Facade, Eloquent, Http::, config().
│   ├── ValueObjects/         meghdar haye bi-hoviat (Price, Symbol, Spread)
│   ├── Enums/                EnumTrait
│   ├── Services/             mantegh e domain — bi-halat, bedun e IO
│   ├── Ports/                interface be donya ye birun
│   └── Exceptions/
├── Application/              ← use-case ha
│   ├── Actions/              WRITE — yek use-case
│   ├── Queries/              READ  — bedun e side effect
│   ├── Dto/                  Data Transfer Object
│   └── Pipeline/             (ekhtiari) marhale haye yek farayand e chand-ghesmati
├── Infrastructure/           ← tanha jayi ke donya ye birun hast
│   ├── Persistence/          Eloquent Model + adapter e SnapshotStore
│   ├── Cache/                Redis
│   ├── Sources/ | Sms/ | …   client e biruni (implement e Port)
│   └── Support/              parser، helper e fanni
├── Livewire/                 component ha
├── Http/{Controllers,Resources,Middleware}/
├── Console/Commands/
├── Config/{module}.php       ba mergeConfigFrom
└── Providers/{Module}ServiceProvider.php
```

Module e sade (bedun e integration e biruni) mitune faghat `Models/` + `Actions/` + `Queries/` + `Dto/` dashte bashe — `Domain/Ports` va `Infrastructure/` vaghti lazem-and ke ba donya ye birun harf mizani.

## Jadval e dastresi (ejbari)

| Laye | Mojaz be seda zadan |
|---|---|
| Livewire / Controller / Blade | **faghat `Action` va `Query`** |
| `Application/Actions` | Port، `Domain/Services`، Dto، Action e digar، Query، Model (faghat tu CRUD) |
| `Application/Queries` | Port، Dto، Model — **faghat khundan** |
| `Application/Pipeline` | Port، `Domain/Services`، Dto |
| `Domain/Services` | ValueObject، Enum — **bi-halat، bedun e IO** |
| `Domain/Ports` | faghat Dto va ValueObject (interface) |
| `Infrastructure/*` | har chizi (Eloquent، Redis، Http، SDK) |
| Policy / Observer | Model |

```php
// ❌ HARGEZ tu Livewire/Controller/Blade
Provider::where('is_active', true)->get();

// ✅
$this->providerQuery->active();
$this->providerAction->update($dto);
```

## 🔴 `Domain/` hich chiz e Laravel nemibine

Agar tu `Domain/` yeki az inha ro didi، ghalat ast: `use Illuminate\…`، `Cache::`، `Http::`، `DB::`، `config()`، `now()`، `app()`.

- Config be onvan e **argument** pass mishe، na `config()`.
- Zaman az `ClockPort` miad، na `now()` — ta test determinist bashe.

**Chera**: kol e mantegh ba PHPUnit e khali test mishe، bedun e Docker/DB/Redis. Agar baraye test kardan e yek mantegh majbur shodi DB bala biari، un mantegh tu laye ye ghalat neshaste.

## Port & Adapter

```php
// Domain/Ports/SmsPort.php
interface SmsPort { public function send(string $phone, string $text): void; }

// Infrastructure/Sms/KavenegarSms.php
final readonly class KavenegarSms implements SmsPort { /* … */ }
```

Port e jadid = 5 gam، **hich kodum ekhtiari nist**:
1. interface tu `Domain/Ports/` (faghat Dto/VO tu signature)
2. adapter tu `Infrastructure/`
3. fake tu `tests/Support/Fakes/`
4. bind tu ServiceProvider e module
5. **test ke bind ro assert kone**

Gam e 5 ejbari ast chun tu projeh ye marja' (`hg_website`) yek `scoped()` ru class e namojud bind shode bud va memoization **hichvaght kar nakard** — kasi ta mah ha nafahmid.

## Action / Query

**Naming**: CRUD → `{Entity}Action` · business → `{Verb}{Entity}Action`.
Yek Action **yek kar** mikone. Agar esm esh «va» dare، do ta Action ast.

```php
/** @extends BaseAction<Provider> */
final readonly class ProviderAction extends BaseAction
{
    protected function model(): string { return Provider::class; }
}

final readonly class ProviderQuery extends BaseQuery
{
    protected function model(): string { return Provider::class; }

    /** @return Collection<int, Provider> */
    public function active(): Collection
    {
        return $this->query()          // hamishe Builder e taze — Octane-safe
            ->where('is_active', true)
            ->orderBy('sort')
            ->get();
    }
}
```

`$this->query()` hamishe Builder e taze mide. Builder e zakhire-shode tu property zire Octane beyn e request ha nasht mikone.

## Controller / Livewire

Nazok. `Query`/`Action` ro **constructor-inject** kon. Hich data-shaping tu Controller ya Blade.

```php
public function show(string $key): View
{
    $provider = $this->providerQuery->findByKeyOrFail($key, with: ['spreads']);

    return view('…', ['provider' => (new ProviderResource($provider))->resolve()]);
}
```
Eager-load **az birun** pass mishe (`with:`)، na penhan tu Query.
Resource baraye Blade → hatman `->resolve()` (tu-dar-tu ham sarih، chun `resolve` bazgashti nist).

## Gheymat — hargez float

```php
new Price(minor: 266_400_000, quote: QuoteCurrency::IRR);   // 266,400,000 rial
new Price(minor: 41_401_900,  quote: QuoteCurrency::USD);   // $4140.19 (scale 4)
```
`minor = major × 10^scale`. **Int e kham hargez az `Price` birun nemire.**
Parse ba **bcmath**، na `(float)` — `(float)` ru adad haye rial saket daghighat gom mikone.

## Model / Migration

- Model ha **faghat tu `Infrastructure/Persistence/Models/`**.
- Migration tu `database/migrations/` (flat، global) — **hamishe ezafe-shi**. Hich `migrate:fresh` ru data ye vaghei.
- Model ba `@property` docblock e kamel، `$fillable`، `$casts`، `$hidden`.
- Model ha `final` **nistan** (Eloquent lazem dare extend beshan).

## Config e module

```php
// Providers/{Module}ServiceProvider.php
$this->mergeConfigFrom(__DIR__.'/../Config/pricing.php', 'pricing');
```
Tu `app/Modules/{Module}/Config/` mimune — publish nemishe tu `config/`.
Provider e har module dasti tu `bootstrap/providers.php` sabt mishe (auto-discovery nadarim).

## Style

- `declare(strict_types=1);` — khat e aval e **har** file (migration ham).
- `final readonly class` baraye Action / Query / Service / Driver / Adapter / Support e bi-halat.
- `final class` baraye Dto / Resource / Event / Command.
- **Na** `final` baraye Model va Livewire component.
- Enum ha: `use EnumTrait` + `labels(): array` (meghdar → label e Farsi).
- **Comment ha va output e console: Finglish.** Docblock tozih mide **chera**، na **chi**. Bug haye vaghei ro tu docblock sabt kon.

## Ghabl az commit

- [ ] `Domain/` hich `use Illuminate\…` nadare
- [ ] Hich Eloquent tu Livewire/Controller/Blade
- [ ] Port e jadid: interface + adapter + fake + bind + **test e bind**
- [ ] `declare(strict_types=1)` hame ja
- [ ] Comment ha Finglish
- [ ] `vendor/bin/pint --dirty`
