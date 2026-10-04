# Dashboard Design — PriceBoard

Marja' e basari: **`images/dashboard-prefer.webp`** (Donezo) — sakhtar، radius، faseleh va zaban e shekl az inja miad.
Marja' e dovom: `images/dashboard.png` (Shopeers) — faghat baraye chegali ye jadval va stat card.

Ghanun haye ejra: `resources/views/CLAUDE.md` · skill: `dashboard-ui`.

---

## 1. 🔴 Brand ≠ up/down

Tu bazaar e mali **sabz = so'ud، ghermez = nozul**. Rang e brand e ma ham sabz ast. Agar hamun token ro baraye har do bezani، tu board kasi nemifahme sabz «brand» ast ya «gheymat bala raft».

| Hadaf | Token | Faghat inja |
|---|---|---|
| Brand (chrome) | `brand-600/700/800` | sidebar e fa'al، dokme ye asli، card e featured، logo |
| Gheymat bala raft | `up` | adad / arrow / flash e gheymat |
| Gheymat paeen umad | `down` | adad / arrow / flash e gheymat |
| Tala / noghre / mes | `metal-gold` / `metal-silver` / `metal-copper` | icon va chip e symbol |

Tafkik e amali: **brand** sabz e tire ye kam-eshba' ast ke ru sath haye bozorg mishine؛ **up** emerald e roshan va por-eshba' ast ke faghat ru matn e riz e adad mishine. Kenar e ham gij nemikonan.

---

## 2. Token ha — `resources/css/app.css`

```css
@theme {
  /* ── Brand: sabz e tire. FAGHAT chrome. ───────────────────── */
  --color-brand-50:  #EDF7F1;
  --color-brand-100: #D3EDDF;
  --color-brand-200: #A8DBBE;
  --color-brand-300: #74C396;
  --color-brand-400: #45A873;
  --color-brand-500: #2A8D5A;
  --color-brand-600: #1E7449;   /* dokme ye asli */
  --color-brand-700: #176039;   /* sidebar e fa'al، card e featured */
  --color-brand-800: #114A2C;
  --color-brand-900: #0C3620;
  --color-brand-950: #072415;

  /* ── Ink: khakestari ye GARM (na slate e sard) ─────────────── */
  --color-ink-50:  #F7F7F6;
  --color-ink-100: #EFEFED;
  --color-ink-200: #DEDEDA;
  --color-ink-300: #C4C4BE;
  --color-ink-400: #9B9B94;
  --color-ink-500: #76766F;
  --color-ink-600: #5A5A54;
  --color-ink-700: #45453F;
  --color-ink-800: #32322D;
  --color-ink-900: #22221E;
  --color-ink-950: #151513;

  /* ── Sath ha ───────────────────────────────────────────────── */
  --color-canvas:    #E9EAE7;   /* posht e shell */
  --color-surface:   #FFFFFF;   /* card */
  --color-surface-2: #F7F8F6;   /* dakhel e shell، sidebar */
  --color-line:      #E6E7E3;
  --color-body:      var(--color-ink-700);
  --color-heading:   var(--color-ink-900);
  --color-muted:     var(--color-ink-500);

  /* ── Semantic e gheymat — HARGEZ brand-* ───────────────────── */
  --color-up:        #00B368;
  --color-up-soft:   #E3F8ED;
  --color-down:      #E5484D;
  --color-down-soft: #FDEAEA;
  --color-flat:      var(--color-ink-400);

  /* ── Hālat (salamati ye provider) ──────────────────────────── */
  --color-ok:            #00B368;
  --color-ok-soft:       #E3F8ED;
  --color-degraded:      #E8A700;
  --color-degraded-soft: #FDF3DC;
  --color-down-state:    #E5484D;
  --color-down-state-soft: #FDEAEA;

  /* ── Felezat (faghat icon/chip) ────────────────────────────── */
  --color-metal-gold:   #C8A031;
  --color-metal-silver: #8E95A0;
  --color-metal-copper: #B4683A;

  /* ── Radius — in theme kheyli gerd ast ─────────────────────── */
  --radius-xs:  0.375rem;
  --radius-sm:  0.5rem;
  --radius-md:  0.75rem;
  --radius-lg:  1rem;
  --radius-xl:  1.25rem;
  --radius-2xl: 1.5rem;
  --radius-3xl: 2rem;     /* shell e app */

  /* ── Saye ha: narm، taghriban na-mahsus ────────────────────── */
  --shadow-xs: 0 1px 2px 0 rgb(21 21 19 / 0.04);
  --shadow-sm: 0 1px 3px 0 rgb(21 21 19 / 0.05), 0 1px 2px -1px rgb(21 21 19 / 0.04);
  --shadow-md: 0 4px 10px -2px rgb(21 21 19 / 0.06), 0 2px 4px -2px rgb(21 21 19 / 0.04);
  --shadow-lg: 0 12px 28px -6px rgb(21 21 19 / 0.08);

  /* ── Font: LOCAL. hich CDN. ────────────────────────────────── */
  --font-sans: 'Vazirmatn', ui-sans-serif, system-ui, sans-serif;

  --ease: cubic-bezier(0.4, 0, 0.2, 1);
}
```

