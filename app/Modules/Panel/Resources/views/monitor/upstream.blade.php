{{-- Upstream inspector (P4-14): call haye MA be provider haye gheymat —
     har TALASH e HTTP yek radif (retry ha joda). Filter ha client-side-and
     (Alpine) chun kol e data hamin 100 radif e load-shode-ast (UI-only). --}}

<x-layouts.panel title="پایش منابع" current="monitor" :breadcrumbs="['پنل', 'پایش زنده', 'منابع']">
    <div x-data="{
            calls: @js($calls),
            meta: @js($providerMeta),
            provider: '',
            onlyErrors: false,
            get filtered() {
                return this.calls.filter((c) =>
                    (this.provider === '' || c.provider === this.provider)
                    && (! this.onlyErrors || ! c.succeeded));
            },
            get providers() { return [...new Set(this.calls.map((c) => c.provider))]; },
            name(key) { return this.meta[key]?.name ?? key; },
            clock(iso) {
                try { return new Date(iso).toLocaleTimeString('en-GB', { timeZone: 'Asia/Tehran', hour12: false }); }
                catch { return '—'; }
            },
            kb(bytes) { return bytes === null ? '—' : (bytes / 1024).toFixed(1) + ' KB'; },
        }">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-ink md:text-3xl">پایش منابع</h1>
                <p class="mt-1 text-sm text-muted">فراخوانی‌های ما به منابع قیمت (نه مشتری به ما): وضعیت، مدت و خطای هر تلاش — برای دیباگ منبع.</p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('panel.monitor.ticks') }}"
                   class="inline-flex h-10 items-center gap-2 rounded-xl border border-line px-4 text-sm font-medium text-ink hover:bg-surface-2">
                    <x-icon name="eye" class="size-4" />
                    پایش tick
                </a>
                <a href="{{ route('panel.monitor.api') }}"
                   class="inline-flex h-10 items-center gap-2 rounded-xl border border-line px-4 text-sm font-medium text-ink hover:bg-surface-2">
                    <x-icon name="code" class="size-4" />
                    پایش API
                </a>

                <x-panel.help key="monitor-upstream" title="راهنمای پایش منابع">
                    <h3>این صفحه چیست؟</h3>
                    <p>هر tick، ما به هر منبع فعال یک درخواست HTTP می‌زنیم. این‌جا <b>هر تلاش</b> جداگانه ثبت می‌شود — حتی تلاش اولِ شکست‌خورده‌ای که retry جبرانش کرد.</p>
                    <dl>
                        <dt>وضعیت «قطع»</dt><dd>هیچ پاسخ HTTP نیامد — خطای اتصال/TLS/timeout. متن خطا را ببینید.</dd>
                        <dt>۲۰۰ ولی ناموفق</dt><dd>منبع جواب داد ولی parse شکست خورد — معمولا markup عوض شده.</dd>
                        <dt>تلاش ۲</dt><dd>خطای گذرای اتصال بود و در همان tick دوباره تلاش شد. اگر هر tick تکرار شود، منبع در حال خراب شدن است.</dd>
                    </dl>
                    <p>فقط ~یک ساعت آخر نگه داشته می‌شود؛ این ابزار دیباگ لحظه‌ای است، نه آرشیو.</p>
                </x-panel.help>
            </div>
        </div>

        {{-- Filter ha — UI-only ru hamin data ye load-shode. --}}
        <div class="mt-5 flex flex-wrap items-center gap-3 rounded-2xl bg-surface card-p shadow-xs" x-cloak>
            <label class="flex items-center gap-2 text-sm font-medium text-ink">
                منبع:
                <select x-model="provider"
                        class="h-10 min-w-36 rounded-xl border border-line-3 bg-surface px-3 text-sm focus:border-brand focus:outline-none">
                    <option value="">همه</option>
                    <template x-for="key in providers" :key="key">
                        <option :value="key" x-text="name(key)" :selected="provider === key"></option>
                    </template>
                </select>
            </label>

            <label class="flex min-h-11 cursor-pointer select-none items-center gap-2 text-sm font-medium text-ink">
                <input type="checkbox" x-model="onlyErrors" class="size-4 rounded border-line-3 text-brand focus:ring-brand">
                فقط خطاها
            </label>

            <span class="ms-auto text-xs text-muted-2">
                <span class="num" x-text="filtered.length"></span> از <span class="num" x-text="calls.length"></span> فراخوانی
            </span>
        </div>

        @if ($calls === [])
            <section class="mt-5 rounded-2xl bg-surface card-p py-10 text-center text-muted shadow-xs">
                هنوز فراخوانی‌ای ثبت نشده — با اولین tick، درخواست‌های ما به منابع این‌جا دیده می‌شوند.
            </section>
        @else
            <section class="mt-5 overflow-hidden rounded-2xl bg-surface shadow-xs" x-cloak>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[52rem] text-sm">
                        <thead>
                            <tr class="border-b border-line text-xs font-semibold text-muted">
                                <th class="px-4 py-3 text-start font-semibold">زمان</th>
                                <th class="px-4 py-3 text-start font-semibold">منبع</th>
                                <th class="px-4 py-3 text-start font-semibold">وضعیت</th>
                                <th class="px-4 py-3 text-start font-semibold">مدت</th>
                                <th class="px-4 py-3 text-start font-semibold">تلاش</th>
                                <th class="px-4 py-3 text-start font-semibold">نمادها</th>
                                <th class="px-4 py-3 text-start font-semibold">حجم</th>
                                <th class="px-4 py-3 text-start font-semibold">آدرس / خطا</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-2">
                            <template x-for="(call, i) in filtered" :key="i">
                                <tr :class="! call.succeeded && 'bg-down-soft/30'">
                                    <td class="num px-4 py-2.5 text-xs font-bold text-ink" x-text="clock(call.at)"></td>
                                    <td class="px-4 py-2.5">
                                        <span class="font-medium text-ink" x-text="name(call.provider)"
                                              :title="'driver: ' + (meta[call.provider]?.driver ?? '?')"></span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        {{-- `ok`/`down` — hamun tone haye x-panel.pill، vali chon
                                             tone client-side ast، class ha mostaghim bind mishan. --}}
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold"
                                              :class="call.succeeded ? 'bg-ok-soft text-ok' : 'bg-down-soft text-down'">
                                            <span class="num" x-text="call.status ?? 'قطع'"></span>
                                        </span>
                                    </td>
                                    <td class="num px-4 py-2.5 text-xs text-muted-2" x-text="call.latency_ms.toLocaleString('en-US') + 'ms'"></td>
                                    <td class="px-4 py-2.5">
                                        <span class="num text-xs" :class="call.attempt > 1 ? 'font-semibold text-warn' : 'text-muted-2'"
                                              x-text="call.attempt > 1 ? '↻ ' + call.attempt : call.attempt"
                                              title="تلاش دوم یعنی خطای گذرای اتصال در همان tick"></span>
                                    </td>
                                    <td class="num px-4 py-2.5 text-xs text-muted-2" x-text="call.symbol_count"></td>
                                    {{-- `ltr` vagarna bidi «5.3 KB» ro «KB 5.3» neshun mide. --}}
                                    <td class="ltr num whitespace-nowrap px-4 py-2.5 text-start text-xs text-muted-2" x-text="kb(call.bytes)"></td>
                                    <td class="max-w-80 px-4 py-2.5">
                                        <p class="ltr num truncate text-xs text-muted" dir="ltr" x-text="call.method + ' ' + call.url" :title="call.url"></p>
                                        <template x-if="call.error">
                                            <p class="ltr mt-0.5 text-xs text-down" dir="ltr" x-text="call.error"></p>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <p class="border-t border-line-2 px-4 py-6 text-center text-sm text-muted" x-show="filtered.length === 0">
                    با این فیلترها چیزی پیدا نشد.
                </p>
            </section>
        @endif
    </div>
</x-layouts.panel>
