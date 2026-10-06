{{-- Jozyat e yek call e upstream (P4-20) — Telescope-vaar: kholase، khata،
     request (method/URL/header/form) va response (header/body e pretty).
     Header/field haye hassas ghabl az zakhire redact shodan (FetchStage). --}}

@php
    $at = \Carbon\CarbonImmutable::parse($call['at'])->setTimezone('Asia/Tehran');
    $ok = (bool) $call['succeeded'];
    $providerName = $providerMeta[$call['provider']]['name'] ?? $call['provider'];

    // JSON e motabar -> pretty + unescape (mesl e payesh e API)؛ vagarna kham.
    $pretty = static function (?string $raw): ?string {
        if ($raw === null || $raw === '') {
            return $raw;
        }

        $decoded = json_decode($raw, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            // JSON e NAGHES (preview e boride) — pretty nemishe، vali `\uXXXX`
            // faghat baraye namayesh harf mishe ta matn e Farsi khunda bashe.
            return (string) preg_replace_callback(
                '/\\\\u([0-9a-fA-F]{4})/',
                static fn (array $m): string => mb_chr((int) hexdec($m[1]), 'UTF-8') ?: $m[0],
                $raw,
            );
        }

        return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    };

    $body = $pretty($call['response_body'] ?? null);
@endphp

