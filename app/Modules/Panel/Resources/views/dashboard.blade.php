{{-- Mizkar (P3-05): kholase ye zende ye system az BoardQuery/ProviderHealthQuery.
     Hame ye adad ha server-side hesab shodan (DashboardQuery) — Blade faghat
     namayesh e (ghanun e laye ha). --}}

@php
    // Format e gheymat: divisor e namayesh (rial/dollar) + raghame Latin
    // ba joda-konande. FAGHAT namayesh — hich mohasebe-i.
    $money = static fn (array $cell): string => number_format($cell['sell'] / $cell['divisor'], $cell['precision']);
    $age = static function (int $seconds): string {
        if ($seconds < 60) {
            return $seconds.' ثانیه';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60).' دقیقه';
        }

        return intdiv($seconds, 3600).' ساعت';
    };
@endphp

<x-layouts.panel title="میزکار" current="dashboard" :breadcrumbs="['پنل', 'میزکار']">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink md:text-3xl">میزکار</h1>
            <p class="mt-1 text-sm text-muted">نمای زنده‌ی موتور قیمت — هر ۱۵ ثانیه یک tick.</p>
        </div>

        <x-panel.help key="dashboard" title="راهنمای میزکار">
            <h3>این صفحه چیست؟</h3>
            <p>خلاصه‌ی سلامت موتور قیمت: چند قیمت زنده داریم، کدام منبع سالم است و جدول مقایسه‌ی قیمت فروش هر منبع.</p>
            <dl>
                <dt>کهنه</dt>
                <dd>قیمتی که از سقف تازگیِ نمادش قدیمی‌تر است؛ نمایش داده می‌شود ولی با نشان کهنگی.</dd>
                <dt>محاسبه‌شده</dt>
                <dd>نرخ خرید/فروشی که از اسپرد ساخته‌ایم، نه نرخ واقعی منبع (ستاره‌دار).</dd>
            </dl>
        </x-panel.help>
    </div>

    {{-- ── Stat card ha (Donezo: avvali featured) ───────────────────── --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-panel.stat-card
            featured
            icon="pulse"
            label="قیمت زنده"
            :value="number_format($stats['liveRows'])"
            :pill="$stats['degraded'] ? ['text' => 'مسیر جایگزین', 'tone' => 'warn'] : ['text' => 'مسیر داغ', 'tone' => 'ok']"
            hint="ردیف روی تابلو" />

        <x-panel.stat-card
            icon="feed"
            label="منابع فعال"
            :value="number_format($stats['activeProviders'])"
            :pill="['text' => $stats['healthyProviders'].' سالم', 'tone' => $stats['healthyProviders'] === $stats['activeProviders'] ? 'ok' : 'warn']" />

        <x-panel.stat-card
            icon="clock"
            label="قیمت کهنه"
            :value="number_format($stats['staleRows'])"
            :pill="$stats['staleRows'] > 0 ? ['text' => 'با نشان کهنگی', 'tone' => 'warn'] : ['text' => 'همه تازه', 'tone' => 'ok']"
            hint="بازار بسته = طبیعی" />

        <x-panel.stat-card
            icon="refresh"
            label="نسخه تابلو"
            :value="number_format($stats['boardVersion'])"
            :hint="'اسپرد نسخه '.$stats['spreadVersion']" />
    </div>

    <div class="mt-6 grid gap-4 xl:grid-cols-3">
        {{-- ── Salamat e manba' ha ──────────────────────────────────── --}}
        <section class="rounded-2xl bg-surface card-p shadow-xs xl:col-span-2" aria-labelledby="health-title">
            <h2 id="health-title" class="text-base font-bold text-ink">سلامت منابع</h2>

            <ul class="mt-4 divide-y divide-line-2">
                @forelse ($providers as $provider)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-2 font-medium text-ink">
                                {{ $provider['name'] }}
                                @unless ($provider['isActive'])
                                    <x-panel.pill tone="flat">خاموش</x-panel.pill>
                                @endunless
                                @if ($provider['retried'])
                                    <x-panel.pill tone="warn">با تلاش دوباره</x-panel.pill>
                                @endif
                            </p>
                            @if ($provider['error'])
                                <p class="ltr mt-1 truncate text-xs text-down" title="{{ $provider['error'] }}">{{ $provider['error'] }}</p>
                            @endif
                        </div>

                        <div class="num flex items-center gap-4 text-xs text-muted">
                            <span title="تاخیر آخرین دریافت">{{ number_format($provider['latencyMs']) }}ms</span>
                            <span title="تعداد نماد">{{ $provider['symbolCount'] }} نماد</span>
                            @if ($provider['rejectedCount'] > 0)
                                <span class="text-warn" title="رد شده">{{ $provider['rejectedCount'] }} رد</span>
                            @endif
                        </div>

                        <x-panel.health-badge :state="$provider['state']" />
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-muted">هنوز منبعی ثبت نشده است.</li>
                @endforelse
            </ul>
        </section>

        {{-- ── Gauge ye salamat ─────────────────────────────────────── --}}
        <section class="flex flex-col items-center justify-center rounded-2xl bg-surface card-p shadow-xs" aria-labelledby="gauge-title">
            <h2 id="gauge-title" class="sr-only">درصد سلامت منابع فعال</h2>
            <x-panel.gauge
                :percent="$healthPercent"
                label="منابع فعالِ سالم"
                :legend="[
                    ['label' => 'سالم', 'tone' => 'ok'],
                    ['label' => 'نیازمند بررسی', 'tone' => 'warn'],
                    ['label' => 'قطع', 'tone' => 'down'],
                ]" />
        </section>
    </div>

    {{-- ── Jadval e moghayese ───────────────────────────────────────── --}}
    <section class="mt-4 overflow-hidden rounded-2xl bg-surface shadow-xs" aria-labelledby="board-title">
        <div class="flex items-center justify-between gap-3 card-p pb-0">
            <h2 id="board-title" class="text-base font-bold text-ink">قیمت فروش منابع <span class="text-sm font-normal text-muted">(ریال / دلار)</span></h2>
            @if ($stats['degraded'])
                <x-panel.pill tone="warn" dot>مسیر جایگزین — کندتر ولی درست</x-panel.pill>
            @endif
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="w-full min-w-[36rem] text-sm">
                <thead>
                    <tr class="border-b border-line text-xs font-semibold text-muted">
                        <th class="px-5 py-2.5 text-start font-semibold">نماد</th>
                        @foreach ($table['providers'] as $providerKey)
                            <th class="ltr px-5 py-2.5 text-end font-semibold">{{ $providerKey }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-2">
                    @forelse ($table['rows'] as $row)
                        <tr>
                            <td class="px-5 py-2.5 font-medium text-ink">{{ $row['label'] }}</td>
                            @foreach ($table['providers'] as $providerKey)
                                @php $cell = $row['cells'][$providerKey] ?? null; @endphp
                                <td class="px-5 py-2.5 text-end">
                                    @if ($cell === null)
                                        <span class="text-muted-3">—</span>
                                    @else
                                        <span @class([
                                            'num font-semibold',
                                            'text-up' => $cell['direction'] === 'up',
                                            'text-down' => $cell['direction'] === 'down',
                                            'text-ink' => $cell['direction'] === 'flat',
                                        ])>
                                            {{ $money($cell) }}@if ($cell['isDerived'])<span class="text-muted-3" title="نرخ فروش محاسبه‌شده">*</span>@endif
                                        </span>
                                        <span class="block text-[0.6875rem] {{ $cell['isStale'] ? 'text-warn' : 'text-muted-3' }}">
                                            {{ $cell['isStale'] ? 'کهنه — ' : '' }}{{ $age($cell['ageSeconds']) }} پیش
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($table['providers']) + 1 }}" class="px-5 py-10 text-center text-muted">
                                هنوز قیمتی ثبت نشده — منبع‌ها را فعال کنید و منتظر اولین tick بمانید.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="card-p pt-3 text-xs text-muted-2">* نرخ خرید/فروش محاسبه‌شده از اسپرد است، نه نرخ اعلامی منبع.</p>
    </section>
</x-layouts.panel>
