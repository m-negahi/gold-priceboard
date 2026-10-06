{{-- «اولویت منابع» per-symbol (P5-07): zanjire ye har symbol — feed e
     mo'asser (API) va «پر کردن بر اساس اولویت» e gheymat e dasti har do az
     hamin miyan. AJAX (hich reload/scroll)، hamun olgu ye safhe ye dasti. --}}

<x-layouts.panel title="اولویت و قیمت دستی" current="priorities" :breadcrumbs="['پنل', 'اولویت و قیمت دستی']">
    <div x-data="symbolPriorities({
            route: @js(route('panel.priorities.update')),
            rows: @js($rows),
            names: @js($names),
            autoNames: @js($autoNames),
            manualActive: @js($manualActive),
            effective: @js($effective),
            routes: {
                manual: @js(route('panel.manual.update')),
                remove: @js(route('panel.manual.remove')),
                seedPriority: @js(route('panel.manual.seed-priority')),
                settings: @js(route('panel.priorities.settings')),
                preview: @js(route('panel.manual.import.preview')),
                confirm: @js(route('panel.manual.import.confirm')),
            },
        })">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-ink md:text-3xl">اولویت و قیمت دستی</h1>
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
                <p>«فقط دستی» قیمت ثبت‌شده شما را مستقیم در فید API استفاده می‌کند؛ اولویت‌ها حفظ می‌شوند و تا خاموش کردن این حالت، هیچ منبعی برای این نماد انتخاب یا خودکار کپی نمی‌شود.</p>
                <p>پشتیبان دستی برای هر نماد جدا روشن یا خاموش می‌شود. به‌روزرسانی خودکار فقط قیمت خام و تازه را کپی می‌کند؛ خطا و کهنگی، آخرین قیمت سالم را تغییر نمی‌دهند.</p>
            </x-panel.help>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-3 rounded-2xl bg-surface card-p shadow-xs">
            <p class="flex-1 text-sm text-muted" x-show="!effective.alarm">قیمت مؤثر هر نماد در کارت آن مشخص است. قیمت‌های ریالی را به ریال وارد کنید؛ نمادهای جهانی به دلار باقی می‌مانند.</p>
            <p x-show="effective.alarm" x-cloak class="flex-1 text-sm text-warn"><span class="num font-bold" x-text="effective.fallbackCount"></span> نماد از قیمت پشتیبان یا کهنه استفاده می‌کند.</p>
            @can('pricing.manage')
                <button type="button" @click="seedPriority()" :disabled="busy" class="inline-flex h-11 items-center gap-2 rounded-xl border border-line px-4 text-sm font-semibold hover:bg-surface-2 disabled:opacity-50"><x-icon name="layers" class="size-4" />کپی قیمت‌های تازه از اولویت‌ها</button>
            @endcan
        </div>
        @unless ($manualActive)
            <p class="mt-3 rounded-xl bg-warn-soft p-3 text-sm text-warn">منبع دستی خاموش است؛ پشتیبان دستی در فید استفاده نمی‌شود. حالت «فقط دستی» مستقل است و قیمت شما را مستقیم استفاده می‌کند.</p>
        @endunless

        @can('pricing.manage')
        <section class="mt-5 rounded-2xl bg-surface card-p shadow-xs" data-manual-import>
            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1"><h2 class="font-bold text-ink">ورود قیمت‌ها از اکسل</h2><p class="mt-1 text-sm text-muted">فایل xlsx با ستون‌های symbol، label، price و unit. واحد IRR برای ریال و USD برای دلار؛ ردیف‌های بدون قیمت نادیده گرفته می‌شوند.</p></div>
                <a href="{{ route('panel.manual.template') }}" class="inline-flex h-11 items-center gap-2 rounded-xl border border-line px-4 text-sm font-medium"><x-icon name="download" class="size-4" />نمونه اکسل</a>
                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl bg-brand-soft px-4 text-sm font-semibold text-brand-2">انتخاب اکسل<input type="file" accept=".xlsx" @change="previewWorkbook($event)" :disabled="busy" class="min-w-0 max-w-48 text-xs" aria-label="فایل اکسل قیمت دستی"></label>
            </div>
            <div x-show="preview" x-cloak class="mt-4" data-import-preview>
                <p class="mb-3 text-sm text-muted">پیش‌نمایش فقط برای بررسی است. تا دکمه تأیید را نزنید، هیچ قیمتی ذخیره نمی‌شود.</p>
                <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b border-line text-muted"><th class="p-2 text-start">ردیف</th><th class="p-2 text-start">نماد</th><th class="p-2 text-start">قیمت</th><th class="p-2 text-start">نتیجه</th></tr></thead><tbody><template x-for="item in preview?.rows || []" :key="item.row"><tr class="border-b border-line-2"><td class="num p-2" x-text="item.row"></td><td class="p-2"><span x-text="item.label"></span><span class="ltr block text-xs text-muted" x-text="item.symbol"></span></td><td class="num p-2"><span x-text="item.price"></span> <span x-text="item.unitLabel"></span></td><td class="p-2" :class="item.error ? 'text-danger' : 'text-ok'" x-text="item.error || 'آماده ثبت'"></td></tr></template></tbody></table></div>
                <div class="mt-3 flex flex-wrap items-center gap-3"><button type="button" @click="confirmWorkbook()" :disabled="busy || !preview || preview.hasErrors || !preview.count" class="h-11 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand disabled:opacity-50">تأیید و ثبت قیمت‌ها</button><p class="text-xs text-danger" x-show="preview?.hasErrors">فایل را اصلاح کنید؛ با وجود خطا، هیچ ردیفی ثبت نمی‌شود.</p><p class="text-xs text-muted" x-show="preview && !preview.count">هیچ قیمت واردشده‌ای در فایل وجود ندارد.</p></div>
            </div>
        </section>
        @endcan

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

                            @can('pricing.manage')
                            <div class="flex items-center gap-2" x-show="editing !== row.symbol">
                                <span data-priority-badge class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                      :class="row.isCustom ? 'bg-brand-soft text-brand-2' : 'bg-surface-3 text-muted'"
                                      x-text="row.isCustom ? 'سفارشی' : 'پیش‌فرض'"></span>
                                <button type="button" @click="edit(row.symbol)" :disabled="editing !== null || row.manualOnly"
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
                            @endcan
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted"><span>قیمت مؤثر:</span><span class="font-semibold text-ink" x-text="row.manualOnly ? 'فقط دستی' : (row.selectedProvider ? name(row.selectedProvider) : 'بدون قیمت')"></span><span x-show="row.usingFallback" class="rounded-full bg-warn-soft px-2 py-1 text-warn">پشتیبان</span><span x-show="row.manualOnly" class="text-muted">اولویت‌ها حفظ شده‌اند؛ انتخاب منبع و کپی خودکار متوقف است.</span></div>

                        @can('pricing.manage')
                        <div class="mt-4 grid gap-4 rounded-xl bg-surface-2 p-3 md:grid-cols-2 xl:grid-cols-3" data-manual-row>
                            <div><label class="mb-1.5 block text-sm font-medium" :for="'price-' + row.symbol">قیمت دستی (<span x-text="row.unitLabel"></span>)</label><div class="flex items-center gap-2"><input type="text" inputmode="decimal" :id="'price-' + row.symbol" x-model="row.display" @keydown.enter.prevent="savePrice(row)" :aria-label="'قیمت ' + row.label" class="num ltr h-11 min-w-0 flex-1 rounded-xl border border-line-3 bg-surface px-3 text-sm focus:border-brand focus:outline-none"><button type="button" @click="savePrice(row)" :disabled="busy" class="grid size-11 place-items-center rounded-xl bg-brand-soft text-brand-2 disabled:opacity-50" aria-label="ثبت قیمت دستی"><x-icon name="check" class="size-4" /></button><button type="button" x-show="row.hasPrice" @click="removePrice(row)" :disabled="busy || row.manualOnly" class="grid size-11 place-items-center rounded-xl text-muted hover:bg-danger-soft hover:text-danger disabled:opacity-40" aria-label="حذف قیمت دستی"><x-icon name="trash" class="size-4" /></button></div><p class="num mt-2 text-xs text-muted"><span x-text="row.updatedDisplay"></span> · <span x-text="row.sourceHint ? name(row.sourceHint) : 'ثبت نشده'"></span> <span x-show="row.isAlarm" class="text-warn">کهنه</span></p></div>
                            <div class="space-y-1"><label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" x-model="row.manualOnly" class="size-4 accent-brand" :disabled="busy" :aria-label="'فقط دستی ' + row.label">فقط قیمت دستی</label><label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" x-model="row.manualFallbackEnabled" class="size-4 accent-brand" :disabled="busy || row.manualOnly" :aria-label="'پشتیبان دستی ' + row.label">پشتیبان دستی هنگام قطع منابع</label></div>
                            <div><label class="mb-1.5 block text-sm font-medium" :for="'backup-' + row.symbol">به‌روزرسانی خودکار قیمت دستی</label><select x-model="row.manualAutoSource" :id="'backup-' + row.symbol" :disabled="busy || row.manualOnly" class="h-11 w-full rounded-xl border border-line-3 bg-surface px-3 text-sm disabled:opacity-50"><option value="none">خاموش؛ فقط ثبت دستی</option><option value="priority">اولین منبع تازه از اولویت‌ها</option><template x-for="(label, key) in autoNames" :key="key"><option :value="key" x-text="label"></option></template></select><button type="button" @click="savePolicy(row)" :disabled="busy" class="mt-2 h-11 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand disabled:opacity-50">ذخیره تنظیم قیمت</button></div>
                        </div>
                        @else
                        <p class="num mt-3 text-sm text-muted">قیمت دستی: <span x-text="row.display || '—'"></span> <span x-text="row.unitLabel"></span> · <span x-text="row.manualFallbackEnabled ? 'پشتیبان روشن' : 'پشتیبان خاموش'"></span></p>
                        @endcan

                        {{-- ── Editor (faghat yek symbol dar har lahze) ── --}}
                        @can('pricing.manage')
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
                        @endcan
                    </li>
                </template>
            </ul>
        </section>

        <p class="mt-3 text-xs text-muted-2">
            ترتیب پیش‌فرض همان ستون «ترتیب» در
            <a href="{{ route('panel.providers.index') }}" wire:navigate class="font-medium text-brand-2 hover:underline">منابع قیمت</a>
            است.
        </p>
    </div>
</x-layouts.panel>
