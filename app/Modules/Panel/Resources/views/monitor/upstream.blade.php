{{-- Upstream inspector (P4-14/P4-18/P4-20): call haye MA be provider haye
     gheymat. Bala: kholase ye per-provider + khata haye por-tekrar (az Query).
     Jadval SERVER-render mishe (har radif link be jozyat e Telescope-vaar)؛
     Alpine faghat ba meta ye har radif show/hide mikone — UI-only. --}}

@php
    $clock = static fn (?string $iso): string => $iso === null
        ? '—'
        : \Carbon\CarbonImmutable::parse($iso)->setTimezone('Asia/Tehran')->format('H:i:s');

    // Jadval: tarikh e shamsi + saat ta saniye (khaste ye sahebe proje) —
    // daftarche az nime-shab rad mishe va saat e tanha mobham bud.
    $stamp = static fn (?string $iso): string => $iso === null
        ? '—'
        : (string) \App\Modules\Common\Helpers\Jalali::format(
            \Carbon\CarbonImmutable::parse($iso)->setTimezone('Asia/Tehran'),
            'Y/m/d H:i:s',
        );

    $name = static fn (string $key): string => $providerMeta[$key]['name'] ?? $key;

    $kb = static fn (?int $bytes): string => $bytes === null ? '—' : number_format($bytes / 1024, 1).' KB';

    // Salamat e kart: nerkh e movafaghiat — ok / warn / down.
    $health = static fn (int $rate): string => match (true) {
        $rate >= 95 => 'ok',
        $rate >= 70 => 'warn',
        default => 'down',
    };

    $healthLabel = ['ok' => 'سالم', 'warn' => 'ناپایدار', 'down' => 'مختل'];

@endphp

