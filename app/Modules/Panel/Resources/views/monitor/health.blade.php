{{-- Salamat e kamel e manba' ha (P3-08). --}}

@php
    $when = static function (?string $iso): string {
        if ($iso === null) {
            return '—';
        }

        return \App\Modules\Common\Helpers\Jalali::format(\Carbon\CarbonImmutable::parse($iso), 'H:i:s') ?? '—';
    };
@endphp

<x-layouts.panel title="سلامت منابع" current="health" :breadcrumbs="['پنل', 'سلامت منابع']">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink md:text-3xl">سلامت منابع</h1>
            <p class="mt-1 text-sm text-muted">وضعیت از پایگاه‌داده (منبع حقیقت شمارش) + تازه‌ترین tick از حافظه داغ.</p>
        </div>
        <a href="{{ route('panel.monitor.ticks') }}"
           class="inline-flex h-10 items-center gap-2 rounded-xl border border-line px-4 text-sm font-medium text-ink hover:bg-surface-2">
            <x-icon name="eye" class="size-4" />
            پایش زنده
        </a>
    </div>

    <section class="mt-6 overflow-hidden rounded-2xl bg-surface shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[52rem] text-sm">
                <thead>
                    <tr class="border-b border-line text-xs font-semibold text-muted">
                        <th class="px-5 py-3 text-start font-semibold">منبع</th>
                        <th class="px-5 py-3 text-start font-semibold">سلامت</th>
                        <th class="num px-5 py-3 text-start font-semibold">تاخیر</th>
                        <th class="num px-5 py-3 text-start font-semibold">نماد / رد</th>
                        <th class="num px-5 py-3 text-start font-semibold">شکست پیاپی</th>
                        <th class="px-5 py-3 text-start font-semibold">آخرین موفق</th>
                        <th class="px-5 py-3 text-start font-semibold">آخرین خطا</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-2">
                    @forelse ($providers as $provider)
                        <tr>
                            <td class="px-5 py-3">
                                <p class="flex items-center gap-2 font-medium text-ink">
                                    {{ $provider['name'] }}
                                    @unless ($provider['isActive'])
                                        <x-panel.pill tone="flat">خاموش</x-panel.pill>
                                    @endunless
                                    @if ($provider['retried'])
                                        <x-panel.pill tone="warn">تلاش دوباره</x-panel.pill>
                                    @endif
                                </p>
                                <p class="ltr text-xs text-muted-3">{{ $provider['key'] }}</p>
                            </td>
                            <td class="px-5 py-3"><x-panel.health-badge :state="$provider['state']" /></td>
                            <td class="num px-5 py-3 text-xs text-muted">{{ number_format($provider['latencyMs']) }}ms</td>
                            <td class="num px-5 py-3 text-xs text-muted">
                                {{ $provider['symbolCount'] }}
                                @if ($provider['rejectedCount'] > 0)
                                    / <span class="text-warn">{{ $provider['rejectedCount'] }}</span>
                                @else
                                    / 0
                                @endif
                            </td>
                            <td class="num px-5 py-3 text-xs {{ $provider['consecutiveFailures'] > 0 ? 'text-down font-semibold' : 'text-muted' }}">
                                {{ $provider['consecutiveFailures'] }}
                            </td>
                            <td class="num px-5 py-3 text-xs text-muted">{{ $when($provider['lastSuccessAt']) }}</td>
                            <td class="px-5 py-3">
                                @if ($provider['error'])
                                    <span class="ltr block max-w-64 truncate text-xs text-down" title="{{ $provider['error'] }}">{{ $provider['error'] }}</span>
                                @else
                                    <span class="text-muted-3">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-muted">هنوز منبعی ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.panel>
