{{-- Matrix e provider × symbol (P3-10، غani-shode): gheymat e foroosh +
     taghir + sen + moghayese ye beyn-provider (arzun/geran tarin + ekhtelaf).
     Bar khalaf e tablo، khune haye MAKHFI ham dide mishan. --}}

@php
    $age = static function (?int $seconds): string {
        if ($seconds === null) {
            return '—';
        }
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60).'m';
        }
        if ($seconds < 86400) {
            return intdiv($seconds, 3600).'h';
        }

        return intdiv($seconds, 86400).'d';
    };
    $money = static fn (float $sell, int $divisor, int $precision): string => number_format($sell / max(1, $divisor), $precision);
@endphp

<x-layouts.panel title="ماتریس نمادها" current="matrix" :breadcrumbs="['پنل', 'ماتریس نمادها']">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink md:text-3xl">ماتریس نمادها</h1>
            <p class="mt-1 text-sm text-muted">قیمت فروش هر منبع برای هر نماد، کنار هم — ارزان‌ترین سبز، گران‌ترین قرمز.</p>
        </div>

        <x-panel.help key="matrix" title="راهنمای ماتریس">
            <h3>این صفحه چه می‌گوید؟</h3>
            <p>هر خانه قیمت فروشِ یک منبع برای یک نماد است (با همان اسپردِ تابلو). زیر هر قیمت: درصد تغییر، سن داده، و نشان‌ها.</p>
            <dl>
                <dt>ارزان‌ترین / گران‌ترین</dt><dd>در هر ردیف، کمترین و بیشترین قیمت بین منابع رنگ می‌گیرند و اختلافشان (٪) کنار نماد می‌آید — این همان ارزش «مقایسه» است.</dd>
                <dt>کهنه / مخفی</dt><dd>قدیمی‌تر از سقف تازگی = کهنه؛ قدیمی‌تر از «تازگی + مخفی‌بعد‌از» = از تابلو مخفی ولی این‌جا دیده می‌شود.</dd>
                <dt>* محاسبه‌شده</dt><dd>نرخ از اسپرد ساخته شده، نه نرخ اعلامی منبع.</dd>
            </dl>
        </x-panel.help>
    </div>

    <div class="mt-5 flex flex-wrap items-center gap-2 text-xs">
        <x-panel.pill tone="up" dot>ارزان‌ترین</x-panel.pill>
        <x-panel.pill tone="down" dot>گران‌ترین</x-panel.pill>
        <x-panel.pill tone="warn" dot>کهنه</x-panel.pill>
        <x-panel.pill tone="flat" dot>مخفی از تابلو</x-panel.pill>
        <span class="text-muted-2">* = محاسبه‌شده</span>
    </div>

    <section class="mt-4 overflow-hidden rounded-2xl bg-surface shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[44rem] text-sm">
                <thead>
                    <tr class="border-b border-line text-xs font-semibold text-muted">
                        <th class="px-5 py-3 text-start font-semibold">نماد</th>
                        <th class="px-5 py-3 text-start font-semibold">اختلاف منابع</th>
                        @foreach ($providers as $providerKey)
                            <th class="ltr px-5 py-3 text-center font-semibold">{{ $providerKey }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-2">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="min-w-0">
                                        <p class="font-medium text-ink">{{ $row['label'] }}</p>
                                        <p class="ltr text-xs text-muted-3">{{ $row['symbol'] }}</p>
                                    </div>
                                    {{-- P5-08 — chart e hamun symbol. --}}
                                    <a href="{{ route('panel.charts', ['symbol' => $row['symbol']]) }}"
                                       aria-label="نمودار {{ $row['label'] }}"
                                       class="grid size-9 shrink-0 place-items-center rounded-lg text-muted hover:bg-surface-2 hover:text-brand-2">
                                        <x-icon name="chart" class="size-4" />
                                    </a>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                @if ($row['spreadPct'] !== null)
                                    <span class="num text-xs font-semibold {{ $row['spreadPct'] > 1 ? 'text-warn' : 'text-muted' }}">{{ number_format($row['spreadPct'], 2) }}٪</span>
                                @else
                                    <span class="text-muted-3">—</span>
                                @endif
                            </td>
                            @foreach ($providers as $providerKey)
                                @php $cell = $row['cells'][$providerKey] ?? null; @endphp
                                <td class="px-5 py-3 text-center align-top">
                                    @if ($cell === null)
                                        <span class="text-muted-3">—</span>
                                    @else
                                        @php
                                            $isCheap = $row['cheapest'] === $providerKey;
                                            $isDear = $row['dearest'] === $providerKey;
                                        @endphp
                                        <div class="inline-flex flex-col items-center gap-0.5">
                                            <span @class([
                                                'num font-semibold',
                                                'text-up' => $isCheap,
                                                'text-down' => $isDear,
                                                'text-ink' => ! $isCheap && ! $isDear,
                                            ])>
                                                {{ $cell['sell'] !== null ? $money($cell['sell'], $row['divisor'], $row['precision']) : '—' }}@if ($cell['isDerived'])<span class="text-muted-3" title="محاسبه‌شده">*</span>@endif
                                            </span>

                                            <span class="flex items-center gap-1.5 text-[0.6875rem]">
                                                @if ($cell['changePercent'] != 0)
                                                    <span class="num {{ $cell['direction'] === 'up' ? 'text-up' : ($cell['direction'] === 'down' ? 'text-down' : 'text-muted-2') }}">
                                                        {{ $cell['direction'] === 'up' ? '▲' : ($cell['direction'] === 'down' ? '▼' : '') }}{{ number_format(abs($cell['changePercent']), 2) }}٪
                                                    </span>
                                                @endif
                                                <span class="num {{ $cell['isHidden'] ? 'text-muted-3' : ($cell['isStale'] ? 'text-warn' : 'text-muted-2') }}">
                                                    {{ $cell['isHidden'] ? 'مخفی ' : ($cell['isStale'] ? 'کهنه ' : '') }}{{ $age($cell['ageSeconds']) }}
                                                </span>
                                            </span>
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($providers) + 2 }}" class="px-5 py-10 text-center text-muted">هنوز قیمتی ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <p class="mt-3 text-xs text-muted-2">نمادهای ریالی به ریال و جهانی‌ها به دلار نمایش داده می‌شوند. «مخفی» یعنی سن از «تازگی + مخفی‌بعد‌از» گذشته؛ داده هنوز هست، فقط روی تابلو نمی‌آید.</p>
</x-layouts.panel>