<x-layouts.panel :title="'فراخوانی '.$providerName" current="monitor.upstream" :breadcrumbs="['پنل', 'پایش', 'منابع', $at->format('H:i:s')]">
    <a href="{{ route('panel.monitor.upstream') }}"
       class="inline-flex h-10 items-center gap-1.5 rounded-xl px-3 text-sm font-medium text-muted hover:bg-surface hover:text-ink">
        <x-icon name="arrow-back" class="size-4" />
        بازگشت به پایش منابع
    </a>

    {{-- ── Kholase ── --}}
    <section class="mt-3 rounded-2xl bg-surface card-p shadow-xs">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-extrabold text-ink">{{ $providerName }}</h1>
            <x-panel.pill :tone="$ok ? 'ok' : 'down'" dot>
                <span class="num">{{ $call['status'] ?? 'قطع' }}</span>
                {{ $ok ? 'موفق' : 'ناموفق' }}
            </x-panel.pill>
            @if ((int) $call['attempt'] > 1)
                <x-panel.pill tone="warn"><span class="num">↻ تلاش {{ $call['attempt'] }}</span></x-panel.pill>
            @endif
        </div>

        <p class="ltr num mt-3 break-all rounded-xl bg-surface-2 px-3 py-2 text-sm text-ink">
            <span class="font-bold">{{ $call['method'] }}</span> {{ $call['url'] }}
        </p>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
            @foreach ([
                'زمان' => (string) \App\Modules\Common\Helpers\Jalali::format($at, 'Y/m/d H:i:s'),
                'مدت' => number_format((int) $call['latency_ms']).'ms',
                'تلاش' => (string) $call['attempt'],
                'نمادها' => (string) $call['symbol_count'],
                'حجم پاسخ' => $call['bytes'] === null ? '—' : number_format((int) $call['bytes']).' B',
                'شناسه' => $call['id'],
            ] as $label => $value)
                <div class="rounded-xl bg-surface-2 px-3 py-2">
                    <dt class="text-xs text-muted-2">{{ $label }}</dt>
                    <dd class="ltr num mt-0.5 truncate text-start font-semibold text-ink" title="{{ $value }}">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    @if (($call['error'] ?? null) !== null)
        <section class="mt-4 rounded-2xl bg-danger-soft card-p shadow-xs">
            <h2 class="flex items-center gap-2 font-bold text-danger">
                <x-icon name="alert" class="size-5" />
                خطا
            </h2>
            <pre class="ltr mt-2 whitespace-pre-wrap break-all text-sm text-danger">{{ $call['error'] }}</pre>
        </section>
    @endif

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        {{-- ── Request ── --}}
        <section class="min-w-0 rounded-2xl bg-surface card-p shadow-xs">
            <h2 class="font-bold text-ink">درخواست</h2>

            <h3 class="mt-4 text-xs font-semibold text-muted">هدرها</h3>
            @if ($call['request_headers'] === [])
                <p class="mt-1 text-sm text-muted-2">—</p>
            @else
                <dl class="ltr mt-1 divide-y divide-line-2 rounded-xl border border-line-2 text-xs">
                    @foreach ($call['request_headers'] as $hName => $hValue)
                        <div class="grid grid-cols-[minmax(8rem,auto)_1fr] gap-3 px-3 py-2">
                            <dt class="font-semibold text-ink">{{ $hName }}</dt>
                            <dd class="break-all text-muted">{{ $hValue }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            @if ($call['request_body'] !== [])
                <h3 class="mt-4 text-xs font-semibold text-muted">بدنه (فرم)</h3>
                <dl class="ltr mt-1 divide-y divide-line-2 rounded-xl border border-line-2 text-xs">
                    @foreach ($call['request_body'] as $field => $value)
                        <div class="grid grid-cols-[minmax(8rem,auto)_1fr] gap-3 px-3 py-2">
                            <dt class="font-semibold text-ink">{{ $field }}</dt>
                            <dd class="break-all text-muted">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>

        {{-- ── Response ── --}}
        <section class="min-w-0 rounded-2xl bg-surface card-p shadow-xs" x-data="{ copied: false }">
            <h2 class="font-bold text-ink">پاسخ</h2>

            <h3 class="mt-4 text-xs font-semibold text-muted">هدرها</h3>
            @if ($call['response_headers'] === [])
                <p class="mt-1 text-sm text-muted-2">{{ $call['status'] === null ? 'پاسخی نیامد — خطای اتصال.' : '—' }}</p>
            @else
                <dl class="ltr mt-1 divide-y divide-line-2 rounded-xl border border-line-2 text-xs">
                    @foreach ($call['response_headers'] as $hName => $hValue)
                        <div class="grid grid-cols-[minmax(8rem,auto)_1fr] gap-3 px-3 py-2">
                            <dt class="font-semibold text-ink">{{ $hName }}</dt>
                            <dd class="break-all text-muted">{{ $hValue }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            <div class="mt-4 flex items-center justify-between gap-2">
                <h3 class="text-xs font-semibold text-muted">بدنه</h3>
                @if ($body !== null && $body !== '')
                    <button type="button"
                            @click="navigator.clipboard?.writeText($refs.body.textContent).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
                            class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2.5 text-xs font-medium text-muted hover:bg-surface-2 hover:text-ink">
                        <x-icon name="copy" class="size-4" />
                        <span x-text="copied ? 'کپی شد' : 'کپی'">کپی</span>
                    </button>
                @endif
            </div>

            @if ($body === null || $body === '')
                <p class="mt-1 text-sm text-muted-2">بدنه‌ای ثبت نشده.</p>
            @else
                @if ($call['body_truncated'])
                    <p class="mt-1 rounded-lg bg-warn-soft px-3 py-1.5 text-xs text-warn">
                        فقط بخش اول بدنه نگه داشته شده
                        ({{ $ok ? 'پاسخ موفق: پیش‌نمایش ۲KB' : 'پاسخ ناموفق: تا ۱۶KB' }} از <span class="num">{{ number_format((int) $call['bytes']) }}</span> بایت).
                    </p>
                @endif
                <pre x-ref="body" @class([
                    // `pre-wrap`: preview e boride yek khat e boland e — bedun esh
                    // scroll e ofoghi e bi-payan.
                    'ltr mt-2 max-h-[32rem] overflow-auto whitespace-pre-wrap break-all rounded-xl p-3 text-[0.6875rem] leading-5',
                    'bg-danger-soft text-danger' => ! $ok,
                    'bg-surface-2 text-ink' => $ok,
                ])>{{ $body }}</pre>
            @endif
        </section>
    </div>
</x-layouts.panel>
