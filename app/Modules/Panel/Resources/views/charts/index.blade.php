{{-- Chart e panel e admin (P5-08): candle ya khati، az 5 daghighe ta
     salane. Haftegi/mahane/salane server-side ba taghvim e Jalali (hafte az
     shanbe، mah/sal e shamsi) az candle e ruzane sakhte mishan. --}}

<x-layouts.panel title="نمودار قیمت" current="charts" :breadcrumbs="['پنل', 'نمودار قیمت']">
    <div x-data="adminChart({
            url: @js(route('panel.charts.data')),
            symbol: @js($symbol),
            period: @js($period),
            precisions: @js($precisions),
        })">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-ink md:text-3xl">نمودار قیمت</h1>
                <p class="mt-1 text-sm text-muted">کندل یا خطی، از ۵ دقیقه تا سالانه. هفته از شنبه و ماه/سال شمسی است.</p>
            </div>

            <x-panel.help key="charts" title="راهنمای نمودار">
                <ul>
                    <li><b>منبع خودکار</b> یعنی اولین منبعِ زنجیرهٔ اولویتِ همین نماد (صفحه اولویت منابع).</li>
                    <li>بازه‌های <b>هفتگی، ماهانه و سالانه</b> از کندل روزانه ساخته می‌شوند: باز = باز اولین روز، بسته = بسته آخرین روز، بیشینه/کمینه = بیشترین/کمترین آن بازه.</li>
                    <li>قیمت‌ها با اسپرد همان منبع نمایش داده می‌شوند — همان عددی که روی تابلو می‌بینید.</li>
                </ul>
            </x-panel.help>
        </div>

        {{-- ── Kontrol ha ── --}}
        <section class="mt-6 space-y-3 rounded-2xl bg-surface card-p shadow-xs">
            <div class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 text-sm text-ink">
                    نماد:
                    <select x-model="symbol" @change="changeSymbol()" aria-label="نماد"
                            class="h-10 min-w-44 rounded-xl border border-line-3 bg-surface px-3 text-sm focus:border-brand focus:outline-none">
                        @foreach ($instruments as $value => $label)
                            <option value="{{ $value }}" @selected($value === $symbol)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex items-center gap-2 text-sm text-ink">
                    منبع:
                    <select x-model="provider" @change="changeProvider()" aria-label="منبع"
                            class="h-10 min-w-44 rounded-xl border border-line-3 bg-surface px-3 text-sm focus:border-brand focus:outline-none">
                        <option value="">خودکار (طبق اولویت)</option>
                        @foreach ($providers as $key => $name)
                            <option value="{{ $key }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="ms-auto inline-flex rounded-xl bg-surface-2 p-1" role="radiogroup" aria-label="نوع نمودار">
                    @foreach (['candle' => 'کندل', 'line' => 'خطی'] as $value => $label)
                        <button type="button" role="radio" @click="setKind(@js($value))" :aria-checked="kind === @js($value)"
                                class="h-9 rounded-lg px-3.5 text-sm font-medium transition-colors"
                                :class="kind === @js($value) ? 'bg-surface text-ink shadow-xs' : 'text-muted hover:text-ink'">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="بازه">
                @foreach ($periods as $value => $label)
                    <button type="button" role="radio" @click="setPeriod(@js($value))" :aria-checked="period === @js($value)"
                            class="inline-flex h-9 items-center rounded-full px-3.5 text-sm font-medium transition-colors"
                            :class="period === @js($value) ? 'bg-brand-700 text-on-brand' : 'bg-surface-2 text-ink-3 hover:bg-surface-3'">{{ $label }}</button>
                @endforeach
            </div>
        </section>

        {{-- ── Chart ── --}}
        <section class="relative mt-4 rounded-2xl bg-surface p-2 shadow-xs md:p-4">
            <div class="flex items-center justify-between gap-2 px-2 pb-2 text-xs text-muted">
                <span>
                    منبع:
                    <span class="font-medium text-ink" x-text="resolvedProvider ? (@js($providers)[resolvedProvider] ?? resolvedProvider) : '—'"></span>
                </span>
                <span x-show="degraded" x-cloak class="inline-flex items-center rounded-full bg-warn-soft px-2 py-0.5 font-semibold text-warn">داده از حافظه پشتیبان — Influx پاسخ نداد</span>
            </div>

            {{-- KLineCharts arz/ertefa' e sarih mikhad. --}}
            <div x-ref="canvas" class="ltr h-[28rem] w-full md:h-[34rem]" data-admin-chart></div>

            <div x-show="loading" x-cloak class="absolute inset-0 grid place-items-center rounded-2xl bg-surface/60 text-sm text-muted">در حال بارگذاری…</div>
            <div x-show="!loading && empty" x-cloak class="absolute inset-0 grid place-items-center text-center text-sm text-muted" data-chart-empty>
                برای این نماد و بازه هنوز داده‌ای نیست.
            </div>
        </section>
    </div>
</x-layouts.panel>
