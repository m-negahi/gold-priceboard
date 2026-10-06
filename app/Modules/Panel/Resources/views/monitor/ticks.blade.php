{{-- TickMonitor (P3-09/P4-12): akharin gozaresh haye tick — «chi rad shod
     va CHERA، ba MEGHDAR». Safhe ye asli ye debug e feed. --}}

@php
    $clock = static function (?string $iso): string {
        if ($iso === null) {
            return '—';
        }

        return \Carbon\CarbonImmutable::parse($iso)->setTimezone('Asia/Tehran')->format('H:i:s');
    };

    $humanSeconds = static function (int $s): string {
        if ($s < 60) {
            return $s.' ثانیه';
        }
        if ($s < 3600) {
            return intdiv($s, 60).' دقیقه';
        }
        if ($s < 86400) {
            return intdiv($s, 3600).' ساعت';
        }

        return intdiv($s, 86400).' روز';
    };

    // Dalil e rad — tarjome ye MAFHOOMI ba meghdar (P4-12). Reason e kham
    // shekl e `category:param` dare.
    $explain = static function (array $rejection) use ($humanSeconds): string {
        $reason = (string) ($rejection['reason'] ?? '');
        [$category, $param] = array_pad(explode(':', $reason, 2), 2, '');

        return match ($category) {
            'stale' => sprintf(
                'قیمت منبع %s قدیمی بود — بیشتر از سقف تازگیِ این نماد. (بازار بسته = طبیعی)',
                $humanSeconds((int) rtrim($param, 's')),
            ),
            'future' => 'timestamp منبع از آینده است (اختلاف ساعت یا داده خراب).',
            'out_of_band' => 'خارج از بازه منطقی نماد — معمولا خطای واحد (تومان/ریال) یا ستون اشتباه.',
            'deviation' => sprintf(
                'پرش %s نسبت به tick قبلی — بیش از سقف (۵٪ دلاری / ۱۰٪ ریالی). اگر tick بعدی همین حدود را تایید کند، پذیرفته می‌شود.',
                str_replace('%', '٪', $param),
            ),
            'cross_provider' => sprintf(
                'اولین قیمت این منبع %s با میانه سایر منابع فاصله دارد (سقف ۲۰٪) — معمولا خطای پیکربندی/واحد.',
                str_replace('%', '٪', $param),
            ),
            'non_positive' => 'مقدار صفر یا منفی — داده خراب.',
            default => $reason,
        };
    };

    $categories = [
        'stale' => 'کهنه',
        'future' => 'از آینده',
        'out_of_band' => 'خارج از بازه',
        'deviation' => 'پرش ناگهانی',
        'cross_provider' => 'اختلاف بین‌منبعی',
        'non_positive' => 'نامعتبر',
    ];

    // Payam e skip e kham (Finglish e engine) -> tozih e Farsi.
    $skipText = static fn (string $reason): string => match (true) {
        str_contains($reason, 'provider e fa\'ali') => 'در این نوبت هیچ منبعی نوبت poll نداشت — چرخه واقعی ~۳۰ ثانیه است (tick ~۱۰ ثانیه طول می‌کشد و نوبت بعدی ۱۵ ثانیه جلوتر می‌رود). طبیعی است.',
        str_contains($reason, 'hanuz dar hale ejra') => 'tick قبلی هنوز در حال اجرا بود (قفل). اگر مدام تکرار شود، یک منبع کند است.',
        str_contains($reason, 'snapshot haye ghabli') => 'پایگاه‌داده در دسترس نبود — بدون قیمت‌های قبلی، محافظ‌ها کور می‌شوند و tick عمدا رد می‌شود.',
        default => $reason,
    };
@endphp