### Gradient va texture
```css
@utility bg-brand-grad {
  background-image: linear-gradient(135deg, var(--color-brand-600), var(--color-brand-800));
}

/* Hachure — emza ye in theme. Hālat e "gheyr e fa'al / pending". */
@utility hachure {
  background-image: repeating-linear-gradient(
    45deg,
    var(--color-ink-200) 0 2px,
    transparent 2px 7px
  );
}
```

---

## 3. Font

**Vazirmatn** (SIL OFL، Farsi ye a'la، raghamha ye tabular dare).
**Local tu `resources/fonts/`** — hich CDN (ru host e Iran ghabel e etminan nist).

```css
@font-face {
  font-family: 'Vazirmatn';
  src: url('../fonts/Vazirmatn-Regular.woff2') format('woff2');
  font-weight: 400; font-display: swap; font-style: normal;
}
/* … 500 / 600 / 700 / 800 / 900 */
```
Vazn ha ye lazem: 400, 500, 600, 700, 800, 900.

```css
body { font-feature-settings: 'ss01'; line-height: 1.7; }      /* shekl e durost e ی */
.num { font-variant-numeric: tabular-nums; }                   /* EJBARI ru har adad e gheymat */
```
`.num` ejbari-e: bedun esh har tick e 15 saniye-i arz e soton ro taghir mide va kol e jadval mipare.

---

## 4. Sakhtar e safhe

```
┌─ body: bg-canvas ─────────────────────────────────────────────┐
│  ┌─ .shell-app : rounded-3xl, bg-surface-2, shadow-lg ──────┐  │
│  │ ┌─sidebar──┐  ┌─ topbar: card e GERD e jodagane ──────┐  │  │
│  │ │ logo     │  │ search pill    │  icon ha  │ avatar   │  │  │
│  │ │          │  └──────────────────────────────────────┘  │  │
│  │ │ MENU     │  ┌─ content: rounded-2xl, bg-surface ────┐  │  │
│  │ │ ▎Dashb.  │  │  h1 + action ha                      │  │  │
│  │ │  Provide │  │  ┌────┬────┬────┬────┐  stat card ha │  │  │
│  │ │  Spread  │  │  │feat│    │    │    │               │  │  │
│  │ │          │  │  └────┴────┴────┴────┘               │  │  │
│  │ │ GENERAL  │  │  ┌─────────────┬──────┬───────────┐  │  │  │
│  │ │  Setting │  │  │ chart       │ …    │ …         │  │  │  │
│  │ │          │  │  └─────────────┴──────┴───────────┘  │  │  │
│  │ │ [promo]  │  └──────────────────────────────────────┘  │  │
│  │ └──────────┘                                            │  │
│  └─────────────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────────────┘
```

```css
@utility shell-app {
  margin-inline: auto;
  max-width: 100rem;
  border-radius: var(--radius-3xl);
  background: var(--color-surface-2);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
}
@utility card-p    { padding: 1.25rem; }
@utility card-p-lg { padding: 1.5rem 1.75rem; }
```

---

## 5. Component ha (`resources/views/dashboard/components/`، namespace `dash`)

| Component | Shekl |
|---|---|
| `layout` | shell + sidebar + topbar |
| `sidebar-item` | gheyr e fa'al: icon e outline + `text-muted`. Fa'al: **navar e `brand-700` e samt** (`before:` ba `ms-0`) + `text-heading font-bold` + bg e `surface` |
| `sidebar-group` | label e `text-[0.6875rem] uppercase tracking-wide text-ink-400` |
| `stat-card` | `rounded-2xl card-p`. Default sefid. `featured` → `bg-brand-grad text-white`. Dokme ye gerd e `↗` bala-rast (`size-8 rounded-full border`). Adad `text-3xl font-extrabold num`. Zir esh pill e riz + matn e `text-muted` |
| `pill` | `rounded-full px-2 py-0.5 text-xs font-semibold`. tone: `ok` / `degraded` / `down` → `*-soft` bg + matn e por-rang |
| `health-badge` | `pill` + dot e `size-1.5 rounded-full` |
| `price-cell` | adad ba `.num` + arrow. `up`/`down`/`flat`. Agar `is_derived` → `*` e riz + tooltip |
| `bar-chart` | bar haye **capsule** (`rounded-full`). Fa'al `bg-brand-500`، gheyr e fa'al `hachure` |
| `gauge` | nim-donut e SVG، darsad e bozorg vasat، legend ba dot |
| `promo-card` | `bg-brand-grad` + texture + dokme ye pill e sefid |
| `empty-state` | icon + matn + CTA |

### `is_derived` — ejbari

Ma adad hayi ro ba label e «نرخ خرید/فروش فلان مغازه» neshun midim ke dar vaghe' **arithmetic e ma ru yek mid price** ast. Ru site-i ke kol e arzesh esh e'temad ast، hamin chizi ast ke shekayat misaze.

→ Har gheymat e **mochtag** (az spread sakhte shode) bayad marker dashte bashe:
```blade
<x-dash::price-cell :price="$row['buy']" :derived="! $row['has_native_sides']" />
```
→ `*` e riz kenar e adad + footnote e «نرخ خرید/فروش محاسبه‌شده». Jofte vaghei (`xau-melted-g-irr` e tgju) **bedun e** marker.
Hamin flag tu payload e API ham miad (`isDerived`). Retrofit kardanesh ba'd az dashtan e customer e billing-dar kheyli gerun tar ast.

---

## 6. Typography

| Chi | Class |
|---|---|
| H1 e safhe | `text-2xl md:text-3xl font-extrabold text-heading` |
| Zir-onvan | `text-sm text-muted` |
| Onvan e card | `text-base font-bold text-heading` |
| Adad e stat | `text-3xl font-extrabold num` |
| Label e jadval | `text-xs font-semibold text-muted` |
| Adad e gheymat | `text-sm font-semibold num` |

Line-height e body 1.7؛ onvan ha 1.25.

---

## 7. RTL

- **Faghat `ms-` `me-` `ps-` `pe-`.** Hargez `ml-`/`mr-`/`pl-`/`pr-`.
- Navar e sidebar e fa'al samt e **rast** (`border-s-2`) — ba logical property khod-be-khod durost mishe.
- Arrow e `↗` tu RTL bayad be samt e **bala-chap** bashe → `rtl:-scale-x-100`.
- Mobile-first. **Hich `max-*`.** Test e 375×667.

---

## 8. Dark mode

Phase 5 (board e TV ye maghaze aghlab dark ast). Alan **nemizanim** vali token ha amade-and: `@media (prefers-color-scheme: dark)` ba `:root:not([data-theme="light"])` + `:root[data-theme="dark"]`.
Baraye board e TV `data-theme="dark"` e ejbari mizanim (na tabe' e system).

---

## 9. Checklist

- [ ] Hich hex / `style=""` / `<style>` tu Blade
- [ ] `brand-*` va `up`/`down` ghati nashodan
- [ ] Har adad e gheymat `.num` dare
- [ ] Gheymat e mochtag marker e `is_derived` dare
- [ ] Faghat `ms-/me-/ps-/pe-`
- [ ] Font local (hich CDN)
- [ ] Ru 375px test shod
- [ ] Playwright snapshot + moghayese ba `images/dashboard-prefer.webp`
