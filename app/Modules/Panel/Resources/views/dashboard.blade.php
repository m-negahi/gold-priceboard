<x-layouts.panel title="میزکار" current="dashboard" :breadcrumbs="['پنل', 'میزکار']">
    {{--
        Mizkar v2. Snapshot-e avalie inline mishe (avalin paint bedoone fetch); ba'd az un Alpine
        har 5 sanie hamun shekl ro az `panel.api.dashboard` migire va faghat cell-hayi ke avaz
        shodan ro dast mizane. Har tarikh-e jalali az server miad; client faghat "x sanie pish" va
        shomarande ha ro mishmore. Sparkline-e 24 sa'ate tanbal az `/panel/api/series/{code}` miad
        va age un endpoint nabashe sotoonesh bi-seda penhan mimune.
        Bakhsh-e mali amdan nist (Billing, M12) — naghshe-ye mantaghe ha tu rahnama-ye safhe-ast.
    --}}
    <div x-data="dashboard({ url: '{{ route('panel.api.dashboard') }}', tickUrl: '{{ route('panel.api.monitor.tick') }}', seriesUrl: '/panel/api/series', initial: @js($snapshot), interval: 5000 })" class="space-y-5">

        {{-- ── Sar-e safhe ──────────────────────────────────────────────────────── --}}
        <header class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
            <div>
                <h1 class="text-2xl font-bold leading-tight">میزکار</h1>
                <p class="mt-1 text-sm text-muted">وضعیت لحظه‌ای موتور قیمت: قیمت‌ها، منابع، بازارها و آنچه به توجه نیاز دارد.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="inline-flex items-center gap-2 rounded-chip border border-line bg-surface py-1 pr-2.5 pl-3 font-medium"
                      :class="{ 'text-ok': live.tone === 'ok', 'text-danger': live.tone === 'danger', 'text-muted': live.tone === 'flat' }"
                      data-live role="status" aria-live="polite">
                    <span class="relative flex size-2.5">
                        <span x-show="live.tone === 'ok' && !refreshing" class="absolute inline-flex size-full animate-ping rounded-full bg-ok opacity-60"></span>
                        <span class="relative inline-flex size-2.5 rounded-full"
                              :class="{ 'bg-ok': live.tone === 'ok', 'bg-danger': live.tone === 'danger', 'bg-muted-3': live.tone === 'flat' }"></span>
                    </span>
                    <span x-text="live.label">زنده</span>
                </span>

                {{-- Hoshdar: gheymat-e nahayi-ye chand instrument ro operator ba dast zade (ADR-0034). --}}
                <a href="{{ route('panel.monitor') }}" x-show="k?.manual_count > 0" x-cloak data-manual-warning
                   class="inline-flex items-center gap-1.5 rounded-chip border border-warn/40 bg-warn-soft px-3 py-1 font-medium text-warn hover:bg-warn-soft/80">
                    <x-icon name="alert" class="size-4" />
                    قیمت دستی فعال است
                    <span class="num rounded-chip bg-surface px-1.5 text-[11px]" x-text="n(k?.manual_count)"></span>
                </a>

                <span class="inline-flex items-center gap-2 rounded-chip border border-line bg-surface px-3 py-1 text-muted">
                    <x-icon name="clock" class="size-4 text-muted-2" />
                    <span class="hidden sm:inline">به‌روزرسانی</span>
                    <span class="num text-ink" data-updated-at x-text="snapshot?.generated_at_jalali ?? '—'">{{ $snapshot['generated_at_jalali'] }}</span>
                </span>

                <button type="button" @click="refresh()" :disabled="refreshing"
                        class="grid size-9 place-items-center rounded-lg border border-line bg-surface text-muted transition-colors hover:bg-surface-3 hover:text-ink disabled:cursor-progress"
                        aria-label="به‌روزرسانی دستی">
                    <x-icon name="refresh" class="size-4" ::class="refreshing && 'animate-spin'" />
                </button>

                <x-panel.help key="dashboard" title="راهنمای میزکار">
                    @include('panel::dashboard-help')
                </x-panel.help>
            </div>
        </header>

        {{-- Navar-e barik-e progress: faghat vaghti fetch dar jaryan-e. --}}
        <div class="-mt-2 h-0.5 overflow-hidden rounded-full bg-line-2" aria-hidden="true">
            <div class="h-full w-full origin-right bg-brand transition-opacity duration-(--bp-dur-base)" :class="refreshing ? 'animate-pulse opacity-100' : 'opacity-0'"></div>
        </div>

        {{-- ── Navar-e amar: yek radif-e feshorde, har amar yek link be safhe-ash ─── --}}
        <section aria-label="آمار لحظه‌ای" data-statbar class="grid grid-cols-2 gap-2 sm:grid-cols-4 2xl:grid-cols-8">

            <a href="/panel/instruments" data-kpi="instruments" class="group flex items-center gap-2.5 rounded-card border border-line bg-surface px-3 py-2 transition-colors hover:border-line-3 hover:bg-surface-2">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-soft text-brand"><x-icon name="coins" class="size-4" /></span>
                <span class="min-w-0">
                    <span class="num block text-base font-semibold leading-tight" x-text="n(k?.instruments_active)">—</span>
                    <span class="block truncate text-[11px] text-muted">ابزار فعال</span>
                </span>
            </a>

            <a href="/panel/instruments" data-kpi="quoted" class="group flex items-center gap-2.5 rounded-card border border-line bg-surface px-3 py-2 transition-colors hover:border-line-3 hover:bg-surface-2">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg" :class="k && k.instruments_quoted < k.instruments_active ? 'bg-warn-soft text-warn' : 'bg-ok-soft text-ok'"><x-icon name="circle-check" class="size-4" /></span>
                <span class="min-w-0">
                    <span class="num block text-base font-semibold leading-tight"><span x-text="n(k?.instruments_quoted)">—</span><span class="text-xs font-normal text-muted-2"> / <span x-text="n(k?.instruments_active)"></span></span></span>
                    <span class="block truncate text-[11px] text-muted">قیمت‌دار</span>
                </span>
            </a>

            <div data-kpi="stale" class="flex items-center gap-2.5 rounded-card border border-line bg-surface px-3 py-2" :class="k?.stale_count > 0 && 'border-stale-strong'">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg" :class="k?.stale_count > 0 ? 'bg-stale-soft text-stale' : 'bg-surface-3 text-muted'"><x-icon name="timer" class="size-4" /></span>
                <span class="min-w-0">
                    <span class="num block text-base font-semibold leading-tight" :class="k?.stale_count > 0 && 'text-stale'" x-text="n(k?.stale_count)">—</span>
                    <span class="block truncate text-[11px] text-muted" x-text="k?.stale_count > 0 ? 'کهنه — خوراک نمی‌رسد' : 'کهنه'">کهنه</span>
                </span>
            </div>

            <a href="/panel/sources" data-kpi="sources" class="group flex items-center gap-2.5 rounded-card border border-line bg-surface px-3 py-2 transition-colors hover:border-line-3 hover:bg-surface-2">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg"
                      :class="{ 'bg-ok-soft text-ok': sourcesTone === 'ok', 'bg-warn-soft text-warn': sourcesTone === 'warn', 'bg-danger-soft text-danger': sourcesTone === 'danger', 'bg-surface-3 text-muted': sourcesTone === 'flat' }"><x-icon name="feed" class="size-4" /></span>
                <span class="min-w-0">
                    <span class="num block text-base font-semibold leading-tight"><span x-text="n(k?.sources_healthy)">—</span><span class="text-xs font-normal text-muted-2"> / <span x-text="n(k?.sources_total)"></span></span></span>
                    <span class="block truncate text-[11px] text-muted">منبع سالم</span>
                </span>
            </a>

            <a href="/panel/sources" data-kpi="cooldown" class="group flex items-center gap-2.5 rounded-card border border-line bg-surface px-3 py-2 transition-colors hover:border-line-3 hover:bg-surface-2" :class="k?.sources_cooldown > 0 && 'border-danger/40'">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg" :class="k?.sources_cooldown > 0 ? 'bg-danger-soft text-danger' : 'bg-surface-3 text-muted'"><x-icon name="ban" class="size-4" /></span>
                <span class="min-w-0">
                    <span class="num block text-base font-semibold leading-tight" :class="k?.sources_cooldown > 0 && 'text-danger'" x-text="n(k?.sources_cooldown)">—</span>
                    <span class="block truncate text-[11px] text-muted" x-text="k?.sources_failing > 0 ? `وقفهٔ خطا · ${n(k.sources_failing)} در حال خطا` : 'وقفهٔ خطا'">وقفهٔ خطا</span>
                </span>
            </a>

            <a href="/panel/reports/providers" data-kpi="last-tick" class="group flex items-center gap-2.5 rounded-card border border-line bg-surface px-3 py-2 transition-colors hover:border-line-3 hover:bg-surface-2" :title="k?.last_tick?.at_jalali">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg" :class="lastTickTone === 'ok' ? 'bg-ok-soft text-ok' : lastTickTone === 'warn' ? 'bg-warn-soft text-warn' : 'bg-surface-3 text-muted'"><x-icon name="pulse" class="size-4" /></span>
                <span class="min-w-0">
                    <span class="num block text-sm font-semibold leading-tight" x-text="k?.last_tick ? ago(k.last_tick.at) : '—'">—</span>
                    <span class="block truncate text-[11px] text-muted" x-text="k?.last_tick ? `آخرین دریافت · ${k.last_tick.source_name}` : 'آخرین دریافت'">آخرین دریافت</span>
                </span>
            </a>

            <div data-kpi="markets" class="flex items-center gap-2.5 rounded-card border border-line bg-surface px-3 py-2">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg" :class="k?.markets.open > 0 ? 'bg-ok-soft text-ok' : 'bg-surface-3 text-muted'">
                    <x-icon name="sun" class="size-4" x-show="k?.markets.open > 0" />
                    <x-icon name="moon" class="size-4" x-show="!(k?.markets.open > 0)" />
                </span>
                <span class="min-w-0">
                    <span class="num block text-base font-semibold leading-tight"><span x-text="n(k?.markets.open)">—</span><span class="text-xs font-normal text-muted-2"> / <span x-text="n(k?.markets.total)"></span></span></span>
                    <span class="block truncate text-[11px] text-muted">بازار باز</span>
                </span>
            </div>

            <div data-kpi="movers" class="flex items-center gap-2.5 rounded-card border border-line bg-surface px-3 py-2">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-surface-3 text-ink-3"><x-icon name="trend-up" class="size-4" /></span>
                <span class="min-w-0">
                    <span class="num flex items-baseline gap-2 text-base font-semibold leading-tight">
                        <span class="inline-flex items-center gap-0.5 text-up"><x-icon name="trend-up" class="size-3.5" /><span x-text="n(k?.movers.up)">—</span></span>
                        <span class="inline-flex items-center gap-0.5 text-down"><x-icon name="trend-down" class="size-3.5" /><span x-text="n(k?.movers.down)">—</span></span>
                    </span>
                    <span class="block truncate text-[11px] text-muted">روند امروز · <span class="num" x-text="n(k?.movers.flat)"></span> ثابت</span>
                </span>
            </div>
        </section>

        {{-- ── Jadval-e gheymat: ghahreman-e safhe, feshorde ─────────────────────── --}}
        <section class="overflow-hidden rounded-card border border-line bg-surface" aria-labelledby="prices-title" data-prices>
            <header class="flex flex-wrap items-center gap-3 border-b border-line px-4 py-2.5">
                <h2 id="prices-title" class="text-sm font-semibold">قیمت‌های لحظه‌ای</h2>
                <span class="num rounded-chip bg-surface-3 px-2 py-0.5 text-[11px] text-muted" x-text="`${n(rows.length)} ابزار`"></span>
                <span x-show="spark.available === true" x-cloak class="hidden items-center gap-1 text-[11px] text-muted-2 lg:inline-flex"><x-icon name="spark" class="size-3.5" />نمودار ۲۴ ساعته از تاریخچه</span>

                <div class="mr-auto flex items-center gap-1 rounded-chip border border-line bg-surface-2 p-0.5 text-xs" role="group" aria-label="واحد نمایش">
                    <button type="button" @click="setUnit('rial')" :aria-pressed="unit === 'rial'"
                            class="rounded-chip px-2.5 py-0.5 font-medium transition-colors"
                            :class="unit === 'rial' ? 'bg-surface text-ink shadow-(--bp-elev-sm)' : 'text-muted hover:text-ink'">ریال</button>
                    <button type="button" @click="setUnit('toman')" :aria-pressed="unit === 'toman'"
                            class="rounded-chip px-2.5 py-0.5 font-medium transition-colors"
                            :class="unit === 'toman' ? 'bg-surface text-ink shadow-(--bp-elev-sm)' : 'text-muted hover:text-ink'">تومان</button>
                </div>
            </header>

            <div class="max-h-[32rem] overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-surface-2 text-right text-[11px] text-muted shadow-[inset_0_-1px_0_var(--color-line)]">
                        <tr>
                            <th class="px-3 py-2 font-medium">ابزار</th>
                            <th class="hidden px-2 py-2 text-left font-medium xl:table-cell">خرید</th>
                            <th class="hidden px-2 py-2 text-left font-medium xl:table-cell">فروش</th>
                            <th class="px-2 py-2 text-left font-medium">قیمت <span x-text="unit === 'toman' ? '(تومان)' : '(ریال)'"></span></th>
                            <th class="hidden px-2 py-2 text-left font-medium sm:table-cell">تغییر</th>
                            <th x-show="spark.available === true" x-cloak class="hidden px-2 py-2 text-center font-medium lg:table-cell">۲۴ ساعت</th>
                            <th class="hidden px-2 py-2 font-medium md:table-cell">منبع</th>
                            <th class="hidden whitespace-nowrap px-3 py-2 font-medium sm:table-cell">زمان</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-line-2">
                        {{-- Skeleton: faghat vaghti hanuz hich snapshot-i nadarim. --}}
                        <template x-if="snapshot === null">
                            <template x-for="i in 6" :key="i">
                                <tr class="animate-pulse" aria-hidden="true">
                                    <td class="px-3 py-2"><div class="h-3 w-32 rounded bg-surface-3"></div><div class="mt-1.5 h-2 w-16 rounded bg-line-2"></div></td>
                                    <td class="hidden px-2 py-2 xl:table-cell"><div class="mr-auto h-3 w-20 rounded bg-surface-3"></div></td>
                                    <td class="hidden px-2 py-2 xl:table-cell"><div class="mr-auto h-3 w-20 rounded bg-surface-3"></div></td>
                                    <td class="px-2 py-2"><div class="mr-auto h-3 w-24 rounded bg-surface-3"></div></td>
                                    <td class="px-2 py-2"><div class="mr-auto h-3 w-16 rounded bg-surface-3"></div></td>
                                    <td class="hidden px-2 py-2 md:table-cell"><div class="h-3 w-12 rounded bg-surface-3"></div></td>
                                    <td class="px-3 py-2"><div class="h-3 w-20 rounded bg-surface-3"></div></td>
                                </tr>
                            </template>
                        </template>

                        <template x-for="row in rows" :key="row.code">
                            <tr class="relative transition-colors duration-(--bp-dur-slow)" :class="rowClass(row)" :data-code="row.code" :data-stale="row.is_stale ? '1' : '0'">
                                <td class="relative py-1.5 pr-3 pl-2">
                                    {{-- Navar-e felez: hoviyat-e instrument, rooye labe-ye rast-e radif. --}}
                                    <span class="absolute inset-y-1.5 right-0 w-0.75 rounded-l-full" :class="accent(row)" aria-hidden="true"></span>
                                    <div class="flex items-center gap-2.5">
                                        <span class="grid size-7 shrink-0 place-items-center rounded-md text-[10px] font-bold" :class="disc(row)" x-text="symbol(row)"></span>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="max-w-20 truncate text-[13px] font-medium sm:max-w-none" x-text="row.name"></span>
                                                <template x-if="row.is_stale">
                                                    <span class="inline-flex items-center gap-0.5 whitespace-nowrap rounded-chip bg-stale-strong px-1.5 py-px text-[10px] font-medium text-stale" data-badge="stale">
                                                        <x-icon name="timer" class="size-3" />کهنه
                                                    </span>
                                                </template>
                                                <template x-if="!row.market_open">
                                                    <span class="hidden items-center gap-0.5 whitespace-nowrap rounded-chip bg-surface-3 px-1.5 py-px text-[10px] font-medium text-muted sm:inline-flex" data-badge="closed" :title="row.market_label">
                                                        <x-icon name="moon" class="size-3" />بازار بسته
                                                    </span>
                                                </template>
                                            </div>
                                            <div class="flex max-w-20 items-center gap-1 truncate text-[10px] leading-tight text-muted-2 sm:max-w-none">
                                                <span class="ltr" x-text="row.code"></span>
                                                <span aria-hidden="true">·</span>
                                                <span x-text="row.unit"></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="num hidden px-2 py-1.5 text-left text-xs text-muted xl:table-cell" data-cell="buy" x-text="money(row.buy)"></td>
                                <td class="num hidden px-2 py-1.5 text-left text-xs text-muted xl:table-cell" data-cell="sell" x-text="money(row.sell)"></td>
                                <td class="px-2 py-1.5 text-left" data-cell="mid">
                                    <span class="num block text-sm font-semibold sm:text-[15px]" :class="row.is_stale && 'text-stale'" x-text="money(row.mid)"></span>
                                    {{-- Zir-e sm faghat ~290px darim (rail-e sidebar 68px migire): taghir va zaman zir-e gheymat mishinan. --}}
                                    <span class="mt-0.5 flex items-center justify-end gap-1.5 sm:hidden" :class="directionClass(row)">
                                        <span class="num inline-flex items-center gap-0.5 rounded-chip px-1 py-px text-[10px] font-medium" :class="pctChip(row)" x-text="pct(row.change_pct)"></span>
                                        <span class="num text-[10px] text-muted" x-text="ago(row.as_of)" :title="row.as_of_jalali"></span>
                                    </span>
                                </td>
                                <td class="hidden px-2 py-1.5 text-left sm:table-cell">
                                    <div class="flex items-center justify-end gap-1.5" :class="directionClass(row)">
                                        <span class="num hidden text-[11px] 2xl:inline" x-text="delta(row.change_abs)"></span>
                                        <span class="num inline-flex items-center gap-0.5 rounded-chip px-1.5 py-px text-[11px] font-medium" :class="pctChip(row)">
                                            <x-icon name="trend-up" class="size-3" x-show="row.direction === 'up'" />
                                            <x-icon name="trend-down" class="size-3" x-show="row.direction === 'down'" />
                                            <x-icon name="minus" class="size-3" x-show="row.direction === 'flat'" />
                                            <span x-text="pct(row.change_pct)"></span>
                                        </span>
                                    </div>
                                </td>
                                {{-- Sparkline: SVG-e inline, bedoone ketabkhane; faghat vaghti endpoint-e series javab dade. --}}
                                <td x-show="spark.available === true" x-cloak class="hidden px-2 py-1 text-center lg:table-cell" data-cell="spark">
                                    <svg x-show="sparkHas(row.code)" viewBox="0 0 80 24" class="inline-block h-6 w-20" :class="sparkTone(row.code)" aria-hidden="true" preserveAspectRatio="none">
                                        <path :d="sparkPath(row.code)" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                                    </svg>
                                    {{-- Kamtar az do noghte tu 24 sa'at-e akhir: khat-i nist, vali sotoon khali ham nabayad "kharab" be nazar berese. --}}
                                    <span x-show="!sparkHas(row.code)" class="text-[10px] text-muted-3" title="در ۲۴ ساعت اخیر تاریخچه‌ای ثبت نشده">—</span>
                                </td>
                                <td class="hidden px-2 py-1.5 md:table-cell">
                                    <span class="inline-flex max-w-28 truncate rounded-chip border border-line bg-surface-2 px-1.5 py-px text-[11px] text-ink-3" x-text="row.source_name ?? '—'"></span>
                                </td>
                                <td class="hidden whitespace-nowrap px-3 py-1.5 text-[11px] text-muted sm:table-cell">
                                    <span class="num cursor-default underline decoration-dotted decoration-line-3 underline-offset-4" x-text="ago(row.as_of)" :title="row.as_of_jalali"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                {{-- Halat-e khali: nasb-e taze, hanuz hich tick-i. --}}
                <template x-if="empty">
                    <div class="flex flex-col items-center px-6 py-14 text-center" data-empty>
                        <span class="grid size-14 place-items-center rounded-2xl bg-brand-soft text-brand"><x-icon name="inbox" class="size-7" /></span>
                        <h3 class="mt-4 font-semibold">هنوز قیمتی ثبت نشده است</h3>
                        <p class="mt-1 max-w-sm text-sm text-muted">به محض اینکه یک منبع فعال پاسخ بدهد، اولین قیمت‌ها اینجا می‌نشینند. با «اجرای یک دور دریافت» می‌توانید همین حالا شروع کنید.</p>
                    </div>
                </template>
            </div>
        </section>

        {{-- ── Keyfiat-e dade: harekat ha + moghayese-ye manabe' ─────────────────── --}}
        <div class="grid min-w-0 items-start gap-4 lg:grid-cols-3">

            {{-- Bishtarin harekat ha --}}
            <section class="min-w-0 overflow-hidden rounded-card border border-line bg-surface" aria-labelledby="movers-title" data-movers>
                <header class="flex items-center gap-2 border-b border-line px-4 py-2.5">
                    <h2 id="movers-title" class="text-sm font-semibold">بیشترین حرکت‌ها</h2>
                    <span class="text-[11px] text-muted-2">نسبت به پایانی قبلی</span>
                </header>

                <template x-if="snapshot !== null && movers.up.length === 0 && movers.down.length === 0">
                    <p class="px-4 py-6 text-center text-sm text-muted">امروز هنوز حرکتی ثبت نشده است.</p>
                </template>

                <div class="divide-y divide-line" x-show="movers.up.length > 0 || movers.down.length > 0">
                    <ul class="divide-y divide-line-2" data-movers-up aria-label="رشدها" x-show="movers.up.length > 0">
                        <template x-for="row in movers.up" :key="row.code">
                            <li class="flex items-center gap-2 px-3 py-2" :data-code="row.code">
                                <span class="size-1.5 shrink-0 rounded-full bg-up-strong" aria-hidden="true"></span>
                                <div class="flex min-w-0 flex-1 items-center gap-2">
                                    <span class="truncate text-xs font-medium" x-text="row.name"></span>
                                    <template x-if="row.is_stale"><x-icon name="timer" class="size-3 shrink-0 text-stale" /></template>
                                    <span class="num mr-auto shrink-0 text-[11px] text-muted-2" x-text="money(row.mid)"></span>
                                </div>
                                <span class="num shrink-0 rounded-chip bg-up-soft px-1.5 py-px text-[11px] font-medium text-up" x-text="pct(row.change_pct)"></span>
                            </li>
                        </template>
                    </ul>
                    <ul class="divide-y divide-line-2" data-movers-down aria-label="افت‌ها" x-show="movers.down.length > 0">
                        <template x-for="row in movers.down" :key="row.code">
                            <li class="flex items-center gap-2 px-3 py-2" :data-code="row.code">
                                <span class="size-1.5 shrink-0 rounded-full bg-down-strong" aria-hidden="true"></span>
                                <div class="flex min-w-0 flex-1 items-center gap-2">
                                    <span class="truncate text-xs font-medium" x-text="row.name"></span>
                                    <template x-if="row.is_stale"><x-icon name="timer" class="size-3 shrink-0 text-stale" /></template>
                                    <span class="num mr-auto shrink-0 text-[11px] text-muted-2" x-text="money(row.mid)"></span>
                                </div>
                                <span class="num shrink-0 rounded-chip bg-down-soft px-1.5 py-px text-[11px] font-medium text-down" x-text="pct(row.change_pct)"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </section>

            {{-- Moghayese-ye manabe': kodum provider por-t-e --}}
            <section class="min-w-0 overflow-hidden rounded-card border border-line bg-surface lg:col-span-2" aria-labelledby="comparison-title" data-comparison>
                <header class="flex flex-wrap items-center gap-2 border-b border-line px-4 py-2.5">
                    <h2 id="comparison-title" class="text-sm font-semibold">مقایسهٔ منابع</h2>
                    <span class="text-[11px] text-muted-2">انحراف هر منبع از قیمت نهایی · آستانه <span class="num" x-text="pctPlain(comparison.threshold_pct)"></span></span>
                    <span class="num mr-auto rounded-chip px-2 py-0.5 text-[11px]" :class="offCount > 0 ? 'bg-danger-soft text-danger' : 'bg-ok-soft text-ok'" x-text="offCount > 0 ? `${n(offCount)} ابزار خارج از آستانه` : 'همه در آستانه'"></span>
                </header>

                <template x-if="snapshot !== null && !comparisonHasData">
                    <div class="flex items-center gap-3 px-4 py-5" data-comparison-empty>
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-info-soft text-info"><x-icon name="info" class="size-5" /></span>
                        <div class="text-sm">
                            <p class="font-medium">هنوز قیمتی از منابع در حافظه نیست</p>
                            <p class="text-xs text-muted">حافظهٔ منبع‌ها با هر دریافت پر می‌شود و پس از دو برابر پنجرهٔ تازگی پاک می‌شود. یک دور دریافت اجرا کنید.</p>
                        </div>
                    </div>
                </template>

                <div class="max-h-[26rem] overflow-auto" x-show="comparisonHasData">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 z-10 bg-surface-2 text-right text-[11px] text-muted shadow-[inset_0_-1px_0_var(--color-line)]">
                            <tr>
                                <th class="px-3 py-2 font-medium">ابزار</th>
                                <th class="px-2 py-2 text-left font-medium">نهایی</th>
                                <template x-for="source in comparison.sources" :key="source.key">
                                    <th class="px-2 py-2 text-center font-medium" :data-source="source.key">
                                        <span class="inline-flex items-center gap-1">
                                            <span class="size-1.5 rounded-full" :class="dot(sourceStatus(source.key))" aria-hidden="true"></span>
                                            <span class="ltr" x-text="source.key"></span>
                                        </span>
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-2">
                            <template x-for="row in comparison.rows" :key="row.code">
                                <tr :data-code="row.code" :data-off="row.is_off ? '1' : '0'" :class="row.is_off ? 'bg-danger-soft/40' : 'hover:bg-surface-2'">
                                    <td class="px-3 py-1.5">
                                        <div class="flex items-center gap-1.5">
                                            <span class="truncate font-medium" x-text="row.name"></span>
                                            <template x-if="row.final_is_stale"><x-icon name="timer" class="size-3 shrink-0 text-stale" /></template>
                                        </div>
                                        <span class="ltr block text-[10px] text-muted-2" x-text="row.code"></span>
                                    </td>
                                    <td class="num px-2 py-1.5 text-left font-semibold" x-text="money(row.final_mid)"></td>
                                    <template x-for="source in comparison.sources" :key="source.key">
                                        <td class="px-2 py-1.5 text-center" :data-cell="source.key">
                                            {{-- Se halat: mapping nadare (noghte), cache khali (khat), ya yek chip-e enheraf + sen. --}}
                                            <template x-if="!(source.key in row.cells)">
                                                <span class="text-muted-3" aria-label="نگاشت ندارد">·</span>
                                            </template>
                                            <template x-if="source.key in row.cells && row.cells[source.key] === null">
                                                <span class="text-muted-2" title="قیمتی در حافظه نیست">—</span>
                                            </template>
                                            <template x-if="row.cells[source.key]">
                                                <div class="inline-flex flex-col items-center gap-0.5" :title="`${money(row.cells[source.key].mid)} · ${row.cells[source.key].as_of_jalali}`">
                                                    <span class="num inline-flex items-center gap-0.5 rounded-chip px-1.5 py-px text-[11px] font-medium" :class="devChip(row.cells[source.key])">
                                                        <template x-if="row.cells[source.key].is_serving"><x-icon name="check" class="size-3" /></template>
                                                        <span x-text="dev(row.cells[source.key].deviation_pct)"></span>
                                                    </span>
                                                    <span class="num text-[10px] text-muted-2" x-text="age(row.cells[source.key].as_of)"></span>
                                                </div>
                                            </template>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        {{-- ── Amaliat: bazar ha, khata ha, niaz be tavajoh, eghdam-e sari' ───────── --}}
        <div class="grid min-w-0 items-start gap-4 md:grid-cols-2 xl:grid-cols-4">

            {{-- Jalase-haye bazar --}}
            <section class="min-w-0 overflow-hidden rounded-card border border-line bg-surface" aria-labelledby="sessions-title" data-sessions>
                <header class="flex items-center gap-2 border-b border-line px-4 py-2.5">
                    <h2 id="sessions-title" class="text-sm font-semibold">بازارها</h2>
                    <span class="text-[11px] text-muted-2">امروز · وقت تهران</span>
                </header>

                <template x-if="snapshot !== null && sessions.length === 0">
                    <p class="px-4 py-6 text-sm text-muted">ابزار فعالی نیست که بازاری داشته باشد.</p>
                </template>

                <ul class="divide-y divide-line-2">
                    <template x-for="s in sessions" :key="s.key">
                        <li class="px-4 py-3" :data-market="s.key" :data-open="s.open ? '1' : '0'">
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-1.5 text-xs font-medium">
                                    <span class="size-2 rounded-full" :class="s.open ? 'bg-up-strong' : 'bg-muted-3'" aria-hidden="true"></span>
                                    <span x-text="s.label"></span>
                                </span>
                                <span class="rounded-chip px-1.5 py-px text-[10px] font-medium" :class="s.open ? 'bg-up-soft text-up' : 'bg-surface-3 text-muted'" x-text="s.always_open ? 'بدون تقویم' : (s.open ? 'باز' : 'بسته')"></span>
                            </div>
                            {{-- Timeline-e 24 sa'ate: LTR ta 00:00 chap va 24:00 rast bashe, mesl-e sa'at. --}}
                            <div dir="ltr" class="relative mt-2.5 h-2 rounded-full bg-surface-3" aria-hidden="true">
                                <template x-for="(seg, i) in s.segments" :key="i">
                                    <span class="absolute inset-y-0 rounded-full bg-up-strong/70" :style="`left:${seg.from_pct}%;width:${Math.max(0.5, seg.to_pct - seg.from_pct)}%`"></span>
                                </template>
                                <span x-show="s.always_open" class="absolute inset-0 rounded-full bg-up-strong/40"></span>
                                <span class="absolute -top-1 h-4 w-0.5 rounded-full bg-ink" :style="`left:calc(${s.now_pct}% - var(--bp-hairline))`" data-now></span>
                            </div>
                            <div dir="ltr" class="num mt-1 flex justify-between text-[9px] text-muted-3" aria-hidden="true">
                                <span>۰۰</span><span>۰۶</span><span>۱۲</span><span>۱۸</span><span>۲۴</span>
                            </div>
                            <p class="mt-1.5 text-[11px] text-muted" x-text="sessionNext(s)"></p>
                        </li>
                    </template>
                </ul>
            </section>

            {{-- Feed-e khata ha --}}
            <section class="min-w-0 overflow-hidden rounded-card border border-line bg-surface" aria-labelledby="errors-title" data-errors>
                <header class="flex items-center gap-2 border-b border-line px-4 py-2.5">
                    <h2 id="errors-title" class="text-sm font-semibold">خطاها</h2>
                    <span class="num rounded-chip px-2 py-0.5 text-[11px]" :class="errors.length ? 'bg-danger-soft text-danger' : 'bg-ok-soft text-ok'" x-text="n(errors.length)"></span>
                </header>

                <template x-if="snapshot !== null && errors.length === 0">
                    <div class="flex items-center gap-3 px-4 py-5">
                        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-ok-soft text-ok"><x-icon name="circle-check" class="size-4" /></span>
                        <p class="text-sm text-muted">هیچ منبعی خطا نداده است.</p>
                    </div>
                </template>

                <ul class="divide-y divide-line-2">
                    <template x-for="e in errors" :key="e.key">
                        <li class="px-4 py-2.5" :data-source="e.key" :data-status="e.status">
                            <div class="flex items-center gap-2">
                                <span class="size-2 shrink-0 rounded-full" :class="dot(e.status)" aria-hidden="true"></span>
                                <span class="truncate text-xs font-medium" x-text="e.name"></span>
                                <span class="ltr rounded-chip bg-surface-3 px-1 text-[10px] text-muted" x-text="e.key"></span>
                                <span class="mr-auto rounded-chip px-1.5 py-px text-[10px] font-medium" :class="statusChip(e.status)" x-text="statusLabel(e.status)"></span>
                            </div>
                            <p class="mt-1 flex flex-wrap items-center gap-x-2 text-[11px] text-muted">
                                <span><span class="num font-medium" :class="e.consecutive_failures > 0 ? 'text-warn' : 'text-ink-3'" x-text="n(e.consecutive_failures)"></span> خطای پیاپی</span>
                                <span aria-hidden="true">·</span>
                                <span>آخرین موفق <span class="num text-ink-3" x-text="ago(e.last_success_at)" :title="e.last_success_jalali"></span></span>
                                <template x-if="e.cooldown_seconds > 0">
                                    <span class="inline-flex items-center gap-1 text-danger"><x-icon name="timer" class="size-3" />بازگشت تا <span class="num font-medium" x-text="countdown(e.disabled_until)"></span></span>
                                </template>
                            </p>
                            <template x-if="e.last_error">
                                <p class="ltr mt-1 truncate rounded-md bg-danger-soft px-2 py-0.5 text-[10px] text-danger" :title="e.last_error" x-text="e.last_error"></p>
                            </template>
                        </li>
                    </template>
                </ul>
            </section>

            {{-- Niaz be tavajoh --}}
            <section class="min-w-0 overflow-hidden rounded-card border border-line bg-surface" aria-labelledby="attention-title" data-attention>
                <header class="flex items-center gap-2 border-b border-line px-4 py-2.5">
                    <h2 id="attention-title" class="text-sm font-semibold">نیاز به توجه</h2>
                    <span class="num rounded-chip px-2 py-0.5 text-[11px]" :class="attention.length ? 'bg-danger-soft text-danger' : 'bg-ok-soft text-ok'" x-text="n(attention.length)"></span>
                </header>

                <template x-if="snapshot !== null && attention.length === 0">
                    <div class="flex items-center gap-3 px-4 py-5">
                        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-ok-soft text-ok"><x-icon name="circle-check" class="size-4" /></span>
                        <div>
                            <p class="text-sm font-medium">همه‌چیز روبه‌راه است</p>
                            <p class="text-[11px] text-muted">نه قیمت کهنه‌ای، نه منبع خاموشی.</p>
                        </div>
                    </div>
                </template>

                <ul class="divide-y divide-line-2">
                    <template x-for="item in attention" :key="item.kind + item.code">
                        <li class="flex items-start gap-2.5 px-4 py-2.5">
                            <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-md" :class="toneChip(item.tone)">
                                <x-icon name="alert" class="size-3.5" x-show="item.tone === 'danger' || item.tone === 'warn'" />
                                <x-icon name="timer" class="size-3.5" x-show="item.tone === 'stale'" />
                                <x-icon name="dot" class="size-3.5" x-show="item.tone === 'info'" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-1.5 text-xs font-medium">
                                    <span x-text="item.title"></span>
                                    <span class="ltr rounded-chip bg-surface-3 px-1 text-[10px] font-normal text-muted" x-text="item.code"></span>
                                </p>
                                <p class="mt-0.5 text-[11px] leading-relaxed text-muted" x-text="item.detail"></p>
                                <template x-if="item.until">
                                    <p class="mt-0.5 text-[11px] text-danger">بازگشت تا <span class="num" x-text="countdown(item.until)"></span></p>
                                </template>
                            </div>
                        </li>
                    </template>
                </ul>
            </section>

            {{-- Eghdam-e sari' --}}
            <section class="min-w-0 overflow-hidden rounded-card border border-line bg-surface" aria-labelledby="actions-title" data-actions>
                <header class="flex items-center gap-2 border-b border-line px-4 py-2.5">
                    <h2 id="actions-title" class="text-sm font-semibold">اقدام سریع</h2>
                </header>
                <div class="space-y-3 px-4 py-3">
                    <button type="button" @click="tick()" :disabled="ticking" data-action="tick"
                            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-brand text-sm font-medium text-on-brand shadow-(--bp-elev-brand) transition-colors hover:bg-brand-2 disabled:cursor-progress disabled:bg-brand/60 disabled:shadow-none">
                        <x-icon name="refresh" class="size-4" ::class="ticking && 'animate-spin'" />
                        <span x-text="ticking ? 'در حال ارسال…' : 'اجرای یک دور دریافت'">اجرای یک دور دریافت</span>
                    </button>
                    <p class="text-[11px] leading-relaxed text-muted">هر منبع فعالِ خارج از وقفهٔ خطا همین حالا یک بار دریافت می‌شود؛ منتظر نوبت بعدی نمی‌ماند.</p>

                    <ul class="divide-y divide-line-2 rounded-lg border border-line">
                        <li><a href="/panel/sources/new" class="flex items-center gap-2 px-3 py-2 text-xs text-ink-2 transition-colors hover:bg-surface-2 hover:text-ink"><x-icon name="plus" class="size-3.5 text-brand" />افزودن منبع قیمت</a></li>
                        <li><a href="/panel/instruments/new" class="flex items-center gap-2 px-3 py-2 text-xs text-ink-2 transition-colors hover:bg-surface-2 hover:text-ink"><x-icon name="plus" class="size-3.5 text-brand" />افزودن ابزار</a></li>
                        <li><a href="/panel/reports/providers" class="flex items-center gap-2 px-3 py-2 text-xs text-ink-2 transition-colors hover:bg-surface-2 hover:text-ink"><x-icon name="gauge" class="size-3.5 text-brand" />گزارش عملکرد منابع</a></li>
                        <li><a href="/panel/settings" class="flex items-center gap-2 px-3 py-2 text-xs text-ink-2 transition-colors hover:bg-surface-2 hover:text-ink"><x-icon name="cog" class="size-3.5 text-brand" />تنظیمات بازار و تازگی</a></li>
                    </ul>
                </div>
            </section>
        </div>
    </div>
</x-layouts.panel>
