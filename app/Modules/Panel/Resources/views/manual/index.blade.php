{{-- Gheymat haye DASTI (fallback) — Phase 4/5. --}}

@php
    $when = static function (?string $iso): string {
        if ($iso === null) {
            return 'هرگز';
        }

        return \Carbon\CarbonImmutable::parse($iso)->setTimezone('Asia/Tehran')->format('Y/m/d H:i');
    };
@endphp

<x-layouts.panel title="قیمت دستی" current="manual" :breadcrumbs="['پنل', 'قیمت دستی']">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink md:text-3xl">قیمت دستی (پشتیبان)</h1>
            <p class="mt-1 text-sm text-muted">اگر همه‌ی منابع قطع شوند، فید روی این قیمت‌ها می‌افتد. اول از یک منبع پر کنید، بعد دستی اصلاح کنید.</p>
        </div>

        <x-panel.help key="manual" title="راهنمای قیمت دستی">
            <h3>چطور کار می‌کند؟</h3>
            <ul>
                <li>فید فروشی به ترتیب اولویتِ منابع قیمت می‌دهد؛ منبع «دستی» آخرین اولویت است.</li>
                <li>اگر همه‌ی منابعِ بالاتر کهنه/قطع شوند، قیمت از «دستی» سرو می‌شود و <b>هشدار</b> روشن می‌شود.</li>
                <li>«پر کردن از منبع» آخرین قیمت‌های آن منبع را این‌جا کپی می‌کند تا از صفر شروع نکنید.</li>
                <li>قیمت دستی که بیش از ۲۴ ساعت به‌روز نشده باشد، هشدار می‌گیرد — یعنی باید تازه‌اش کنید.</li>
            </ul>
        </x-panel.help>
    </div>

    {{-- ── Vaziat e feed e mo'asser (alarm) ─────────────────────────── --}}
    <div class="mt-5 rounded-2xl card-p shadow-xs {{ $effective['alarm'] ? 'bg-warn-soft' : 'bg-surface' }}">
        <div class="flex flex-wrap items-center gap-3">
            @if ($effective['alarm'])
                <x-panel.pill tone="warn" dot>هشدار</x-panel.pill>
                <p class="text-sm font-medium text-warn">
                    {{ $effective['fallbackCount'] }} نماد از قیمت پشتیبان (دستی/کهنه) سرو می‌شوند — منابع اصلی را بررسی کنید.
                </p>
            @else
                <x-panel.pill tone="ok" dot>سالم</x-panel.pill>
                <p class="text-sm text-muted">همه‌ی نمادها از منابع تازه سرو می‌شوند؛ فید روی پشتیبان نیفتاده.</p>
            @endif
        </div>
    </div>

    {{-- ── Seed az yek source ──────────────────────────────────────── --}}
    <form method="POST" action="{{ route('panel.manual.seed') }}" class="mt-5 flex flex-wrap items-end gap-3 rounded-2xl bg-surface card-p shadow-xs">
        @csrf
        <div class="min-w-48 flex-1">
            <label class="mb-1.5 block text-sm font-medium">پر کردن از منبع</label>
            <x-form.select name="provider" :options="$seedSources" placeholder="یک منبع انتخاب کنید" />
        </div>
        <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand shadow-(--bp-elev-brand) hover:bg-brand-2">
            <x-icon name="download" class="size-4" />
            پر کن
        </button>
        <p class="w-full text-xs text-muted-2 sm:w-auto sm:flex-1">آخرین قیمت‌های آن منبع را کپی می‌کند؛ قیمت‌های فعلی دستی بازنویسی می‌شوند.</p>
    </form>

    {{-- ── Jadval e gheymat ha ─────────────────────────────────────── --}}
    <section class="mt-5 overflow-hidden rounded-2xl bg-surface shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[40rem] text-sm">
                <thead>
                    <tr class="border-b border-line text-xs font-semibold text-muted">
                        <th class="px-5 py-3 text-start font-semibold">نماد</th>
                        <th class="px-5 py-3 text-start font-semibold">قیمت دستی (تومان/دلار)</th>
                        <th class="px-5 py-3 text-start font-semibold">منبع</th>
                        <th class="px-5 py-3 text-start font-semibold">آخرین به‌روزرسانی</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-2">
                    @foreach ($rows as $row)
                        <tr @class(['bg-warn-soft/40' => $row['isAlarm']])>
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink">{{ $row['label'] }}</p>
                                <p class="ltr text-xs text-muted-3">{{ $row['symbol'] }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <form method="POST" action="{{ route('panel.manual.update') }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="symbol" value="{{ $row['symbol'] }}">
                                    <x-form.number name="value" ltr :value="$row['display']" class="w-40"
                                                   placeholder="{{ $row['hasPrice'] ? '' : 'خالی' }}" :aria-label="'قیمت '.$row['label']" />
                                    <button type="submit" class="inline-flex size-9 items-center justify-center rounded-lg bg-brand-soft text-brand-2 hover:bg-brand-soft-2" aria-label="ثبت">
                                        <x-icon name="check" class="size-4" />
                                    </button>
                                </form>
                            </td>
                            <td class="px-5 py-3">
                                @if ($row['sourceHint'])
                                    <span class="ltr text-xs text-muted">{{ $row['sourceHint'] }}</span>
                                @else
                                    <span class="text-muted-3">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <span class="num text-xs {{ $row['isAlarm'] ? 'font-semibold text-warn' : 'text-muted' }}">{{ $when($row['updatedAt']) }}</span>
                                @if ($row['isAlarm'])
                                    <x-panel.pill tone="warn">کهنه</x-panel.pill>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-end">
                                @if ($row['hasPrice'])
                                    <form method="POST" action="{{ route('panel.manual.remove') }}">
                                        @csrf
                                        <input type="hidden" name="symbol" value="{{ $row['symbol'] }}">
                                        <button type="submit" class="inline-flex size-9 items-center justify-center rounded-lg text-muted hover:bg-danger-soft hover:text-danger" aria-label="حذف">
                                            <x-icon name="trash" class="size-4" />
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <p class="mt-3 text-xs text-muted-2">برای فعال شدن فید پشتیبان، منبع «دستی» را در صفحه‌ی منابع روشن کنید. قیمت دستی هرگز به‌خاطر کهنگی رد نمی‌شود — همیشه نمایش داده می‌شود.</p>
</x-layouts.panel>
