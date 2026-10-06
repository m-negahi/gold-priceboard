{{-- Gheymat haye DASTI (fallback) — Phase 4/5. AJAX (hich reload/scroll). --}}

<x-layouts.panel title="قیمت دستی" current="manual" :breadcrumbs="['پنل', 'قیمت دستی']">
    <div x-data="manualPrices({
            routes: {
                update: @js(route('panel.manual.update')),
                seed: @js(route('panel.manual.seed')),
                seedPriority: @js(route('panel.manual.seed-priority')),
                remove: @js(route('panel.manual.remove')),
            },
            rows: @js($rows),
            effective: @js($effective),
        })">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-ink md:text-3xl">قیمت دستی (پشتیبان)</h1>
                <p class="mt-1 text-sm text-muted">اگر همه‌ی منابع قطع شوند، فید روی این قیمت‌ها می‌افتد. اول از یک منبع پر کنید، بعد دستی اصلاح کنید.</p>
            </div>

            <x-panel.help key="manual" title="راهنمای قیمت دستی">
                <h3>چطور کار می‌کند؟</h3>
                <ul>
                    <li>فید فروشی به ترتیب اولویتِ منابع قیمت می‌دهد؛ منبع «دستی» آخرین اولویت است.</li>
                    <li>اگر همه‌ی منابعِ بالاتر کهنه/قطع شوند، قیمت از «دستی» سرو می‌شود و <b>هشدار</b> روشن می‌شود.</li>
                    <li>«پر کردن از منبع» آخرین قیمت‌های آن منبع را این‌جا کپی می‌کند تا از صفر شروع نکنید.</li>
                    <li>قیمت دستی که بیش از ۲۴ ساعت به‌روز نشده باشد، هشدار می‌گیرد.</li>
                </ul>
            </x-panel.help>
        </div>

        {{-- Banner e vaziat (zende، bedun e reload). --}}
        <div class="mt-5 rounded-2xl card-p shadow-xs" :class="alarm ? 'bg-warn-soft' : 'bg-surface'">
            <div class="flex flex-wrap items-center gap-3">
                <template x-if="alarm">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-warn-soft px-2 py-0.5 text-xs font-semibold text-warn"><span class="size-1.5 rounded-full bg-warn"></span>هشدار</span>
                        <span class="text-sm font-medium text-warn"><span class="num" x-text="fallbackCount"></span> نماد از قیمت پشتیبان (دستی/کهنه) سرو می‌شوند — منابع اصلی را بررسی کنید.</span>
                    </span>
                </template>
                <template x-if="!alarm">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-ok-soft px-2 py-0.5 text-xs font-semibold text-ok"><span class="size-1.5 rounded-full bg-up-strong"></span>سالم</span>
                        <span class="text-sm text-muted">همه‌ی نمادها از منابع تازه سرو می‌شوند؛ فید روی پشتیبان نیفتاده.</span>
                    </span>
                </template>
            </div>
        </div>

        {{-- Seed bar asas e OLAVIAT e per-symbol (P5-07) — rah e asli. --}}
        <div class="mt-5 flex flex-wrap items-center gap-3 rounded-2xl bg-surface card-p shadow-xs" data-seed-priority>
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-ink">پر کردن بر اساس اولویت</p>
                <p class="mt-0.5 text-sm text-muted">
                    هر نماد از اولین منبعِ تازهٔ زنجیرهٔ خودش پر می‌شود (مثلاً اول تی‌جی‌جی‌یو، اگر کهنه بود اتحادیه). نمادی که هیچ منبع تازه‌ای ندارد دست نمی‌خورد.
                    <a href="{{ route('panel.priorities.index') }}" class="font-medium text-brand-2 hover:underline">تنظیم اولویت‌ها</a>
                </p>
            </div>
            <button type="button" @click="seedByPriority()" :disabled="busy.seedPriority"
                    class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand shadow-(--bp-elev-brand) hover:bg-brand-2 disabled:opacity-60">
                <x-icon name="layers" class="size-4" />
                پر کردن بر اساس اولویت
            </button>
        </div>

        {{-- Seed az YEK source (hame ye symbol ha az hamun) — gozine ye dovom. --}}
        <div class="mt-3 flex flex-wrap items-end gap-3 rounded-2xl bg-surface card-p shadow-xs">
            <div class="min-w-48 flex-1">
                <label class="mb-1.5 block text-sm font-medium">پر کردن همه از یک منبع</label>
                <x-form.select name="seed_provider" :options="$seedSources" placeholder="یک منبع انتخاب کنید" x-model="seedProvider" />
            </div>
            <button type="button" @click="seed()" :disabled="!seedProvider || busy.seed"
                    class="inline-flex h-11 items-center gap-2 rounded-xl border border-line px-4 text-sm font-semibold text-ink hover:bg-surface-2 disabled:opacity-60">
                <x-icon name="download" class="size-4" />
                پر کن
            </button>
            <p class="w-full text-xs text-muted-2 sm:w-auto sm:flex-1">آخرین قیمت‌های آن منبع کپی می‌شوند؛ قیمت‌های فعلی دستی بازنویسی می‌شوند.</p>
        </div>

        {{-- Jadval — Alpine، hich reload. --}}
        <section class="mt-5 overflow-hidden rounded-2xl bg-surface shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead>
                        <tr class="border-b border-line text-xs font-semibold text-muted">
                            <th class="px-5 py-3 text-start font-semibold">نماد</th>
                            <th class="px-5 py-3 text-start font-semibold">قیمت دستی (تومان/دلار)</th>
                            <th class="px-5 py-3 text-start font-semibold">منبع</th>
                            <th class="px-5 py-3 text-start font-semibold">آخرین به‌روزرسانی</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-2">
                        <template x-for="row in rows" :key="row.symbol">
                            <tr :class="row.isAlarm && 'bg-warn-soft/40'">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-ink" x-text="row.label"></p>
                                    <p class="ltr text-xs text-muted-3" x-text="row.symbol"></p>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <input type="text" inputmode="decimal" x-model="row.display"
                                               @keydown.enter.prevent="save(row.symbol)"
                                               class="num ltr h-10 w-40 rounded-xl border border-line-3 bg-surface px-3 text-left text-sm focus:border-brand focus:shadow-(--bp-elev-brand) focus:outline-none"
                                               :aria-label="'قیمت ' + row.label">
                                        <button type="button" @click="save(row.symbol)" :disabled="busy[row.symbol]"
                                                class="inline-flex size-10 items-center justify-center rounded-lg bg-brand-soft text-brand-2 hover:bg-brand-soft-2 disabled:opacity-60" aria-label="ثبت">
                                            <x-icon name="check" class="size-4" />
                                        </button>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="ltr text-xs text-muted" x-text="row.sourceHint || '—'"></span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="num text-xs" :class="row.isAlarm ? 'font-semibold text-warn' : 'text-muted'" x-text="when(row.updatedAt)"></span>
                                    <template x-if="row.isAlarm">
                                        <span class="inline-flex items-center rounded-full bg-warn-soft px-2 py-0.5 text-xs font-semibold text-warn">کهنه</span>
                                    </template>
                                </td>
                                <td class="px-5 py-3 text-end">
                                    <button type="button" x-show="row.hasPrice" @click="remove(row.symbol)" :disabled="busy[row.symbol]"
                                            class="inline-flex size-9 items-center justify-center rounded-lg text-muted hover:bg-danger-soft hover:text-danger" aria-label="حذف">
                                        <x-icon name="trash" class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>

        <p class="mt-3 text-xs text-muted-2">برای فعال شدن فید پشتیبان، منبع «دستی» را در صفحه‌ی منابع روشن کنید. قیمت دستی هرگز به‌خاطر کهنگی رد نمی‌شود — همیشه نمایش داده می‌شود.</p>
    </div>
</x-layouts.panel>
