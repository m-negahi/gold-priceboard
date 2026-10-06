{{-- Form e spread e yek provider (P3-07): yek radif baraye HAR Instrument +
     wildcard. Preview SERVER-side ba haman SpreadPricer e board (JS faghat
     transport e). Hazf e override faghat ba dokme ye sarih (sync hazf
     nemikone — ghanun e Action). --}}

<x-layouts.panel :title="'اسپرد — '.$providerName" current="providers"
                 :breadcrumbs="['پنل', 'منابع قیمت', 'اسپرد '.$providerName]">
    <div x-data="spreadForm({ previewUrl: @js(route('panel.providers.spreads.preview', $providerKey)), rows: @js($rows) })">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-ink md:text-3xl">اسپرد {{ $providerName }}</h1>
                <p class="mt-1 text-sm text-muted">اسپرد روی خواندن اعمال می‌شود؛ مقدار خام همیشه دست‌نخورده می‌ماند.</p>
            </div>

            <x-panel.help key="spreads" title="راهنمای اسپرد">
                <h3>قاعده‌ها</h3>
                <ul>
                    <li>مقدار علامت‌دار است: منفی کم می‌کند.</li>
                    <li>«درصد» نسبت به قیمت خام، «مبلغ» در واحد اصلی ارز (ریال/دلار).</li>
                    <li>ردیفی که تغییر نکند، نسخه‌ای هم در دفترچه ثبت نمی‌شود.</li>
                    <li>حذف override یعنی برگشت به ردیف پیش‌فرض (*).</li>
                </ul>
            </x-panel.help>
        </div>

        @error('rows')
            <p class="mt-4 rounded-xl bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ route('panel.providers.spreads.update', $providerKey) }}" class="mt-6">
            @csrf
            @method('PUT')

            <section class="overflow-hidden rounded-2xl bg-surface shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[56rem] text-sm">
                        <thead>
                            <tr class="border-b border-line text-xs font-semibold text-muted">
                                <th class="px-4 py-3 text-start font-semibold">نماد</th>
                                <th class="px-4 py-3 text-start font-semibold">فعال</th>
                                <th class="px-4 py-3 text-start font-semibold">خرید</th>
                                <th class="px-4 py-3 text-start font-semibold">فروش</th>
                                <th class="px-4 py-3 text-start font-semibold">تازگی (ثانیه)</th>
                                <th class="px-4 py-3 text-start font-semibold">پیش‌نمایش (خام ← خرید / فروش)</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-2">
                            <template x-for="(row, i) in rows" :key="row.symbol">
                                <tr :class="row.symbol === '*' && 'bg-surface-2'">
                                    <td class="px-4 py-3">
                                        <input type="hidden" :name="`rows[${i}][symbol]`" :value="row.symbol">
                                        <p class="font-medium text-ink" x-text="row.label"></p>
                                        <p class="ltr text-xs text-muted-3" x-show="row.symbol !== '*'" x-text="row.symbol"></p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="checkbox" :name="`rows[${i}][is_enabled]`" value="1" x-model="row.is_enabled"
                                               class="size-4 accent-(--color-brand)" :aria-label="`فعال ${row.label}`">
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1.5">
                                            <select :name="`rows[${i}][buy_mode]`" x-model="row.buy_mode" @change="schedule(i)"
                                                    class="h-9 rounded-lg border border-line-3 bg-surface px-2 text-xs">
                                                <option value="percent">درصد</option>
                                                <option value="amount">مبلغ</option>
                                            </select>
                                            <input :name="`rows[${i}][buy_value]`" x-model="row.buy_value" @input="schedule(i)"
                                                   inputmode="decimal" class="num ltr h-9 w-24 rounded-lg border border-line-3 bg-surface px-2 text-left text-xs"
                                                   :aria-label="`مقدار خرید ${row.label}`">
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1.5">
                                            <select :name="`rows[${i}][sell_mode]`" x-model="row.sell_mode" @change="schedule(i)"
                                                    class="h-9 rounded-lg border border-line-3 bg-surface px-2 text-xs">
                                                <option value="percent">درصد</option>
                                                <option value="amount">مبلغ</option>
                                            </select>
                                            <input :name="`rows[${i}][sell_value]`" x-model="row.sell_value" @input="schedule(i)"
                                                   inputmode="decimal" class="num ltr h-9 w-24 rounded-lg border border-line-3 bg-surface px-2 text-left text-xs"
                                                   :aria-label="`مقدار فروش ${row.label}`">
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input :name="`rows[${i}][freshness_seconds]`" x-model="row.freshness_seconds"
                                               inputmode="numeric" placeholder="پیش‌فرض"
                                               class="num ltr h-9 w-24 rounded-lg border border-line-3 bg-surface px-2 text-left text-xs"
                                               :aria-label="`سقف تازگی ${row.label}`">
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="num flex items-center gap-1.5 text-xs" :class="row.busy ? 'opacity-50' : ''">
                                            <template x-if="row.symbol === '*' || row.raw === null">
                                                <span class="text-muted-3" x-text="row.symbol === '*' ? 'روی هر نماد جدا اعمال می‌شود' : 'قیمتی ثبت نشده'"></span>
                                            </template>
                                            <template x-if="row.symbol !== '*' && row.raw !== null">
                                                <span>
                                                    <span class="text-muted-2" x-text="fmt(row, row.raw)"></span>
                                                    <span class="text-muted-3">←</span>
                                                    <span class="font-semibold text-ink" x-text="fmt(row, row.buy)"></span>
                                                    <span class="text-muted-3">/</span>
                                                    <span class="font-semibold text-ink" x-text="fmt(row, row.sell)"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        {{-- Hazf e override: dokme SUBMIT e form e asli nist — form e
                                             jodagane nemishe tu table gozasht، pas POST ba JS e sade
                                             ham nemizanim؛ yek link e GET ham ghalat e. Rah e sade:
                                             formaction e POST e hazf ru hamin form. --}}
                                        <button type="submit" x-show="row.exists && row.symbol !== '*'" x-cloak
                                                :formaction="@js(url('dashboard/providers/'.$providerKey.'/spreads')) + '/' + row.symbol + '/remove'"
                                                class="inline-flex size-8 items-center justify-center rounded-lg text-muted hover:bg-danger-soft hover:text-danger"
                                                :aria-label="`حذف override ${row.label}`">
                                            <x-icon name="trash" class="size-4" />
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="mt-4 flex items-center justify-end gap-3">
                <a href="{{ route('panel.providers.index') }}"
                   class="inline-flex h-11 items-center rounded-xl border border-line px-4 text-sm font-medium text-ink hover:bg-surface-2">بازگشت</a>
                <button type="submit"
                        class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-on-brand shadow-(--bp-elev-brand) hover:bg-brand-2">
                    <x-icon name="check" class="size-4" />
                    ذخیره اسپردها
                </button>
            </div>
        </form>
    </div>
</x-layouts.panel>
