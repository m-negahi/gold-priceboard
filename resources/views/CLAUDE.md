# CLAUDE.md — Frontend (`resources/views/`)

**Khodkar load mishe** vaghti tu `resources/views/**` kar mikoni.
Token ha va component haye theme: `docs/dashboard-design.md` · skill: `dashboard-ui`.

## 🔴 Hich hex، hich `style=""`، hich `<style>`

```blade
{{-- ❌ --}}
<div style="background:#1f7a4d">
<div class="bg-[#1f7a4d]">
<style> .card { border-radius: 32px } </style>

{{-- ✅ --}}
<div class="bg-brand-700 rounded-3xl">
```
Har rang / radius / shadow / spacing bayad **token e `@theme`** tu `resources/css/app.css` bashe.
Har class string ke **≥ ۳ bar** tekrar she → `@utility` mishe.

## Brand ≠ up/down

| Hadaf | Token | Koja |
|---|---|---|
| Brand (chrome) | `brand-*` | sidebar e fa'al، dokme ye asli، card e featured |
| Gheymat bala raft | `up` | faghat adad / arrow / flash e gheymat |
| Gheymat paeen umad | `down` | faghat adad / arrow / flash e gheymat |
| Tala / noghre / mes | `metal-gold` / `metal-silver` / `metal-copper` | faghat icon va chip e symbol |

Tu bazaar e mali sabz ya'ni **so'ud**. Rang e brand e ma ham sabz ast — agar hamun token ro baraye har do bezani، tu board kasi nemifahme sabz «brand» ast ya «gheymat bala raft». **`brand-*` ro baraye taghir e gheymat estefade nakon.**

## Alpine vs Livewire

| Kar | Chi |
|---|---|
| toggle e menu / dropdown / modal / tab / accordion | **Alpine** |
| sidebar e collapse، dark toggle، filter e client-side | **Alpine** + `localStorage` |
| har chizi ke ba server sync mishe | **Livewire** |
| jadval e filter/paginate، form e validation-dar، gheymat e zende | **Livewire** |

5 ghanun e sakht:
1. UI-only → **hamishe Alpine** (~200× sabok tar).
2. Har chi ba server sync mishe → **Livewire**. **Hich fetch/AJAX e Alpine-only.**
3. **Hargez Alpine va Livewire ru yek interaction.**
4. Livewire hamishe SSR — hich render e faghat-client (SEO).
5. Alpine hamishe ba **`x-cloak`**.

`wire:navigate` ru **har** link e dakheli (`mailto:`/`tel:`/biruni estesna). Natije: state e Alpine ba navigate reset mishe → chiz haye paydar (masalan `collapsed` e sidebar) tu `localStorage` ba `x-init="$watch(...)"`.

## Blade faghat namayesh ast

```blade
{{-- ❌ --}}
@php $providers = \App\Models\Provider::where('is_active', true)->get(); @endphp

{{-- ✅ data az Controller/Livewire miad: Query → Resource → resolve() --}}
@foreach ($providers as $provider)
```
Hich Eloquent، hich Query، hich data-shaping tu Blade.
Tarikh e Jalali va format e adad tu laye ye namayesh (Resource/helper) anjam mishe، na tu Blade.

## RTL va Farsi

- **Faghat property haye logical**: `ms-` `me-` `ps-` `pe-`. **Hargez** `ml-`/`mr-`/`pl-`/`pr-`.
- Mobile-first. **Hich breakpoint e `max-*`.** Test ru 375×667. Target e lams ≥ 44px.
- Adad ha ba class e `.num` (`tabular-nums`) — vagarna tu jadval e gheymat e zende har tick jump mizane.
- **Matn e UI Farsi ast** (comment e code Finglish — `CLAUDE.md` e root).
- Font **local** (`resources/fonts/`). **Hich font e CDN** — ru host e ma ghabel e etminan nist.

## Container

```blade
<div class="container-site">   {{-- hich `mx-auto max-w-… px-…` e tekrari --}}
```

## Icon

**Faghat Tabler.**
```blade
<x-tabler-chart-line class="size-5" />
<x-dynamic-component :component="'tabler-'.$icon" />   {{-- dynamic --}}
```

## Component ha

Anonymous Blade component، namespace e `dash` baraye panel:
```blade
<x-dash::stat-card :label="__('Provider haye fa\'al')" :value="$count" featured />
<x-dash::pill tone="degraded">نیازمند بررسی</x-dash::pill>
```

## Ba'd az har taghir — ejbari

1. `mcp__playwright__browser_navigate` → URL
2. `mcp__playwright__browser_snapshot` (a11y tree — aval in، token-friendly)
3. `mcp__playwright__browser_take_screenshot` (faghat baraye moghayese ye basari)
4. Gozaresh: `✅ Verified` ya `⚠️ Issues: …`

Moghayese ba `images/dashboard-prefer.webp` — makhsusan radius، faseleh، vazn e font.

## Checklist

- [ ] Hich hex / `style=""` / `<style>`
- [ ] `brand-*` va `up`/`down` ghati nashodan
- [ ] Faghat `ms-/me-/ps-/pe-`
- [ ] Hich Eloquent/Query tu Blade
- [ ] Adad ha `.num`
- [ ] Alpine ba `x-cloak`، hich fetch e Alpine-only
- [ ] `wire:navigate` ru link haye dakheli
- [ ] Icon faghat Tabler
- [ ] Ru 375px test shod
- [ ] Playwright snapshot gerefte shod