<x-layouts.panel title="پایش منابع" current="monitor.upstream" :breadcrumbs="['پنل', 'پایش', 'منابع']">
    <div x-data="{
            provider: '',
            status: 'all',
            retryOnly: false,
            slowOnly: false,
            q: '',
            metas: @js($metas),
            visible(m) {
                if (this.provider !== '' && m.p !== this.provider) return false;
                if (this.status === 'ok' && ! m.ok) return false;
                if (this.status === 'failed' && m.ok) return false;
                if (this.retryOnly && ! m.retry) return false;
                if (this.slowOnly && ! m.slow) return false;
                const q = this.q.trim().toLowerCase();
                return q === '' || m.text.includes(q);
            },
            get shown() { return this.metas.filter((m) => this.visible(m)).length; },
            reset() { this.provider = ''; this.status = 'all'; this.retryOnly = false; this.slowOnly = false; this.q = ''; },
        }">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-ink md:text-3xl">پایش منابع</h1>
                <p class="mt-1 text-sm text-muted">فراخوانی‌های ما به منابع قیمت (نه مشتری به ما) — هر ردیف را برای درخواست و پاسخ کامل باز کنید.</p>
            </div>

            <x-panel.help key="monitor-upstream" title="راهنمای پایش منابع">
                <h3>این صفحه چیست؟</h3>
                <p>هر tick، ما به هر منبع فعال یک درخواست HTTP می‌زنیم. این‌جا <b>هر تلاش</b> جداگانه ثبت می‌شود — حتی تلاش اولِ شکست‌خورده‌ای که retry جبرانش کرد.</p>
                <dl>
                    <dt>وضعیت «قطع»</dt><dd>هیچ پاسخ HTTP نیامد — خطای اتصال/TLS/timeout. متن خطا را ببینید.</dd>
                    <dt>۲۰۰ ولی ناموفق</dt><dd>منبع جواب داد ولی parse شکست خورد — معمولا markup عوض شده. بدنه پاسخ را در صفحه جزئیات ببینید.</dd>
                    <dt>تلاش ۲</dt><dd>خطای گذرای اتصال بود و در همان tick دوباره تلاش شد. اگر هر tick تکرار شود، منبع در حال خراب شدن است.</dd>
                    <dt>کند</dt><dd>بیشتر از ۸۰٪ بودجه tick — یک قدم تا جا انداختن نوبت بعدی.</dd>
                </dl>
                <p>بدنه پاسخ‌های ناموفق تا ۱۶KB و موفق‌ها فقط ۲KB اول نگه داشته می‌شود؛ فقط ~یک ساعت آخر — ابزار دیباگ لحظه‌ای است، نه آرشیو.</p>
            </x-panel.help>
        </div>

        @if ($calls === [])
            <section class="mt-6 rounded-2xl bg-surface card-p py-10 text-center text-muted shadow-xs">
                هنوز فراخوانی‌ای ثبت نشده — با اولین tick، درخواست‌های ما به منابع این‌جا دیده می‌شوند.
            </section>
        @else
            {{-- ── Kholase ye per-provider ── --}}
            {{-- Kart haye COMPACT (khaste ye sahebe proje، 2026-10-06): ba 8+ provider
                 kart e bozorg nesf e safhe ro migereft. Yek radif e kam-ertefa' baraye
                 har provider: esm + vaziat + nerkh + 3 adad + akharin khata (yek khat). --}}
            <div class="mt-6 grid gap-2 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                @foreach ($summary as $key => $s)
                    @php $tone = $health($s['success_rate']); @endphp
                    <section class="rounded-xl bg-surface px-3 py-2.5 shadow-xs" data-upstream-summary="{{ $key }}">
                        <header class="flex items-center gap-2">
                            <h2 class="min-w-0 flex-1 truncate text-sm font-bold text-ink" title="driver: {{ $providerMeta[$key]['driver'] ?? '?' }}">{{ $name($key) }}</h2>
                            <span class="num text-lg font-extrabold leading-none text-ink">{{ $s['success_rate'] }}٪</span>
                            <x-panel.pill :tone="$tone" dot>{{ $healthLabel[$tone] }}</x-panel.pill>
                        </header>

                        <dl class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted">
                            <div class="flex items-baseline gap-1"><dt class="text-muted-2">موفق</dt><dd class="num font-semibold text-ink">{{ $s['ok'] }}/{{ $s['calls'] }}</dd></div>
                            <div class="flex items-baseline gap-1"><dt class="text-muted-2">میانگین</dt><dd class="num font-semibold text-ink">{{ number_format($s['avg_latency_ms']) }}ms</dd></div>
                            <div class="flex items-baseline gap-1"><dt class="text-muted-2">بیشینه</dt><dd @class(['num font-semibold', 'text-warn' => $s['max_latency_ms'] > $slowMs, 'text-ink' => $s['max_latency_ms'] <= $slowMs])>{{ number_format($s['max_latency_ms']) }}ms</dd></div>
                            <div class="flex items-baseline gap-1"><dt class="text-muted-2">retry/کند</dt><dd class="num font-semibold text-ink">{{ $s['retries'] }}/{{ $s['slow'] }}</dd></div>
                        </dl>

                        @if ($s['last_error'] !== null)
                            <p class="mt-1 truncate text-xs text-down" title="{{ $s['last_error'] }}">
                                <span class="font-semibold">خطا {{ $clock($s['last_error_at']) }}:</span>
                                <span class="ltr">{{ $s['last_error'] }}</span>
                            </p>
                        @endif
                    </section>
                @endforeach
            </div>

            {{-- ── Khata haye por-tekrar ── --}}
            @if ($topErrors !== [])
                <section class="mt-4 rounded-2xl bg-surface card-p shadow-xs">
                    <h2 class="flex items-center gap-2 font-bold text-ink">
                        <x-icon name="alert" class="size-5 text-down" />
                        خطاهای پرتکرار
                    </h2>
                    <ul class="mt-3 divide-y divide-line-2">
                        @foreach ($topErrors as $error)
                            <li class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2.5 text-sm">
                                <x-panel.pill tone="down"><span class="num">{{ $error['count'] }}×</span></x-panel.pill>
                                <span class="font-medium text-ink">{{ $name($error['provider']) }}</span>
                                <span class="ltr min-w-0 flex-1 truncate text-xs text-muted" title="{{ $error['message'] }}">{{ $error['message'] }}</span>
                                <span class="num text-xs text-muted-2">آخرین: {{ $clock($error['last_at']) }}</span>
                                @if ($error['last_id'] !== '')
                                    <a href="{{ route('panel.monitor.upstream.show', $error['last_id']) }}"
                                       class="inline-flex h-9 items-center gap-1 rounded-lg px-2.5 text-xs font-medium text-brand-2 hover:bg-brand-soft">
                                        مشاهده
                                        <x-icon name="chevron" class="size-4" />
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- ── Filter ha ── --}}
            <section class="mt-4 space-y-3 rounded-2xl bg-surface card-p shadow-xs" x-cloak>
                <div class="flex flex-wrap items-center gap-2" role="group" aria-label="فیلتر منبع">
                    <button type="button" @click="provider = ''" :aria-pressed="provider === ''"
                            class="inline-flex h-9 items-center gap-1.5 rounded-full px-3.5 text-sm font-medium transition-colors"
                            :class="provider === '' ? 'bg-brand-700 text-on-brand' : 'bg-surface-2 text-ink-3 hover:bg-surface-3'">
                        همه <span class="num text-xs opacity-80">{{ count($calls) }}</span>
                    </button>
                    @foreach ($summary as $key => $s)
                        <button type="button" @click="provider = @js($key)" :aria-pressed="provider === @js($key)"
                                class="inline-flex h-9 items-center gap-1.5 rounded-full px-3.5 text-sm font-medium transition-colors"
                                :class="provider === @js($key) ? 'bg-brand-700 text-on-brand' : 'bg-surface-2 text-ink-3 hover:bg-surface-3'">
                            {{ $name($key) }} <span class="num text-xs opacity-80">{{ $s['calls'] }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex rounded-xl bg-surface-2 p-1" role="radiogroup" aria-label="وضعیت">
                        @foreach (['all' => 'همه', 'ok' => 'موفق', 'failed' => 'خطا'] as $value => $label)
                            <button type="button" role="radio" @click="status = @js($value)" :aria-checked="status === @js($value)"
                                    class="h-9 rounded-lg px-3.5 text-sm font-medium transition-colors"
                                    :class="status === @js($value) ? 'bg-surface text-ink shadow-xs' : 'text-muted hover:text-ink'">{{ $label }}</button>
                        @endforeach
                    </div>

                    <label class="flex min-h-11 cursor-pointer select-none items-center gap-2 text-sm text-ink">
                        <input type="checkbox" x-model="retryOnly" class="size-4 rounded border-line-3">
                        فقط retry
                    </label>
                    <label class="flex min-h-11 cursor-pointer select-none items-center gap-2 text-sm text-ink">
                        <input type="checkbox" x-model="slowOnly" class="size-4 rounded border-line-3">
                        فقط کند
                    </label>

                    <div class="relative min-w-48 flex-1">
                        <x-icon name="search" class="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-muted-2" />
                        <input type="search" x-model.debounce.150ms="q" placeholder="جستجو در آدرس یا متن خطا…" aria-label="جستجو"
                               class="h-10 w-full rounded-xl border border-line-3 bg-surface ps-9 pe-3 text-sm focus:border-brand focus:outline-none">
                    </div>

                    <span class="text-xs text-muted-2">
                        <span class="num" x-text="shown"></span> از <span class="num">{{ count($calls) }}</span>
                    </span>
                </div>
            </section>

            {{-- ── Jadval ── --}}
            <section class="mt-4 overflow-hidden rounded-2xl bg-surface shadow-xs">
                {{-- 🔴 `relative`: `sr-only` e header (absolute) bedun e ajdad e
                     positioned AZ clip e scroll farar mikard va kol e safhe ro tu
                     mobile 514px be chap pahn mikard (responsive.spec gereft). --}}
                <div class="relative overflow-x-auto">
                    <table class="w-full min-w-[52rem] text-sm">
                        <thead>
                            <tr class="border-b border-line text-xs font-semibold text-muted">
                                <th class="px-4 py-3 text-start font-semibold">زمان</th>
                                <th class="px-4 py-3 text-start font-semibold">منبع</th>
                                <th class="px-4 py-3 text-start font-semibold">وضعیت</th>
                                <th class="px-4 py-3 text-start font-semibold">مدت</th>
                                <th class="px-4 py-3 text-start font-semibold">تلاش</th>
                                <th class="px-4 py-3 text-start font-semibold">نمادها</th>
                                <th class="px-4 py-3 text-start font-semibold">حجم</th>
                                <th class="px-4 py-3 text-start font-semibold">آدرس / خطا</th>
                                <th class="px-2 py-3"><span class="sr-only">جزئیات</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-2">
                            @foreach ($calls as $i => $call)
                                @php
                                    $ok = (bool) $call['succeeded'];
                                    $slow = (int) $call['latency_ms'] > $slowMs;
                                    $href = ($call['id'] ?? '') !== '' ? route('panel.monitor.upstream.show', $call['id']) : null;
                                @endphp
                                <tr x-show="visible(metas[{{ $i }}])" @class(['group', 'bg-down-soft/30' => ! $ok, 'hover:bg-surface-2' => $ok])>
                                    <td class="num whitespace-nowrap px-4 py-2.5 text-xs font-bold text-ink">
                                        @if ($href)
                                            <a href="{{ $href }}" class="hover:underline">{{ $stamp($call['at']) }}</a>
                                        @else
                                            {{ $stamp($call['at']) }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 font-medium text-ink">{{ $name($call['provider']) }}</td>
                                    <td class="px-4 py-2.5">
                                        <x-panel.pill :tone="$ok ? 'ok' : 'down'"><span class="num">{{ $call['status'] ?? 'قطع' }}</span></x-panel.pill>
                                    </td>
                                    <td @class(['num whitespace-nowrap px-4 py-2.5 text-xs', 'font-semibold text-warn' => $slow, 'text-muted-2' => ! $slow])>{{ number_format((int) $call['latency_ms']) }}ms</td>
                                    <td class="px-4 py-2.5 text-xs">
                                        @if ((int) $call['attempt'] > 1)
                                            <span class="num font-semibold text-warn" title="تلاش دوم یعنی خطای گذرای اتصال در همان tick">↻ {{ $call['attempt'] }}</span>
                                        @else
                                            <span class="num text-muted-2">{{ $call['attempt'] }}</span>
                                        @endif
                                    </td>
                                    <td class="num px-4 py-2.5 text-xs text-muted-2">{{ $call['symbol_count'] }}</td>
                                    {{-- `ltr` vagarna bidi «5.3 KB» ro «KB 5.3» neshun mide. --}}
                                    <td class="ltr num whitespace-nowrap px-4 py-2.5 text-start text-xs text-muted-2">{{ $kb($call['bytes'] ?? null) }}</td>
                                    <td class="max-w-80 px-4 py-2.5">
                                        <p class="ltr num truncate text-xs text-muted" title="{{ $call['url'] }}">{{ $call['method'] }} {{ $call['url'] }}</p>
                                        @if (($call['error'] ?? null) !== null)
                                            <p class="ltr mt-0.5 line-clamp-2 text-xs text-down">{{ $call['error'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2.5 text-end">
                                        @if ($href)
                                            <a href="{{ $href }}" aria-label="جزئیات فراخوانی {{ $stamp($call['at']) }}"
                                               class="inline-flex size-9 items-center justify-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink">
                                                <x-icon name="chevron" class="size-4" />
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-line-2 px-4 py-6 text-center text-sm text-muted" x-show="shown === 0" x-cloak>
                    با این فیلترها چیزی پیدا نشد.
                    <button type="button" @click="reset()" class="ms-1 font-medium text-brand-2 hover:underline">پاک کردن فیلترها</button>
                </div>
            </section>
        @endif
    </div>
</x-layouts.panel>
