{{-- Safhe ye yek instrument (`/price/{symbol}`، P10-13): SSR baraye SEO
     (breadcrumb + JSON-LD az `InstrumentSeoQuery`)، chart e inline
     (`instrumentChart`) va poll e zende ye radif (`instrumentLive`). Gheymat
     ha toman (zarib az `PublicBoardQuery`). --}}
@php $p = $page; @endphp

<x-layouts.site :seo="$seo">
    <div x-data="instrumentLive({ url: @js(route('board.json')), symbol: @js($p['symbol']) })" class="container-wide py-6 sm:py-8" data-instrument-page="{{ $p['symbol'] }}">

        <nav class="text-sm text-muted" aria-label="مسیر">
            <a href="{{ route('home') }}" class="hover:text-ink" wire:navigate>خانه</a>
            <span class="mx-1.5" aria-hidden="true">/</span>
            <a href="{{ route('home') }}#{{ $p['metal'] ?? 'fx' }}" class="hover:text-ink">{{ $p['groupLabel'] }}</a>
            <span class="mx-1.5" aria-hidden="true">/</span>
            <span class="text-ink">{{ $p['label'] }}</span>
        </nav>

        {{-- ── Sar e safhe: esm + kholase ye gheymat ── --}}
        <section class="mt-4 rounded-2xl bg-surface card-p shadow-xs">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span @class([
                        'grid size-12 shrink-0 place-items-center rounded-xl',
                        'bg-gold-soft text-metal-gold' => $p['metal'] === 'xau',
                        'bg-silver-soft text-metal-silver' => $p['metal'] === 'xag',
                        'bg-copper-soft text-metal-copper' => $p['metal'] === 'xcu',
                        'bg-surface-3 text-muted' => $p['metal'] === null,
                    ])>
                        <x-icon :name="$p['icon']" class="size-6" />
                    </span>
                    <div>
                        <h1 class="text-2xl font-extrabold leading-tight text-ink sm:text-3xl">قیمت {{ $p['label'] }}</h1>
                        <p class="mt-1 text-sm text-muted"><span class="ltr inline-block">{{ $p['symbol'] }}</span> · {{ $p['groupLabel'] }} · هر {{ $p['unitLabel'] }} به {{ $p['quoteLabel'] }}</p>
                    </div>
                </div>

                <div class="text-end">
                    @if ($p['hasPrices'])
                        <p class="text-xs text-muted">میانگین منابع</p>
                        <p class="num text-3xl font-extrabold text-ink" data-stat="avg">{{ $p['avgDisplay'] }}</p>
                        <p class="mt-1 text-xs text-muted">آخرین به‌روزرسانی <span class="num">{{ $p['updatedAt'] ?? '—' }}</span></p>
                    @else
                        <p class="text-sm text-muted" data-no-prices>هنوز قیمتی برای این نماد ثبت نشده.</p>
                    @endif
                </div>
            </div>

            @if ($p['hasPrices'])
                <dl class="mt-5 grid gap-3 sm:grid-cols-4">
                    <div class="rounded-xl bg-surface-2 p-3"><dt class="text-xs text-muted">کمترین</dt><dd class="num mt-1 text-lg font-bold text-up" data-stat="min">{{ $p['minDisplay'] }}</dd></div>
                    <div class="rounded-xl bg-surface-2 p-3"><dt class="text-xs text-muted">بیشترین</dt><dd class="num mt-1 text-lg font-bold text-down" data-stat="max">{{ $p['maxDisplay'] }}</dd></div>
                    <div class="rounded-xl bg-surface-2 p-3"><dt class="text-xs text-muted">اختلاف منابع</dt><dd class="num mt-1 text-lg font-bold text-ink">{{ $p['spreadPct'] ?? '—' }}٪</dd></div>
                    <div class="rounded-xl bg-surface-2 p-3"><dt class="text-xs text-muted">تعداد منبع</dt><dd class="num mt-1 text-lg font-bold text-ink">{{ $p['count'] }}</dd></div>
                </dl>
            @endif

            <p x-show="degraded" x-cloak class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-warn-soft px-3 py-1 text-xs font-semibold text-warn"><x-icon name="alert" class="size-3.5" />مسیر جایگزین — ممکن است کمی کند باشد</p>
        </section>

        {{-- ── Chart ── --}}
        <section class="mt-4 rounded-2xl bg-surface p-2 shadow-xs md:p-4" aria-labelledby="chart-heading"
                 x-data="instrumentChart({ url: @js(route('candles.json')), symbol: @js($p['symbol']), interval: 'm15', precision: @js($p['precision']) })">
            <div class="flex flex-wrap items-center justify-between gap-2 px-2 pb-2">
                <h2 id="chart-heading" class="font-bold text-ink">نمودار {{ $p['label'] }}</h2>
                <div class="flex items-center gap-1" role="radiogroup" aria-label="بازه">
                    @foreach (\App\Modules\Pricing\Domain\Enums\Interval::labels() as $value => $label)
                        <button type="button" role="radio" @click="setInterval(@js($value))" :aria-checked="interval === @js($value)"
                                class="h-8 rounded-full px-3 text-xs font-medium transition-colors"
                                :class="interval === @js($value) ? 'bg-brand-700 text-on-brand' : 'bg-surface-2 text-ink-3 hover:bg-surface-3'">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <div class="relative">
                <div x-ref="canvas" class="ltr h-80 w-full sm:h-96" data-instrument-chart></div>
                <div x-show="loading" x-cloak class="absolute inset-0 grid place-items-center rounded-xl bg-surface/60 text-sm text-muted">در حال بارگذاری…</div>
                <div x-show="!loading && empty" x-cloak class="absolute inset-0 grid place-items-center text-sm text-muted" data-chart-empty>برای این نماد و بازه هنوز داده‌ای نیست.</div>
            </div>
            <p class="px-2 pt-2 text-xs text-muted">منبع نمودار: <span class="font-medium text-ink" x-text="provider || '—'"></span> · قیمت‌ها با اسپرد همان منبع</p>
        </section>

        {{-- ── Moghayese ye manba' ha ── --}}
        <section class="mt-4 overflow-hidden rounded-2xl bg-surface shadow-xs" aria-labelledby="providers-heading">
            <div class="flex items-center justify-between gap-3 border-b border-line p-4">
                <h2 id="providers-heading" class="font-bold text-ink">قیمت {{ $p['label'] }} در منابع مختلف</h2>
                <span class="text-xs text-muted">{{ $p['quoteLabel'] }} · هر ۱۵ ثانیه</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[32rem] text-sm">
                    <thead class="text-xs text-muted">
                        <tr><th class="px-4 py-3 text-start">منبع</th><th class="px-4 py-3 text-center">قیمت</th><th class="px-4 py-3 text-center">تغییر</th><th class="px-4 py-3 text-center">وضعیت</th></tr>
                    </thead>
                    <tbody class="divide-y divide-line-2">
                        @forelse ($p['cells'] as $cell)
                            <tr data-provider-row="{{ $cell['key'] }}" data-provider-cell="{{ $cell['key'] }}">
                                <td class="px-4 py-3 font-medium text-ink">{{ $cell['name'] }}@if ($cell['isDerived'])<span class="text-muted-3" title="نرخ محاسبه‌شده">*</span>@endif</td>
                                <td class="px-4 py-3 text-center"><span data-price @class(['num font-semibold text-ink', 'text-muted-2' => $cell['isStale']])>{{ $cell['sellDisplay'] }}</span></td>
                                <td class="px-4 py-3 text-center"><span data-change class="num text-xs {{ $cell['direction'] === 'up' ? 'text-up' : ($cell['direction'] === 'down' ? 'text-down' : 'text-muted-2') }}">@if ($cell['changePercent'] != 0){{ $cell['direction'] === 'up' ? '▲' : ($cell['direction'] === 'down' ? '▼' : '') }}{{ number_format(abs($cell['changePercent']), 2) }}٪@endif</span></td>
                                <td class="px-4 py-3 text-center">@if ($cell['isStale'])<x-panel.pill tone="warn">کهنه</x-panel.pill>@else<x-panel.pill tone="ok" dot>زنده</x-panel.pill>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-muted">هنوز قیمتی ثبت نشده — به‌زودی.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="px-4 py-3 text-xs text-muted-2">قیمت‌ها به {{ $p['quoteLabel'] }} است. * = نرخ خرید/فروش محاسبه‌شده از اسپرد، نه نرخ اعلامی منبع.</p>
        </section>

        {{-- ── Instrument haye ham-gruh ── --}}
        @if ($p['related'] !== [])
            <section class="mt-4" aria-labelledby="related-heading">
                <h2 id="related-heading" class="mb-2 text-sm font-bold text-ink">نمادهای مرتبط</h2>
                <div class="flex flex-wrap gap-2" data-related>
                    @foreach ($p['related'] as $item)
                        <a href="{{ route('instrument', $item['symbol']) }}" wire:navigate class="inline-flex h-9 items-center rounded-full border border-line bg-surface px-3 text-sm text-ink hover:border-line-3 hover:text-brand-2">{{ $item['label'] }}</a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.site>
