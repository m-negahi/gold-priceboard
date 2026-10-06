@php
    $b = $board;
    $money = static fn (?float $major, int $precision): string => $major === null ? '—' : number_format($major, $precision);
    // Gruh-bandi ba felez baraye sar-tak haye bakhsh. (match na array —
    // key e `null` tu array literal be `''` tabdil mishe va lookup kharab.)
    $metalLabel = static fn (?string $m): string => match ($m) {
        'gold' => 'طلا و سکه',
        'silver' => 'نقره',
        'copper' => 'مس',
        default => 'ارز',
    };
@endphp

<x-layouts.site title="قیمت لحظه‌ای طلا، سکه و نقره"
                description="مقایسه‌ی لحظه‌ای قیمت طلا، سکه، نقره و دلار بین چند منبع معتبر — با کمترین، بیشترین و میانگین هر نماد.">

    <section class="border-b border-line bg-surface">
        <div class="container-wide py-10 sm:py-14">
            <h1 class="max-w-2xl text-2xl font-extrabold leading-tight text-heading sm:text-4xl">
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
                <h2 class="mb-2 mt-6 flex items-center gap-2 text-sm font-bold text-heading first:mt-0">
                    <span @class([
                        'size-2 rounded-full',
                        'bg-metal-gold' => $metal === 'gold',
                        'bg-metal-silver' => $metal === 'silver',
                        'bg-metal-copper' => $metal === 'copper',
                        'bg-ink-300' => $metal === null,
                    ])></span>
                    {{ $metalLabel($metal) }}
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
                                                'bg-gold-soft text-metal-gold' => $metal === 'gold',
                                                'bg-silver-soft text-metal-silver' => $metal === 'silver',
                                                'bg-copper-soft text-metal-copper' => $metal === 'copper',
                                                'bg-surface-3 text-muted' => $metal === null,
                                            ])>
                                                <x-icon :name="$row['icon']" class="size-4" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="font-medium text-ink">{{ $row['label'] }}</p>
                                                <p class="ltr text-xs text-muted-3">{{ $row['symbol'] }}</p>
                                            </div>
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
                                                        <span data-price @class(['num font-semibold text-ink', 'text-muted-2' => $cell['isStale']])>{{ $money($cell['sell'] / $row['divisor'], $row['precision']) }}</span>
                                                        @if ($cell['isDerived'])<span class="text-muted-3" title="نرخ محاسبه‌شده">*</span>@endif
                                                    </span>
                                                    <span data-change class="num text-xs {{ $cell['direction'] === 'up' ? 'text-up' : ($cell['direction'] === 'down' ? 'text-down' : 'text-muted-2') }}">
                                                        @if ($cell['changePercent'] != 0){{ $cell['direction'] === 'up' ? '▲' : ($cell['direction'] === 'down' ? '▼' : '') }}{{ number_format(abs($cell['changePercent']), 2) }}٪@endif
                                                    </span>
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach

                                    <td class="num px-4 py-3 text-center font-semibold text-up" data-stat="{{ $row['symbol'] }}:min">{{ $money($row['min'], $row['precision']) }}</td>
                                    <td class="num px-4 py-3 text-center font-semibold text-down" data-stat="{{ $row['symbol'] }}:max">{{ $money($row['max'], $row['precision']) }}</td>
                                    <td class="num px-4 py-3 text-center font-medium text-muted" data-stat="{{ $row['symbol'] }}:avg">{{ $money($row['avg'], $row['precision']) }}</td>
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
</x-layouts.site>
