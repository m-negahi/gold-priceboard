{{-- Matrix e provider × symbol (P3-10): sen e har khune + marker e derived.
     Bar khalaf e tablo، khune haye MAKHFI ham dide mishan (hadaf e safhe
     javab e «chera ru tablo nist» ast). --}}

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

        return intdiv($seconds, 3600).'h';
    };
@endphp

<x-layouts.panel title="ماتریس نمادها" current="matrix" :breadcrumbs="['پنل', 'ماتریس نمادها']">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink md:text-3xl">ماتریس نمادها</h1>
            <p class="mt-1 text-sm text-muted">سن هر خانه؛ خانه‌ای که از تابلو مخفی شده هم این‌جا دیده می‌شود.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <x-panel.pill tone="ok" dot>تازه</x-panel.pill>
            <x-panel.pill tone="warn" dot>کهنه</x-panel.pill>
            <x-panel.pill tone="down" dot>مخفی از تابلو</x-panel.pill>
            <span class="text-muted-2">* = خرید/فروش محاسبه‌شده</span>
        </div>
    </div>

    <section class="mt-6 overflow-hidden rounded-2xl bg-surface shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[36rem] text-sm">
                <thead>
                    <tr class="border-b border-line text-xs font-semibold text-muted">
                        <th class="px-5 py-3 text-start font-semibold">نماد</th>
                        @foreach ($providers as $providerKey)
                            <th class="ltr px-5 py-3 text-center font-semibold">{{ $providerKey }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-2">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink">{{ $row['label'] }}</p>
                                <p class="ltr text-xs text-muted-3">{{ $row['symbol'] }}</p>
                            </td>
                            @foreach ($providers as $providerKey)
                                @php $cell = $row['cells'][$providerKey] ?? null; @endphp
                                <td class="px-5 py-3 text-center">
                                    @if ($cell === null)
                                        <span class="text-muted-3">—</span>
                                    @else
                                        @php
                                            $tone = $cell['isHidden'] ? 'down' : ($cell['isStale'] ? 'warn' : 'ok');
                                        @endphp
                                        <x-panel.pill :tone="$tone" dot>
                                            <span class="num">{{ $age($cell['ageSeconds']) }}</span>
                                            @if (count($cell['sides']) > 1)
                                                <span class="ltr text-[0.625rem] opacity-70">bid/ask</span>
                                            @endif
                                            @if ($cell['isDerived'])
                                                <span title="نرخ خرید/فروش محاسبه‌شده">*</span>
                                            @endif
                                        </x-panel.pill>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($providers) + 1 }}" class="px-5 py-10 text-center text-muted">هنوز قیمتی ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <p class="mt-3 text-xs text-muted-2">«مخفی از تابلو» یعنی سن از «تازگی + مخفی بعد از» گذشته؛ داده هنوز هست، فقط نمایش داده نمی‌شود.</p>
</x-layouts.panel>