<x-layouts.panel title="پایش زنده" current="monitor" :breadcrumbs="['پنل', 'پایش زنده']">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink md:text-3xl">پایش زنده</h1>
            <p class="mt-1 text-sm text-muted">هر tick: چه گرفتیم، چه رد شد و چرا — با مقدار و دلیل دقیق. ۵۰ tick آخر.</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('panel.monitor.upstream') }}"
               class="inline-flex h-10 items-center gap-2 rounded-xl border border-line px-4 text-sm font-medium text-ink hover:bg-surface-2">
                <x-icon name="download" class="size-4" />
                پایش منابع
            </a>
            <a href="{{ route('panel.monitor.api') }}"
               class="inline-flex h-10 items-center gap-2 rounded-xl border border-line px-4 text-sm font-medium text-ink hover:bg-surface-2">
                <x-icon name="code" class="size-4" />
                پایش API
            </a>

            <x-panel.help key="monitor" title="راهنمای پایش">
                <h3>محافظ‌های قیمت — سقف‌ها</h3>
                <dl>
                    <dt>پرش ناگهانی</dt>
                    <dd>هر قیمت نسبت به tick قبلی حداکثر <b>۵٪ برای نمادهای دلاری</b> و <b>۱۰٪ برای ریالی</b> می‌تواند حرکت کند؛ بیشتر از آن رد می‌شود. <b>قاعده دو-تایی:</b> اگر tick بعدی همان حدود را تایید کند (تا ۲٪ اختلاف)، پذیرفته می‌شود — یعنی جهش واقعی بازار فقط یک tick (۱۵ ثانیه) دیر می‌رسد ولی خطای parse هرگز ثبت نمی‌شود.</dd>
                    <dt>بازه منطقی</dt>
                    <dd>هر نماد یک بازه مطلق دارد که فقط «مزخرف» را می‌گیرد (فلیپ ارز، ستون اشتباه) — خطای ۱۰× را محافظِ پرش می‌گیرد، نه این.</dd>
                    <dt>اختلاف بین‌منبعی</dt>
                    <dd>اولین قیمتِ یک منبع تازه با میانه سایر منابع مقایسه می‌شود (سقف ۲۰٪) — جلوی «تومان به جای ریال» را از tick اول می‌گیرد.</dd>
                    <dt>کهنه</dt>
                    <dd>قدیمی‌تر از سقف تازگی نماد. شب و تعطیلی طبیعی است — تابلو آخرین قیمت پذیرفته‌شده را با نشان سن نگه می‌دارد.</dd>
                </dl>
                <h3>چرا بعضی tick ها «رد» می‌شوند؟</h3>
                <p>چرخه واقعی ~۳۰ ثانیه است: tick حدود ۱۰ ثانیه طول می‌کشد و نوبت بعدیِ منبع ۱۵ ثانیه جلوتر می‌رود؛ پس یک‌درمیان «هیچ منبعی نوبت ندارد» می‌بینید — سالم است.</p>
            </x-panel.help>
        </div>
    </div>

    <div class="mt-6 space-y-4">
        @forelse ($reports as $report)
            <section class="rounded-2xl bg-surface card-p shadow-xs">
                <header class="flex flex-wrap items-center gap-3">
                    <span class="num text-sm font-bold text-ink">{{ $clock($report['at'] ?? null) }}</span>

                    @if ($report['skipped'] ?? false)
                        <x-panel.pill tone="flat" dot>بدون اجرا</x-panel.pill>
                        <span class="text-xs text-muted">{{ $skipText((string) ($report['skip_reason'] ?? '')) }}</span>
                    @else
                        <x-panel.pill tone="ok">{{ $report['accepted'] ?? 0 }} قیمت پذیرفته</x-panel.pill>
                        @if (($report['rejected'] ?? 0) > 0)
                            <x-panel.pill tone="warn">{{ $report['rejected'] }} رد</x-panel.pill>
                        @endif
                        <span class="num text-xs text-muted-2">{{ number_format($report['duration_ms'] ?? 0) }}ms</span>
                    @endif

                    <span class="ms-auto flex flex-wrap items-center gap-1.5">
                        @foreach ((array) ($report['providers'] ?? []) as $key => $provider)
                            @php $meta = $providerMeta[$key] ?? null; @endphp
                            <x-panel.pill :tone="($provider['succeeded'] ?? false) ? 'ok' : 'down'" dot>
                                <span title="driver: {{ $meta['driver'] ?? '?' }}">{{ $meta['name'] ?? $key }}</span>
                                <span class="num">{{ number_format($provider['latency_ms'] ?? 0) }}ms</span>
                                <span class="num" title="تعداد نماد">{{ $provider['symbol_count'] ?? 0 }}</span>
                                @if (($provider['retried'] ?? false))
                                    <span title="با تلاش دوباره موفق شد — اگر هر tick تکرار شود، منبع در حال خراب شدن است">↻</span>
                                @endif
                            </x-panel.pill>
                        @endforeach
                        @foreach ((array) ($report['sinks'] ?? []) as $name => $sink)
                            <x-panel.pill :tone="($sink['ok'] ?? false) ? 'flat' : 'down'">
                                <span class="ltr">{{ $name }}</span>
                                @if (($sink['spooled'] ?? 0) > 0)
                                    <span class="num" title="خط‌های spool شده — بعد از وصل شدن replay می‌شوند">{{ $sink['spooled'] }}</span>
                                @endif
                            </x-panel.pill>
                        @endforeach
                    </span>
                </header>

                {{-- Khata ye provider ha — matn e kamel. --}}
                @foreach ((array) ($report['providers'] ?? []) as $key => $provider)
                    @if (! ($provider['succeeded'] ?? true) && ($provider['error'] ?? null))
                        <p class="mt-2 text-xs text-down">
                            <span class="font-semibold">{{ $providerMeta[$key]['name'] ?? $key }}:</span>
                            <span class="ltr">{{ $provider['error'] }}</span>
                        </p>
                    @endif
                @endforeach

                @php $rejections = (array) ($report['rejections'] ?? []); @endphp

                @if ($rejections !== [])
                    {{-- <details> e native — bedun e JS، baz-shodani. --}}
                    <details class="mt-3 border-t border-line-2 pt-3">
                        <summary class="cursor-pointer select-none text-xs font-medium text-muted hover:text-ink">
                            جزئیات {{ count($rejections) }} رد — منبع، نماد، مقدار، دلیل
                        </summary>
                        <div class="mt-2 overflow-x-auto">
                            <table class="w-full min-w-[40rem] text-xs">
                                <thead>
                                    <tr class="text-start text-muted-2">
                                        <th class="py-1.5 pe-3 text-start font-medium">منبع</th>
                                        <th class="py-1.5 pe-3 text-start font-medium">نماد</th>
                                        <th class="py-1.5 pe-3 text-start font-medium">سمت</th>
                                        <th class="py-1.5 pe-3 text-start font-medium">مقدار (ریال/مینور)</th>
                                        <th class="py-1.5 text-start font-medium">چرا رد شد</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line-2">
                                    @foreach ($rejections as $rejection)
                                        <tr>
                                            <td class="py-1.5 pe-3">{{ $providerMeta[$rejection['provider'] ?? '']['name'] ?? ($rejection['provider'] ?? '?') }}</td>
                                            <td class="ltr num py-1.5 pe-3">{{ $rejection['symbol'] ?? '' }}</td>
                                            <td class="ltr py-1.5 pe-3 text-muted">{{ $rejection['side'] ?? '' }}</td>
                                            <td class="num py-1.5 pe-3">{{ isset($rejection['minor']) && $rejection['minor'] !== null ? number_format($rejection['minor']) : '—' }}</td>
                                            <td class="py-1.5">
                                                <x-panel.pill tone="warn">{{ $categories[$rejection['category'] ?? ''] ?? ($rejection['category'] ?? '?') }}</x-panel.pill>
                                                <span class="text-muted">{{ $explain($rejection) }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif

                @if ((array) ($report['unmapped'] ?? []) !== [])
                    <ul class="mt-2 space-y-1 text-xs">
                        @foreach ((array) $report['unmapped'] as $provider => $labels)
                            <li class="flex flex-wrap items-center gap-2">
                                <x-panel.pill tone="down">برچسب ناشناخته</x-panel.pill>
                                <span class="text-muted">{{ $providerMeta[$provider]['name'] ?? $provider }} کلیدهایی فرستاده که map نشده‌اند (markup عوض شده؟):</span>
                                <span class="ltr text-muted">{{ implode('، ', array_slice((array) $labels, 0, 5)) }}</span>
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
