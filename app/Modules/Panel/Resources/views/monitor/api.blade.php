{{-- Inspector e call haye API (P4-11) — mini-Telescope: request/response/
     payload/hazine/khata، 200 call e akhar. --}}

@php
    $clock = static fn (?string $iso): string => $iso === null
        ? '—'
        : \Carbon\CarbonImmutable::parse($iso)->setTimezone('Asia/Tehran')->format('H:i:s');

    // Response ro khanа kon: age JSON e motabar bud، pretty + unescape
    // (vagarna raghame Farsi/matn `\uXXXX` dide mishe — mesle Telescope na).
    $pretty = static function (?string $raw): ?string {
        if ($raw === null || $raw === '') {
            return $raw;
        }

        $decoded = json_decode($raw, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return $raw; // JSON nist — kham neshun bede.
        }

        return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    };

    $tone = static fn (int $status): string => match (true) {
        $status >= 500 => 'down',
        $status === 402 || $status === 403 || $status === 429 => 'warn',
        $status === 401 => 'warn',
        $status >= 400 => 'flat',
        $status === 304 => 'info',
        default => 'ok',
    };
@endphp

<x-layouts.panel title="پایش API" current="monitor" :breadcrumbs="['پنل', 'پایش زنده', 'API']">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink md:text-3xl">پایش API</h1>
            <p class="mt-1 text-sm text-muted">هر فراخوانی مشتری: وضعیت، هزینه، مدت و payload — خطاها با پاسخ کامل.</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('panel.monitor.ticks') }}"
               class="inline-flex h-10 items-center gap-2 rounded-xl border border-line px-4 text-sm font-medium text-ink hover:bg-surface-2">
                <x-icon name="eye" class="size-4" />
                پایش tick
            </a>

            <x-panel.help key="monitor-api" title="راهنمای پایش API">
                <h3>وضعیت‌ها</h3>
                <dl>
                    <dt>304</dt><dd>داده تغییری نکرده (ETag) — هزینه برگشت می‌خورد.</dd>
                    <dt>401</dt><dd>امضای HMAC نامعتبر — ساعت کلاینت، nonce تکراری یا secret اشتباه.</dd>
                    <dt>402</dt><dd>اعتبار کیف پول کافی نیست.</dd>
                    <dt>429</dt><dd>سقف روزانه پلن پر شده.</dd>
                    <dt>503</dt><dd>زیرساخت پرداخت در دسترس نبود — عمدا سرویس نمی‌دهیم (fail-closed).</dd>
                </dl>
            </x-panel.help>
        </div>
    </div>

    <div class="mt-6 space-y-2">
        @forelse ($calls as $call)
            @php $status = (int) ($call['status'] ?? 0); @endphp
            <details class="rounded-2xl bg-surface shadow-xs">
                <summary class="flex cursor-pointer select-none flex-wrap items-center gap-3 px-4 py-3">
                    <span class="num text-xs font-bold text-ink">{{ $clock($call['at'] ?? null) }}</span>
                    <x-panel.pill :tone="$tone($status)"><span class="num">{{ $status }}</span></x-panel.pill>
                    <span class="ltr num min-w-0 flex-1 truncate text-xs text-ink">
                        {{ $call['method'] ?? '' }} {{ $call['uri'] ?? '' }}
                    </span>
                    <span class="ltr text-xs text-muted-3">{{ $call['client_id'] !== '' ? $call['client_id'] : 'بدون کلاینت' }}</span>
                    @if (($call['cost'] ?? null) !== null && $call['cost'] !== '0')
                        <x-panel.pill tone="flat"><span class="num">{{ number_format((int) $call['cost']) }}</span> ریال</x-panel.pill>
                    @endif
                    <span class="num text-xs text-muted-2">{{ number_format((int) ($call['duration_ms'] ?? 0)) }}ms</span>
                </summary>

                <div class="space-y-3 border-t border-line-2 px-4 py-3 text-xs">
                    <dl class="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                        <div class="flex gap-2"><dt class="text-muted">IP:</dt><dd class="ltr num">{{ $call['ip'] ?? '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="text-muted">Request-Id:</dt><dd class="ltr num">{{ ($call['request_id'] ?? '') !== '' ? $call['request_id'] : '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="text-muted">ETag:</dt><dd class="ltr num">{{ $call['etag'] ?? '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="text-muted">مانده بعد از کسر:</dt><dd class="num">{{ ($call['balance'] ?? null) !== null ? number_format((int) $call['balance']) : '—' }}</dd></div>
                    </dl>

                    @if (($call['request_headers'] ?? []) !== [])
                        <div>
                            <p class="mb-1 font-medium text-muted">هدرهای درخواست:</p>
                            <pre class="ltr overflow-x-auto rounded-lg bg-surface-2 p-3 text-[0.6875rem] leading-5">@foreach ((array) $call['request_headers'] as $hName => $hVal){{ $hName }}: {{ $hVal }}
@endforeach</pre>
                        </div>
                    @endif

                    @if (($call['request_body'] ?? null) !== null)
                        <div>
                            <p class="mb-1 font-medium text-muted">Payload درخواست:</p>
                            <pre class="ltr overflow-x-auto rounded-lg bg-surface-2 p-3 text-[0.6875rem] leading-5">{{ $call['request_body'] }}</pre>
                        </div>
                    @endif

                    @if (($call['response'] ?? null) !== null)
                        <div>
                            <p class="mb-1 font-medium text-muted">پاسخ:</p>
                            <pre class="ltr max-h-80 overflow-auto rounded-lg p-3 text-[0.6875rem] leading-5 {{ $status >= 400 ? 'bg-danger-soft text-danger' : 'bg-surface-2' }}">{{ $pretty($call['response']) }}</pre>
                        </div>
                    @elseif ($status === 304)
                        <p class="text-muted-2">۳۰۴ — بدنه‌ای ندارد (داده تغییری نکرده، هزینه برگشت خورد).</p>
                    @endif
                </div>
            </details>
        @empty
            <section class="rounded-2xl bg-surface card-p py-10 text-center text-muted shadow-xs">
                هنوز فراخوانی‌ای ثبت نشده — اولین درخواست مشتری این‌جا دیده می‌شود.
            </section>
        @endforelse
    </div>
</x-layouts.panel>
