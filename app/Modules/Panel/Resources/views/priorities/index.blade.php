{{-- «اولویت منابع» per-symbol (P5-07): zanjire ye har symbol — feed e
     mo'asser (API) va «پر کردن بر اساس اولویت» e gheymat e dasti har do az
     hamin miyan. AJAX (hich reload/scroll)، hamun olgu ye safhe ye dasti. --}}

<x-layouts.panel title="اولویت منابع" current="priorities" :breadcrumbs="['پنل', 'اولویت منابع']">
    <div x-data="symbolPriorities({
            route: @js(route('panel.priorities.update')),
            rows: @js($rows),
            names: @js($names),
        })">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-ink md:text-3xl">اولویت منابع</h1>
                <p class="mt-1 text-sm text-muted">برای هر نماد مشخص کنید قیمت اول از کدام منبع گرفته شود و اگر آن منبع کهنه یا قطع بود، نوبت کدام است.</p>
            </div>

            <x-panel.help key="priorities" title="راهنمای اولویت منابع">
                <h3>چطور کار می‌کند؟</h3>
                <ul>
                    <li>برای هر نماد، اولین منبعِ این زنجیره که قیمت <b>تازه</b> دارد انتخاب می‌شود؛ اگر کهنه یا قطع بود، نوبت منبع بعدی است.</li>
                    <li>نمادی که اولویت سفارشی ندارد، از <b>ترتیب پیش‌فرض</b> (ستون ترتیب در صفحه منابع قیمت) پیروی می‌کند.</li>
                    <li>منبعی که در زنجیره سفارشی نیست حذف نمی‌شود — بعد از منابع انتخابی و به ترتیب پیش‌فرض می‌آید. پس افزودن منبع جدید هیچ نمادی را بی‌قیمت نمی‌کند.</li>
                    <li><b>دستی</b> همیشه آخر است: فقط وقتی همه منابع واقعی کهنه یا قطع باشند استفاده می‌شود.</li>
                </ul>
                <h3>کجا اثر دارد؟</h3>
                <p>هم روی فید مؤثری که از API می‌فروشیم، هم روی دکمه «پر کردن بر اساس اولویت» در صفحه قیمت دستی.</p>
            </x-panel.help>
        </div>

        <section class="mt-6 overflow-hidden rounded-2xl bg-surface shadow-xs">
            <ul class="divide-y divide-line-2">
                <template x-for="row in rows" :key="row.symbol">
                    <li class="px-4 py-4 md:px-5" :data-priority-row="row.symbol">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
                            <div class="min-w-40">
                                <p class="font-medium text-ink" x-text="row.label"></p>
                                <p class="ltr text-start text-xs text-muted-3" x-text="row.symbol"></p>
                            </div>

                            {{-- Zanjire ye nahayi: shomare + esm؛ manual kam-rang (hamishe akhar). --}}
                            <ol class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5" x-show="editing !== row.symbol" aria-label="ترتیب منابع">
                                <template x-for="(key, i) in row.chain" :key="key">
                                    <li class="flex items-center gap-1.5">
                                        <span class="inline-flex h-8 items-center gap-1.5 rounded-full px-3 text-xs font-medium"
                                              :class="key === 'manual' ? 'bg-surface-2 text-muted' : (i === 0 ? 'bg-brand-soft text-brand-2' : 'bg-surface-3 text-ink-3')">
                                            <span class="num opacity-70" x-text="i + 1"></span>
                                            <span x-text="name(key)"></span>
                                        </span>
                                        <x-icon name="chevron" class="size-3.5 text-muted-3" x-show="i < row.chain.length - 1" />
                                    </li>
                                </template>
                            </ol>

                            <div class="flex items-center gap-2" x-show="editing !== row.symbol">
                                <span data-priority-badge class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                      :class="row.isCustom ? 'bg-brand-soft text-brand-2' : 'bg-surface-3 text-muted'"
                                      x-text="row.isCustom ? 'سفارشی' : 'پیش‌فرض'"></span>
                                <button type="button" @click="edit(row.symbol)" :disabled="editing !== null"
                                        class="inline-flex h-10 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-ink hover:bg-surface-2 disabled:opacity-50"
                                        :aria-label="'ویرایش اولویت ' + row.label">
                                    <x-icon name="pencil" class="size-4" />
                                    ویرایش
                                </button>
                                <button type="button" x-show="row.isCustom" @click="reset(row.symbol)" :disabled="busy"
                                        class="inline-flex h-10 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-muted hover:bg-surface-2 hover:text-ink disabled:opacity-50"
                                        :aria-label="'بازگشت به پیش‌فرض ' + row.label">
                                    <x-icon name="refresh" class="size-4" />
                                    پیش‌فرض
                                </button>
                            </div>
                        </div>

                        {{-- ── Editor (faghat yek symbol dar har lahze) ── --}}
                        <div x-show="editing === row.symbol" x-cloak class="mt-3 rounded-xl bg-surface-2 p-3">
                            <ol class="space-y-1.5" aria-label="زنجیره در حال ویرایش">
                                <template x-for="(key, i) in draft" :key="key">
                                    <li class="flex items-center gap-2 rounded-lg bg-surface px-3 py-1.5 shadow-xs">
                                        <span class="num w-5 text-center text-xs font-bold text-muted" x-text="i + 1"></span>
                                        <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink" x-text="name(key)"></span>
                                        <button type="button" @click="move(i, -1)" :disabled="i === 0"
                                                class="grid size-10 place-items-center rounded-lg text-muted hover:bg-surface-2 hover:text-ink disabled:opacity-30"
                                                :aria-label="'بالاتر: ' + name(key)">
                                            <x-icon name="chevron-up" class="size-4" />
                                        </button>
                                        <button type="button" @click="move(i, 1)" :disabled="i === draft.length - 1"
                                                class="grid size-10 place-items-center rounded-lg text-muted hover:bg-surface-2 hover:text-ink disabled:opacity-30"
                                                :aria-label="'پایین‌تر: ' + name(key)">
                                            <x-icon name="chevron-down" class="size-4" />
                                        </button>
                                        <button type="button" @click="drop(i)"
                                                class="grid size-10 place-items-center rounded-lg text-muted hover:bg-danger-soft hover:text-danger"
                                                :aria-label="'حذف از زنجیره: ' + name(key)">
                                            <x-icon name="trash" class="size-4" />
                                        </button>
                                    </li>
                                </template>
                                <li class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm text-muted" x-show="'manual' in names">
                                    <span class="w-5 text-center">—</span>
                                    <span x-text="name('manual')"></span>
                                    <span class="text-xs">(همیشه آخر)</span>
                                </li>
                            </ol>

                            <p class="mt-2 text-xs text-muted-2" x-show="draft.length === 0">زنجیره خالی است — ذخیره یعنی بازگشت به ترتیب پیش‌فرض.</p>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <select x-show="addable.length > 0" @change="add($event.target.value); $event.target.value = ''"
                                        aria-label="افزودن منبع"
                                        class="h-10 min-w-40 rounded-xl border border-line-3 bg-surface px-3 text-sm focus:border-brand focus:outline-none">
                                    <option value="">+ افزودن منبع…</option>
                                    <template x-for="key in addable" :key="key">
                                        <option :value="key" x-text="name(key)"></option>
                                    </template>
                                </select>

                                <span class="flex-1"></span>

                                <button type="button" @click="cancel()"
                                        class="inline-flex h-10 items-center rounded-xl px-4 text-sm font-medium text-muted hover:bg-surface hover:text-ink">
                                    انصراف
                                </button>
                                <button type="button" @click="save(row.symbol)" :disabled="busy"
                                        class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand hover:bg-brand-2 disabled:opacity-60">
                                    <x-icon name="check" class="size-4" />
                                    ذخیره
                                </button>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </section>

        <p class="mt-3 text-xs text-muted-2">
            ترتیب پیش‌فرض همان ستون «ترتیب» در
            <a href="{{ route('panel.providers.index') }}" class="font-medium text-brand-2 hover:underline">منابع قیمت</a>
            است.
        </p>
    </div>
</x-layouts.panel>
