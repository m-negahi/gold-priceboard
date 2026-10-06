{{--
    Board e TV (P5-03) — baraye namayeshgar e maghaze: full-screen، zamine ye
    tire (contrast e bala az fasele)، font e bozorg، flash e sabz/ghermez.
    Bedun e chrome e site (header/footer) va `noindex` (mohtava ye tekrari).
    Gheymat = gheymat e MO'ASSER (hamun feed e API)، na miangin.
--}}
<x-layouts.site title="تابلوی تلویزیونی قیمت" :chrome="false" body-class="bg-surface-dark text-ink-invert">
    @push('head')
        <meta name="robots" content="noindex, nofollow">
    @endpush

    <div x-data="tvBoard({ url: @js(route('tv.json')), interval: 15000 })"
         data-etag="W/{{ $tv['etag'] }}" data-updated-at="{{ $tv['updatedAt'] }}"
         class="flex min-h-dvh flex-col gap-4 p-4 sm:gap-6 sm:p-6 xl:p-8">

        {{-- Navar e bala: brand + saat + vaziat + full-screen. --}}
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-xl bg-brand-grad text-on-brand">
                    <x-icon name="coins" class="size-6" />
                </span>
                <div>
                    <p class="text-tv-label font-extrabold">تابلو قیمت</p>
                    <p class="flex items-center gap-2 text-tv-meta text-ink-invert/60">
                        <span class="relative flex size-2.5">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-up-on-dark opacity-60"></span>
                            <span class="relative inline-flex size-2.5 rounded-full bg-up-on-dark"></span>
                        </span>
                        زنده
                        <span x-cloak x-show="updatedLabel">— آخرین تغییر <span class="num" x-text="updatedLabel"></span></span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="text-end">
                    <p data-tv-clock class="num text-tv-label font-bold" x-text="clock"></p>
                    <p class="text-tv-meta text-ink-invert/60" x-text="today"></p>
                </div>
                <button type="button" x-cloak x-show="canFullscreen" @click="toggleFullscreen()" data-tv-fullscreen
                        class="grid size-11 place-items-center rounded-xl bg-surface-dark-2 text-ink-invert/80 hover:text-ink-invert"
                        :aria-label="fullscreen ? 'خروج از تمام‌صفحه' : 'تمام‌صفحه'">
                    <x-icon name="maximize" class="size-5" x-show="!fullscreen" />
                    <x-icon name="minimize" class="size-5" x-show="fullscreen" x-cloak />
                </button>
            </div>
        </header>

        <div x-cloak x-show="degraded || alarm"
             class="flex items-center gap-2 rounded-xl bg-warn-soft px-4 py-2 text-tv-meta font-semibold text-warn">
            <x-icon name="alert" class="size-5" />
            برخی قیمت‌ها از مسیر جایگزین نمایش داده می‌شوند — ممکن است کمی قدیمی باشند.
        </div>

        @if ($tv['rows'] === [])
            <div class="grid flex-1 place-items-center rounded-2xl bg-surface-dark-2 text-tv-label text-ink-invert/60">
                هنوز قیمتی ثبت نشده — به‌زودی.
            </div>
        @else
            <section class="grid flex-1 auto-rows-fr grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-3 2xl:grid-cols-4">
                @foreach ($tv['rows'] as $row)
                    @php $metal = $row['metal']; @endphp
                    <article data-tv-tile="{{ $row['symbol'] }}" data-value="{{ $row['value'] }}"
                             @if ($row['isStale']) data-stale @endif
                             class="@container flex flex-col justify-between gap-2 rounded-2xl bg-surface-dark-2 p-4 sm:p-5">
                        <div class="flex items-center gap-3">
                            <span @class([
                                'grid size-10 shrink-0 place-items-center rounded-xl',
                                'bg-gold-soft text-metal-gold' => $metal === 'xau',
                                'bg-silver-soft text-metal-silver' => $metal === 'xag',
                                'bg-copper-soft text-metal-copper' => $metal === 'xcu',
                                'bg-surface-3 text-muted' => $metal === null,
                            ])>
                                <x-icon :name="$row['icon']" class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <h2 class="truncate text-tv-label font-bold">{{ $row['label'] }}</h2>
                                <p class="text-tv-meta text-ink-invert/50">{{ $row['quoteLabel'] }} / {{ $row['unitLabel'] }}</p>
                            </div>
                        </div>

                        <p data-tv-price @class([
                            'num ltr text-end text-tv-price font-extrabold tracking-tight',
                            'text-ink-invert/40' => $row['isStale'],
                        ])>{{ $row['display'] }}</p>

                        <div class="flex items-center justify-between gap-2 text-tv-meta">
                            <span data-tv-change @class([
                                'num inline-flex items-center gap-1 font-semibold',
                                'text-up-on-dark' => $row['direction'] === 'up',
                                'text-down-on-dark' => $row['direction'] === 'down',
                                'text-ink-invert/50' => $row['direction'] === 'flat',
                            ])>{{ $row['direction'] === 'up' ? '▲' : ($row['direction'] === 'down' ? '▼' : '') }} {{ $row['change'] }}٪</span>
                            <span data-tv-stale @class(['rounded-full bg-stale-soft px-2.5 py-0.5 text-stale font-semibold', 'hidden' => ! $row['isStale']])>قدیمی</span>
                        </div>
                    </article>
                @endforeach
            </section>
        @endif
    </div>
</x-layouts.site>
