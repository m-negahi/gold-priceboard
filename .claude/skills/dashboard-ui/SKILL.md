---
name: dashboard-ui
description: Sakhtan e safhe haye panel e admin ba theme e PriceBoard — token haye Tailwind v4, component ha (shell, stat card, hachure, pill, gauge), ghanun e brand vs up/down, va checklist e RTL. Invoke kon ghabl az sakhtan ya taghir e har safhe ye /dashboard.
---

# Dashboard UI — theme va component ha

Marja' e basari: **`images/dashboard-prefer.webp`** (Donezo). Marja' e dovom baraye chegali ye jadval: `images/dashboard.png`.
Jozyat e token ha: `docs/dashboard-design.md`.

## 🔴 Ghanun e aval: brand ≠ up/down

Tu bazaar e mali **sabz = so'ud، ghermez = nozul**. Rang e brand e ma ham sabz ast. Agar hamun token ro baraye har do estefade koni، tu board kasi nemifahme sabz «brand» ast ya «gheymat bala raft».

| Hadaf | Token | Estefade |
|---|---|---|
| Brand (chrome) | `--color-brand-700/800` | sidebar e fa'al، dokme ye asli، card e featured |
| Gheymat bala | `--color-up` | faghat adad/arrow/flash e gheymat |
| Gheymat paeen | `--color-down` | faghat adad/arrow/flash e gheymat |
| Tala / noghre / mes | `--color-metal-gold/silver/copper` | faghat icon va chip e symbol |

**Hargez `brand-*` ro baraye neshun dadan e taghir e gheymat estefade nakon.**

## Zaban e basari

| Element | Ghanun |
|---|---|
| Page | bg e khakestari ye roshan؛ kol e app dakhel e yek **shell e gerd** (`@utility shell-app`، radius ~32px) — "floating panel" |
| Sidebar | shafaf. group ha ba label e riz e UPPERCASE (`MENU`، `GENERAL`). fa'al: **navar e sabz e samt** + matn e bold. gheyr e fa'al: icon e outline + `text-muted` |
| Topbar | **card e gerd e jodagane**: search e pill ba badge e shortcut، dokme haye gerd e icon، avatar + nam |
| Stat card | avali **featured** (gradient e sabz، matn e sefid)، baghi sefid. har kodum: dokme ye gerd e `↗` bala-rast، adad e bozorg، pill e riz + matn e moghayese |
| Chart e bar | bar haye **capsule**. fa'al = brand، gheyr e fa'al = **hachure ye mورب** (`@utility hachure`) — emza ye in theme |
| Gauge | nim-donut، darsad e bozorg vasat، legend ba dot |
| Card e tire | sabz e tire + **texture e swirl** + dokme ye pill |
| Pill e status | `ok`=sabz e soft، `degraded`=zard e soft، `down`=ghermez e soft |
| Kolli | radius e bala hame ja، whitespace e ziad، border e narm، saye ye kheyli nazok |

## Token ha — faghat `@theme`

```blade
{{-- ❌ HARGEZ --}}
<div style="background:#1f7a4d">
<div class="bg-[#1f7a4d]">

{{-- ✅ --}}
<div class="bg-brand-700">
```
Hich hex، hich `style=""`، hich `<style>` tu Blade. Har rang/radius/shadow bayad token e `@theme` tu `resources/css/app.css` bashe.
Har class string ke **≥ ۳ bar** tekrar she → `@utility` mishe.

## Component ha

Anonymous Blade component ha tu `resources/views/dashboard/components/` ba namespace e `dash`:
```blade
<x-dash::stat-card :label="'Provider haye fa'al'" :value="$count" featured />
<x-dash::pill tone="degraded">نیازمند بررسی</x-dash::pill>
<x-dash::health-badge :state="$provider['health_state']" />
```

## Alpine vs Livewire

| Kar | Che chizi |
|---|---|
| toggle e menu/dropdown/modal/tab | **Alpine** (~200× sabok tar) |
| sidebar e collapse، dark toggle | **Alpine** + `localStorage` |
| har chi ke ba server sync mishe | **Livewire** |
| jadval e filter/paginate، form e validation-dar | **Livewire** |

- **Hargez Alpine va Livewire ru yek interaction.**
- Alpine hamishe ba `x-cloak`.
- **Hich fetch/AJAX e Alpine-only** — har chi server mikhahe، Livewire.
- `wire:navigate` ru har link e dakheli. Yadet bashe state e Alpine ba navigate reset mishe → chiz haye paydar tu `localStorage`.

## RTL va Farsi

- **Faghat property haye logical**: `ms-`، `me-`، `ps-`، `pe-`. Hargez `ml-`/`mr-`/`pl-`/`pr-`.
- Mobile-first، **bedun e breakpoint e `max-*`**. Test ru 375×667. Target e lams ≥ 44px.
- Adad ha ba class e `.num` (`font-variant-numeric: tabular-nums`) ta tu jadval jump nazanan.
- Matn e UI **Farsi** ast (comment e code Finglish — `CLAUDE.md` e root).
- Tarikh e Jalali az laye ye namayesh miad (Resource/helper)، na tu Blade mohasebe she.

## Icon

**Faghat Tabler.** `<x-tabler-chart-line />` ya baraye dynamic `<x-dynamic-component :component="'tabler-'.$icon" />`. Hich echo/interpolation e mostaghim.

## Ba'd az har taghir — ejbari

```
1. mcp__playwright__browser_navigate → http://localhost:8300/dashboard/<safhe>
2. mcp__playwright__browser_snapshot        (a11y tree — aval in، token-friendly)
3. mcp__playwright__browser_take_screenshot (faghat agar moghayese ye basari mikhay)
4. Gozaresh: "✅ Verified" ya "⚠️ Issues: …"
```
Moghayese kon ba `images/dashboard-prefer.webp` — makhsusan radius، faseleh، va vazn e font.

## Checklist

- [ ] Hich hex / `style=""` / `<style>`
- [ ] `brand-*` baraye chrome، `up`/`down` baraye gheymat — ghati nashodan
- [ ] Faghat `ms-/me-/ps-/pe-`
- [ ] Icon faghat Tabler
- [ ] Adad ha `.num`
- [ ] Alpine ba `x-cloak`، hich fetch e Alpine-only
- [ ] `wire:navigate` ru link haye dakheli
- [ ] Playwright snapshot gerefte shod
- [ ] Ru 375px test shod
