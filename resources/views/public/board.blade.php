@php
    $b = $board;
    // Sar-tak e gruh az `groupLabel` e query miad (Instrument::groupLabel) —
    // `metal` meghdar e enum ast (`xau`/`xag`/`xcu`)، na 'gold'.
@endphp

{{-- Title/description/JSON-LD az `HomeSeoQuery` (P5-05) — config e `seo.home` + override e `seo_meta`. --}}
<x-layouts.site :seo="$seo">

    <section class="border-b border-line bg-surface">
        <div class="container-wide py-10 sm:py-14">
            <h1 class="max-w-2xl text-2xl font-extrabold leading-tight text-ink sm:text-4xl">
                قیمت لحظه‌ای طلا، سکه و نقره — از چند منبع، کنار هم
            </h1>
            <p class="mt-3 max-w-xl text-muted">
                هر نماد را بین منابع مختلف مقایسه کنید: کمترین، بیشترین و میانگین. هر ۱۵ ثانیه به‌روز می‌شود.
            </p>
        </div>
    </section>

    <div x-data="board({ url: @js(route('board.json')), interval: 15000 })" data-etag="{{ $b['etag'] }}"
         class="container-wide py-8">

        {{-- Navar e vaziat: به‌روزرسانی + hoshdar e degraded. --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
            <div class="flex items-center gap-2 text-muted">
                <span class="relative flex size-2.5">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-up-strong opacity-60"></span>
                    <span class="relative inline-flex size-2.5 rounded-full bg-up-strong"></span>
                </span>
                زنده — هر ۱۵ ثانیه
            </div>
            <template x-if="degraded">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-warn-soft px-3 py-1 text-xs font-semibold text-warn">
                    <x-icon name="alert" class="size-3.5" />
                    مسیر جایگزین — ممکن است کمی کند باشد
                </span>
            </template>
        </div>

        @forelse ($b['rows'] as $i => $row)
            @php $metal = $row['metal']; @endphp
            @if ($loop->first || $b['rows'][$i - 1]['metal'] !== $metal)
                <h2 class="mb-2 mt-6 flex items-center gap-2 text-sm font-bold text-ink first:mt-0">
                    <span @class([
                        'size-2 rounded-full',
                        'bg-metal-gold' => $metal === 'xau',
                        'bg-metal-silver' => $metal === 'xag',
                        'bg-metal-copper' => $metal === 'xcu',
                        'bg-ink-300' => $metal === null,
                    ])></span>
                    {{ $row['groupLabel'] }}
                </h2>

                <div class="overflow-hidden rounded-2xl bg-surface shadow-xs">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[40rem] text-sm">
                            <thead>
                                <tr class="border-b border-line text-xs font-semibold text-muted">
                                    <th class="px-4 py-3 text-start font-semibold">نماد</th>
                                    @foreach ($b['providers'] as $provider)
                                        <th class="px-4 py-3 text-center font-semibold">{{ $provider['name'] }}</th>
                                    @endforeach
                                    <th class="px-4 py-3 text-center font-semibold text-up">کمترین</th>
                                    <th class="px-4 py-3 text-center font-semibold text-down">بیشترین</th>
                                    <th class="px-4 py-3 text-center font-semibold">میانگین</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line-2">
            @endif

                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <span @class([
                                                'grid size-8 shrink-0 place-items-center rounded-lg',
                                                'bg-gold-soft text-metal-gold' => $metal === 'xau',
                                                'bg-silver-soft text-metal-silver' => $metal === 'xag',
                                                'bg-copper-soft text-metal-copper' => $metal === 'xcu',
                                                'bg-surface-3 text-muted' => $metal === null,
                                            ])>
                                                <x-icon :name="$row['icon']" class="size-4" />
                                            </span>
                                            <button type="button" class="min-w-0 text-start"
                                                    @click="$dispatch('open-chart', { symbol: @js($row['symbol']), label: @js($row['label']) })">
                                                <p class="flex items-center gap-1.5 font-medium text-ink hover:text-brand-2">
                                                    {{ $row['label'] }}
                                                    <x-icon name="chart" class="size-3.5 text-muted-3" />
                                                </p>
                                                <p class="ltr text-xs text-muted-3">{{ $row['symbol'] }}</p>
                                            </button>
                                        </div>
                                    </td>

                                    @foreach ($b['providers'] as $provider)
                                        @php $cell = $row['cells'][$provider['key']] ?? null; @endphp
                                        <td class="px-4 py-3 text-center">
                                            @if ($cell === null)
                                                <span class="text-muted-3">—</span>
                                            @else
                                                <div data-cell="{{ $row['symbol'] }}:{{ $provider['key'] }}" class="inline-flex flex-col items-center gap-0.5 rounded-lg px-2 py-1">
                                                    <span class="flex items-center gap-1">
                                                        <span data-price @class(['num font-semibold text-ink', 'text-muted-2' => $cell['isStale']])>{{ $cell['sellDisplay'] }}</span>
                                                        @if ($cell['isDerived'])<span class="text-muted-3" title="نرخ محاسبه‌شده">*</span>@endif
                                                    </span>
                                                    <span data-change class="num text-xs {{ $cell['direction'] === 'up' ? 'text-up' : ($cell['direction'] === 'down' ? 'text-down' : 'text-muted-2') }}">
                                                        @if ($cell['changePercent'] != 0){{ $cell['direction'] === 'up' ? '▲' : ($cell['direction'] === 'down' ? '▼' : '') }}{{ number_format(abs($cell['changePercent']), 2) }}٪@endif
                                                    </span>
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach

                                    <td class="num px-4 py-3 text-center font-semibold text-up" data-stat="{{ $row['symbol'] }}:min">{{ $row['minDisplay'] ?? '—' }}</td>
                                    <td class="num px-4 py-3 text-center font-semibold text-down" data-stat="{{ $row['symbol'] }}:max">{{ $row['maxDisplay'] ?? '—' }}</td>
                                    <td class="num px-4 py-3 text-center font-medium text-muted" data-stat="{{ $row['symbol'] }}:avg">{{ $row['avgDisplay'] ?? '—' }}</td>
                                </tr>

            @if ($loop->last || $b['rows'][$i + 1]['metal'] !== $metal)
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @empty
            <div class="rounded-2xl bg-surface py-16 text-center text-muted shadow-xs">
                هنوز قیمتی ثبت نشده — به‌زودی.
            </div>
        @endforelse

        <p class="mt-4 text-xs text-muted-2">
            قیمت‌ها به تومان (نمادهای جهانی به دلار) است. * = نرخ خرید/فروش محاسبه‌شده از اسپرد، نه نرخ اعلامی منبع.
        </p>
    </div>

    {{-- ── FAQ (P5-05) — HAMUN `seo.faq` e JSON-LD e `FAQPage` (Google: bayad ru safhe dide beshe). --}}
    <section class="container-wide pb-12" aria-labelledby="faq-heading">
        <h2 id="faq-heading" class="mb-4 text-lg font-bold text-ink">سوالات متداول</h2>
        <div class="divide-y divide-line-2 overflow-hidden rounded-2xl bg-surface shadow-xs" data-faq>
            @foreach ($faq as $item)
                <details class="group px-5 py-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold text-ink">
                        {{ $item['question'] }}
                        <x-icon name="chevron-down" class="size-4 shrink-0 text-muted-3 transition-transform group-open:rotate-180" />
                    </summary>
                    <p class="mt-2 text-sm leading-7 text-muted">{{ $item['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- ── Modal e chart (P5-04) — KLineCharts، lazy-load ─────────────── --}}
    <div x-data="priceChart({ url: @js(route('candles.json')) })"
         @open-chart.window="show($event.detail)"
         @keydown.escape.window="close()">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4">
            <div x-show="open" x-transition.opacity.duration.150ms class="absolute inset-0 bg-ink/50" @click="close()" aria-hidden="true"></div>

            <div x-show="open" x-trap.noscroll="open"
                 x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
                 class="relative flex h-[85dvh] w-full max-w-3xl flex-col rounded-t-2xl bg-surface shadow-(--bp-elev-lg) sm:h-[32rem] sm:rounded-2xl" role="dialog" aria-modal="true">

                <header class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5">
                    <div class="min-w-0">
                        <h2 class="truncate font-bold text-ink" x-text="label"></h2>
                        <p class="ltr text-xs text-muted-3"><span x-text="symbol"></span> · <span x-text="provider"></span></p>
                    </div>
                    <button type="button" @click="close()" class="grid size-9 shrink-0 place-items-center rounded-lg text-muted hover:bg-surface-2 hover:text-ink" aria-label="بستن">
                        <x-icon name="close" class="size-5" />
                    </button>
                </header>

                {{-- Dokme haye interval. --}}
                <div class="flex items-center gap-1 border-b border-line px-3 py-2">
                    @foreach (\App\Modules\Pricing\Domain\Enums\Interval::labels() as $value => $il)
                        <button type="button" @click="setInterval(@js($value))"
                                class="rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors"
                                :class="interval === @js($value) ? 'bg-brand-soft text-brand-2' : 'text-muted hover:bg-surface-3 hover:text-ink'">
                            {{ $il }}
                        </button>
                    @endforeach
                </div>

                <div class="relative min-h-0 flex-1">
                    <div x-ref="canvas" class="size-full"></div>

                    <div x-show="loading" x-cloak class="absolute inset-0 grid place-items-center bg-surface/60">
                        <span class="size-6 animate-spin rounded-full border-2 border-line-3 border-t-brand"></span>
                    </div>
                    <div x-show="empty && !loading" x-cloak class="absolute inset-0 grid place-items-center text-sm text-muted">
                        برای این بازه هنوز داده‌ای نیست.
                    </div>
                </div>

                <template x-if="degraded">
                    <p class="border-t border-line bg-warn-soft px-5 py-2 text-xs text-warn">نمودار از آخرین داده‌ی سالم — ممکن است چند لحظه عقب باشد.</p>
                </template>
            </div>
        </div>
    </div>
</x-layouts.site>
