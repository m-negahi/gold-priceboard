{{-- TickMonitor (P3-09/P4-12/P4-18): akharin gozaresh haye tick — «chi rad
     shod va CHERA، ba MEGHDAR». Bala: kholase ye per-provider (az Query).
     Do nama: «خط زمانی» (card e har tick) va «جدول ردها» (saf). Filter ha
     Alpine e UI-only ru meta ye server (`filterMeta`) — hich data-shaping
     inja. Tick e «بدون اجرا» be soorat e default penhan (tabi'i va noise). --}}

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

    $name = static fn (string $key): string => $providerMeta[$key]['name'] ?? $key;

    $health = static fn (int $rate): string => match (true) {
        $rate >= 95 => 'ok',
        $rate >= 70 => 'warn',
        default => 'down',
    };

    $healthLabel = ['ok' => 'سالم', 'warn' => 'ناپایدار', 'down' => 'مختل'];

    $skippedCount = count(array_filter(array_column($metas, 'skipped')));
@endphp

<x-layouts.panel title="پایش زنده" current="monitor.ticks" :breadcrumbs="['پنل', 'پایش', 'tick']">
    <div x-data="{
            view: 'timeline',
            provider: '',
            onlyProblems: false,
            showSkipped: false,
            symbol: '',
            category: '',
            metas: @js($metas),
            rows: @js($rejections),
            reportVisible(m) {
                if (m.skipped && ! this.showSkipped) return false;
                if (this.provider !== '' && ! m.providers.includes(this.provider)) return false;
                if (this.onlyProblems && ! m.problem) return false;
                if (this.symbol !== '' && ! m.symbols.includes(this.symbol)) return false;
                if (this.category !== '' && ! m.categories.includes(this.category)) return false;
                return true;
            },
            rowVisible(p, s, c) {
                return (this.provider === '' || p === this.provider)
                    && (this.symbol === '' || s === this.symbol)
                    && (this.category === '' || c === this.category);
            },
            get shownReports() { return this.metas.filter((m) => this.reportVisible(m)).length; },
            get shownRows() { return this.rows.filter((r) => this.rowVisible(r.provider, r.symbol, r.category)).length; },
            reset() { this.provider = ''; this.onlyProblems = false; this.symbol = ''; this.category = ''; },
        }">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-ink md:text-3xl">پایش زنده</h1>
                <p class="mt-1 text-sm text-muted">هر tick: چه گرفتیم، چه رد شد و چرا — با مقدار و دلیل دقیق. <span class="num">{{ count($reports) }}</span> tick آخر.</p>
            </div>

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
                <h3>چرا بعضی tick ها «بدون اجرا» هستند؟</h3>
                <p>چرخه واقعی ~۳۰ ثانیه است: tick حدود ۱۰ ثانیه طول می‌کشد و نوبت بعدیِ منبع ۱۵ ثانیه جلوتر می‌رود؛ پس یک‌درمیان «هیچ منبعی نوبت ندارد» — سالم است و به‌طور پیش‌فرض پنهان می‌شود.</p>
            </x-panel.help>
        </div>

        @if ($reports === [])
            <section class="mt-6 rounded-2xl bg-surface card-p py-10 text-center text-muted shadow-xs">
                هنوز گزارشی ثبت نشده — یا worker خاموش است یا Redis در دسترس نیست.
            </section>
        @else
            {{-- ── Kholase ye per-provider ── --}}
            @if ($summary !== [])
                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($summary as $key => $s)
                        @php $tone = $health($s['success_rate']); @endphp
                        <section class="rounded-2xl bg-surface card-p shadow-xs" data-tick-summary="{{ $key }}">
                            <header class="flex items-center justify-between gap-2">
                                <h2 class="truncate font-bold text-ink" title="driver: {{ $providerMeta[$key]['driver'] ?? '?' }}">{{ $name($key) }}</h2>
                                <x-panel.pill :tone="$tone" dot>{{ $healthLabel[$tone] }}</x-panel.pill>
                            </header>

                            <div class="mt-3 flex items-end gap-2">
                                <span class="num text-3xl font-extrabold text-ink">{{ $s['success_rate'] }}٪</span>
                                <span class="pb-1 text-xs text-muted"><span class="num">{{ $s['succeeded'] }}</span> از <span class="num">{{ $s['ticks'] }}</span> tick موفق</span>
                            </div>

                            <dl class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                <div class="rounded-lg bg-surface-2 px-2.5 py-2">
                                    <dt class="text-muted-2">میانگین</dt>
                                    {{-- 0 = source e bedun e HTTP (dasti/mock) — «0ms» gomrah-konande-st. --}}
                                    <dd class="num mt-0.5 font-semibold text-ink">{{ $s['avg_latency_ms'] > 0 ? number_format($s['avg_latency_ms']).'ms' : '—' }}</dd>
                                </div>
                                <div class="rounded-lg bg-surface-2 px-2.5 py-2">
                                    <dt class="text-muted-2">بیشینه</dt>
                                    <dd class="num mt-0.5 font-semibold text-ink">{{ $s['max_latency_ms'] > 0 ? number_format($s['max_latency_ms']).'ms' : '—' }}</dd>
                                </div>
                                <div class="rounded-lg bg-surface-2 px-2.5 py-2">
                                    <dt class="text-muted-2">retry</dt>
                                    <dd class="num mt-0.5 font-semibold text-ink">{{ $s['retried'] }}</dd>
                                </div>
                            </dl>

                            @if ($s['rejections'] > 0)
                                <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs">
                                    <span class="text-muted"><span class="num">{{ $s['rejections'] }}</span> رد:</span>
                                    @foreach ($s['by_category'] as $category => $count)
                                        <x-panel.pill tone="warn">{{ $categories[$category] ?? $category }} <span class="num">{{ $count }}</span></x-panel.pill>
                                    @endforeach
                                </div>
                            @endif

                            @if ($s['last_error'] !== null)
                                <p class="mt-3 line-clamp-2 text-xs text-down" title="{{ $s['last_error'] }}">
                                    <span class="font-semibold">آخرین خطا {{ $clock($s['last_error_at']) }}:</span>
                                    <span class="ltr">{{ $s['last_error'] }}</span>
                                </p>
                            @endif
                        </section>
                    @endforeach
                </div>
            @endif

            {{-- ── Filter ha ── --}}
            <section class="mt-4 space-y-3 rounded-2xl bg-surface card-p shadow-xs" x-cloak>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex rounded-xl bg-surface-2 p-1" role="radiogroup" aria-label="نما">
                        <button type="button" role="radio" @click="view = 'timeline'" :aria-checked="view === 'timeline'"
                                class="h-9 rounded-lg px-3.5 text-sm font-medium transition-colors"
                                :class="view === 'timeline' ? 'bg-surface text-ink shadow-xs' : 'text-muted hover:text-ink'">خط زمانی</button>
                        <button type="button" role="radio" @click="view = 'rejections'" :aria-checked="view === 'rejections'"
                                class="h-9 rounded-lg px-3.5 text-sm font-medium transition-colors"
                                :class="view === 'rejections' ? 'bg-surface text-ink shadow-xs' : 'text-muted hover:text-ink'">
                            جدول ردها <span class="num text-xs opacity-80">{{ count($rejections) }}</span>
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2" role="group" aria-label="فیلتر منبع">
                        <button type="button" @click="provider = ''" :aria-pressed="provider === ''"
                                class="inline-flex h-9 items-center rounded-full px-3.5 text-sm font-medium transition-colors"
                                :class="provider === '' ? 'bg-brand-700 text-on-brand' : 'bg-surface-2 text-ink-3 hover:bg-surface-3'">همه</button>
                        @foreach (array_keys($summary) as $key)
                            <button type="button" @click="provider = @js($key)" :aria-pressed="provider === @js($key)"
                                    class="inline-flex h-9 items-center rounded-full px-3.5 text-sm font-medium transition-colors"
                                    :class="provider === @js($key) ? 'bg-brand-700 text-on-brand' : 'bg-surface-2 text-ink-3 hover:bg-surface-3'">{{ $name($key) }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm text-ink">
                        نماد:
                        <select x-model="symbol" class="ltr h-10 min-w-36 rounded-xl border border-line-3 bg-surface px-3 text-sm focus:border-brand focus:outline-none">
                            <option value="">همه</option>
                            @foreach ($options['symbols'] as $symbol)
                                <option value="{{ $symbol }}">{{ $symbol }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="flex items-center gap-2 text-sm text-ink">
                        دلیل رد:
                        <select x-model="category" class="h-10 min-w-36 rounded-xl border border-line-3 bg-surface px-3 text-sm focus:border-brand focus:outline-none">
                            <option value="">همه</option>
                            @foreach ($options['categories'] as $category)
                                <option value="{{ $category }}">{{ $categories[$category] ?? $category }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label x-show="view === 'timeline'" class="flex min-h-11 cursor-pointer select-none items-center gap-2 text-sm text-ink">
                        <input type="checkbox" x-model="onlyProblems" class="size-4 rounded border-line-3">
                        فقط tick های مشکل‌دار
                    </label>

                    @if ($skippedCount > 0)
                        <label x-show="view === 'timeline'" class="flex min-h-11 cursor-pointer select-none items-center gap-2 text-sm text-muted">
                            <input type="checkbox" x-model="showSkipped" class="size-4 rounded border-line-3">
                            نمایش tick های بدون اجرا (<span class="num">{{ $skippedCount }}</span>)
                        </label>
                    @endif

                    <span class="ms-auto text-xs text-muted-2">
                        <span x-show="view === 'timeline'"><span class="num" x-text="shownReports"></span> از <span class="num">{{ count($reports) }}</span> tick</span>
                        <span x-show="view === 'rejections'"><span class="num" x-text="shownRows"></span> از <span class="num">{{ count($rejections) }}</span> رد</span>
                    </span>
                </div>
            </section>

            {{-- ── Nama 1: khat e zamani ── --}}
            <div class="mt-4 space-y-4" x-show="view === 'timeline'">
                @foreach ($reports as $i => $report)
                    <section class="rounded-2xl bg-surface card-p shadow-xs" data-tick-report x-show="reportVisible(metas[{{ $i }}])" @if ($metas[$i]['skipped']) x-cloak @endif>
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
                                @foreach ((array) ($report['sinks'] ?? []) as $sinkName => $sink)
                                    <x-panel.pill :tone="($sink['ok'] ?? false) ? 'flat' : 'down'">
                                        <span class="ltr">{{ $sinkName }}</span>
                                        @if (($sink['spooled'] ?? 0) > 0)
                                            <span class="num" title="خط‌های spool شده — بعد از وصل شدن replay می‌شوند">{{ $sink['spooled'] }}</span>
                                        @endif
                                    </x-panel.pill>
                                @endforeach
                            </span>
                        </header>

                        {{-- Khata ye provider ha — matn e kamel + link be daftarche ye upstream. --}}
                        @foreach ((array) ($report['providers'] ?? []) as $key => $provider)
                            @if (! ($provider['succeeded'] ?? true) && ($provider['error'] ?? null))
                                <p class="mt-2 text-xs text-down">
                                    <span class="font-semibold">{{ $name($key) }}:</span>
                                    <span class="ltr">{{ $provider['error'] }}</span>
                                    <a href="{{ route('panel.monitor.upstream') }}" class="ms-1 font-medium text-brand-2 hover:underline">جزئیات درخواست</a>
                                </p>
                            @endif
                        @endforeach

                        @php $reportRejections = (array) ($report['rejections'] ?? []); @endphp

                        @if ($reportRejections !== [])
                            <details class="mt-3 border-t border-line-2 pt-3" :open="symbol !== '' || category !== ''">
                                <summary class="cursor-pointer select-none text-xs font-medium text-muted hover:text-ink">
                                    جزئیات {{ count($reportRejections) }} رد — منبع، نماد، مقدار، دلیل
                                </summary>
                                <div class="relative mt-2 overflow-x-auto">
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
                                            @foreach ($reportRejections as $rejection)
                                                <tr x-show="rowVisible(@js($rejection['provider'] ?? ''), @js($rejection['symbol'] ?? ''), @js($rejection['category'] ?? ''))">
                                                    <td class="py-1.5 pe-3">{{ $name($rejection['provider'] ?? '?') }}</td>
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
                                        <span class="text-muted">{{ $name($provider) }} کلیدهایی فرستاده که map نشده‌اند (markup عوض شده؟):</span>
                                        <span class="ltr text-muted">{{ implode('، ', array_slice((array) $labels, 0, 5)) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endforeach

                <section class="rounded-2xl bg-surface card-p py-8 text-center text-sm text-muted shadow-xs" x-show="shownReports === 0" x-cloak>
                    با این فیلترها tick ای پیدا نشد.
                    <button type="button" @click="reset()" class="ms-1 font-medium text-brand-2 hover:underline">پاک کردن فیلترها</button>
                </section>
            </div>

            {{-- ── Nama 2: jadval e saf e rad ha ── --}}
            <section class="mt-4 overflow-hidden rounded-2xl bg-surface shadow-xs" x-show="view === 'rejections'" x-cloak>
                @if ($rejections === [])
                    <p class="px-4 py-10 text-center text-sm text-muted">در این بازه هیچ قیمتی رد نشده.</p>
                @else
                    {{-- `relative`: hamun dars e sr-only/absolute dakhel e scroller. --}}
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[48rem] text-sm">
                            <thead>
                                <tr class="border-b border-line text-xs font-semibold text-muted">
                                    <th class="px-4 py-3 text-start font-semibold">زمان</th>
                                    <th class="px-4 py-3 text-start font-semibold">منبع</th>
                                    <th class="px-4 py-3 text-start font-semibold">نماد</th>
                                    <th class="px-4 py-3 text-start font-semibold">سمت</th>
                                    <th class="px-4 py-3 text-start font-semibold">مقدار</th>
                                    <th class="px-4 py-3 text-start font-semibold">چرا رد شد</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line-2">
                                @foreach ($rejections as $j => $row)
                                    <tr x-show="rowVisible(rows[{{ $j }}].provider, rows[{{ $j }}].symbol, rows[{{ $j }}].category)" class="hover:bg-surface-2">
                                        <td class="num whitespace-nowrap px-4 py-2.5 text-xs font-bold text-ink">{{ $clock($row['at'] ?? null) }}</td>
                                        <td class="px-4 py-2.5 font-medium text-ink">{{ $name($row['provider'] ?? '?') }}</td>
                                        <td class="ltr num px-4 py-2.5 text-start text-xs">{{ $row['symbol'] ?? '' }}</td>
                                        <td class="ltr px-4 py-2.5 text-start text-xs text-muted">{{ $row['side'] ?? '' }}</td>
                                        <td class="num px-4 py-2.5 text-xs">{{ isset($row['minor']) && $row['minor'] !== null ? number_format($row['minor']) : '—' }}</td>
                                        <td class="px-4 py-2.5 text-xs">
                                            <x-panel.pill tone="warn">{{ $categories[$row['category'] ?? ''] ?? ($row['category'] ?? '?') }}</x-panel.pill>
                                            <span class="text-muted">{{ $explain($row) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-line-2 px-4 py-6 text-center text-sm text-muted" x-show="shownRows === 0" x-cloak>
                        با این فیلترها ردی پیدا نشد.
                        <button type="button" @click="reset()" class="ms-1 font-medium text-brand-2 hover:underline">پاک کردن فیلترها</button>
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-layouts.panel>
