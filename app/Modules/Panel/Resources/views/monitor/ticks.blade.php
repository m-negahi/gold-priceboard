{{-- TickMonitor (P3-09): akharin gozaresh haye tick — «chi rad shod va
     chera». Safhe ye asli ye debug e feed. --}}

@php
    $clock = static function (?string $iso): string {
        if ($iso === null) {
            return '—';
        }

        return \Carbon\CarbonImmutable::parse($iso)->setTimezone('Asia/Tehran')->format('H:i:s');
    };
    // Category -> label e Farsi (hamun category() e RejectionDto).
    $categories = [
        'stale' => 'کهنه',
        'future' => 'از آینده',
        'out_of_band' => 'خارج از بازه',
        'deviation' => 'انحراف ناگهانی',
        'cross_provider' => 'اختلاف بین‌منبعی',
        'non_positive' => 'نامعتبر',
    ];
@endphp

<x-layouts.panel title="پایش زنده" current="monitor" :breadcrumbs="['پنل', 'پایش زنده']">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink md:text-3xl">پایش زنده</h1>
            <p class="mt-1 text-sm text-muted">هر tick: چه گرفتیم، چه رد شد و چرا، هر مخزن چه کرد. ۵۰ tick آخر نگه داشته می‌شود.</p>
        </div>

        <x-panel.help key="monitor" title="راهنمای پایش">
            <h3>دسته‌های رد شدن</h3>
            <dl>
                <dt>کهنه</dt><dd>از سقف تازگیِ نماد قدیمی‌تر بود.</dd>
                <dt>خارج از بازه</dt><dd>بیرون بازه‌ی منطقی نماد — معمولا خطای واحد/ستون.</dd>
                <dt>انحراف ناگهانی</dt><dd>نسبت به tick قبلی بیش از سقف پرید؛ اگر tick بعدی تایید کند پذیرفته می‌شود.</dd>
                <dt>اختلاف بین‌منبعی</dt><dd>اولین قیمتِ یک منبع با میانه‌ی بقیه بیش از ۲۰٪ فاصله داشت.</dd>
            </dl>
        </x-panel.help>
    </div>

    <div class="mt-6 space-y-4">
        @forelse ($reports as $report)
            <section class="rounded-2xl bg-surface card-p shadow-xs">
                <header class="flex flex-wrap items-center gap-3">
                    <span class="num text-sm font-bold text-ink">{{ $clock($report['at'] ?? null) }}</span>

                    @if ($report['skipped'] ?? false)
                        <x-panel.pill tone="warn" dot>رد شد: {{ $report['skip_reason'] ?? '' }}</x-panel.pill>
                    @else
                        <x-panel.pill tone="ok">{{ $report['accepted'] ?? 0 }} قیمت</x-panel.pill>
                        @if (($report['rejected'] ?? 0) > 0)
                            <x-panel.pill tone="warn">{{ $report['rejected'] }} رد</x-panel.pill>
                        @endif
                        <span class="num text-xs text-muted-2">{{ number_format($report['duration_ms'] ?? 0) }}ms</span>
                    @endif

                    <span class="ms-auto flex flex-wrap items-center gap-1.5">
                        @foreach ((array) ($report['providers'] ?? []) as $key => $provider)
                            <x-panel.pill :tone="($provider['succeeded'] ?? false) ? 'ok' : 'down'" dot>
                                <span class="ltr">{{ $key }}</span>
                                <span class="num">{{ number_format($provider['latency_ms'] ?? 0) }}ms</span>
                                @if (($provider['retried'] ?? false))
                                    <span title="با تلاش دوباره">↻</span>
                                @endif
                            </x-panel.pill>
                        @endforeach
                        @foreach ((array) ($report['sinks'] ?? []) as $name => $sink)
                            {{-- Kelid e payload `ok` ast (SinkResultDto::toArray)، na `succeeded`. --}}
                            <x-panel.pill :tone="($sink['ok'] ?? false) ? 'flat' : 'down'">
                                <span class="ltr">{{ $name }}</span>
                                @if (($sink['spooled'] ?? 0) > 0)
                                    <span class="num" title="spool شد">{{ $sink['spooled'] }}</span>
                                @endif
                            </x-panel.pill>
                        @endforeach
                    </span>
                </header>

                @php
                    $groups = [];
                    foreach ((array) ($report['rejections'] ?? []) as $rejection) {
                        $groups[$rejection['category'] ?? 'other'][] = $rejection;
                    }
                @endphp

                @if ($groups !== [])
                    <ul class="mt-3 space-y-1.5 border-t border-line-2 pt-3 text-xs">
                        @foreach ($groups as $category => $rejections)
                            <li class="flex flex-wrap items-start gap-2">
                                <x-panel.pill tone="warn">{{ $categories[$category] ?? $category }}</x-panel.pill>
                                <span class="min-w-0 text-muted">
                                    @foreach (array_slice($rejections, 0, 8) as $rejection)
                                        <span class="ltr num">{{ $rejection['symbol'] ?? '' }}</span>@if (! $loop->last)،@endif
                                    @endforeach
                                    @if (count($rejections) > 8)
                                        و {{ count($rejections) - 8 }} مورد دیگر
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ((array) ($report['unmapped'] ?? []) !== [])
                    <ul class="mt-2 space-y-1 text-xs">
                        @foreach ((array) $report['unmapped'] as $provider => $labels)
                            <li class="flex flex-wrap items-center gap-2">
                                <x-panel.pill tone="down">برچسب ناشناخته</x-panel.pill>
                                <span class="ltr text-muted">{{ $provider }}:</span>
                                <span class="text-muted">{{ implode('، ', array_slice((array) $labels, 0, 5)) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @empty
            <section class="rounded-2xl bg-surface card-p shadow-xs py-10 text-center text-muted">
                هنوز گزارشی ثبت نشده — یا worker خاموش است یا Redis در دسترس نیست.
            </section>
        @endforelse
    </div>
</x-layouts.panel>
