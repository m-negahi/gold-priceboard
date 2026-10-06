{{-- Tanzimat va salamat e har manba' dar yek kart; polling e upstream hichvaght az safhe seda nemishe. --}}
<x-layouts.panel title="منابع قیمت" current="providers" :breadcrumbs="['پنل', 'منابع قیمت']">
    <div x-data="providerMonitor({ route: @js(route('panel.providers.index')), health: @js($liveHealth) })">
    <p x-show="delayed" x-cloak class="mb-3 rounded-xl bg-warn-soft p-3 text-sm text-warn" role="status">به‌روزرسانی سلامت قطع شده است؛ آخرین وضعیت خوانده‌شده نمایش داده می‌شود.</p>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h1 class="text-2xl font-extrabold text-ink md:text-3xl">منابع قیمت</h1><p class="mt-1 text-sm text-muted">تنظیمات، سلامت و آخرین خطای هر منبع را کنار هم بررسی کنید.</p></div>
        <div class="flex flex-wrap items-center gap-2">
            <x-panel.help key="providers" title="راهنمای منابع قیمت">
                <h3>مرجع اعتماد</h3><p>پس از بررسی مستقل قیمت‌ها، یک منبع معتبر را مرجع اعتماد کنید. اولین قیمت منابع دیگر با این مرجع سنجیده می‌شود؛ تا مرجع برای آن نماد قیمت نداشته باشد، قیمت جدید پذیرفته نمی‌شود. منبع دستی مرجع اعتماد نیست.</p>
                <h3>تنظیم و پایش</h3><p>ویرایش برای زمان‌بندی، ترتیب، واحد ورودی و نمایش منبع است. اسپرد روی قیمت خوانده‌شده اعمال می‌شود و قیمت خام را تغییر نمی‌دهد. خطا و شکست پیاپی را در همین کارت ببینید؛ جزئیات هر فراخوانی در پایش موجود است.</p>
            </x-panel.help>
            <a wire:navigate href="{{ route('panel.monitor.ticks') }}" class="inline-flex h-11 items-center gap-2 rounded-xl border border-line px-4 text-sm font-medium hover:bg-surface-2"><x-icon name="eye" class="size-4" />پایش زنده</a>
            @can('providers.manage')
            <a wire:navigate href="{{ route('panel.providers.create') }}" class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand hover:bg-brand-2"><x-icon name="plus" class="size-4" />منبع جدید</a>
            @endcan
        </div>
    </div>
    @unless ($hasTrustAnchor)
        <div class="mt-5 rounded-xl bg-warn-soft p-4 text-sm text-warn" role="status">مرجع اعتماد فعالی انتخاب نشده است. قبل از روشن کردن یک منبع جدید، قیمت‌های یک منبع معتبر را بررسی و آن را مرجع اعتماد کنید.</div>
    @endunless
    @if ($errors->any())
        <p class="mt-3 text-sm text-danger" role="alert">{{ $errors->first() }}</p>
    @endif
    <section class="mt-5 grid gap-4 xl:grid-cols-2" aria-label="منابع و سلامت">
        @forelse ($rows as $row)
            <article @class(['rounded-2xl bg-surface card-p shadow-xs', 'opacity-60' => $row['deleted']]) data-provider-row="{{ $row['key'] }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 class="text-base font-bold text-ink">{{ $row['name'] }}</h2><p class="ltr mt-1 text-xs text-muted"><span>{{ $row['key'] }}</span> · <span>{{ $row['driver'] }}</span></p></div>
                    <div class="flex flex-wrap items-center gap-2"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold" :class="tone(@js($row['key']))" x-text="health[@js($row['key'])]?.stateLabel || 'نامشخص'">{{ ($row['health']['state'] ?? $row['healthState'])->label() }}</span>
                        @if ($row['deleted'])<x-panel.pill tone="down">حذف‌شده</x-panel.pill>@elseif (! $row['isActive'])<x-panel.pill tone="flat">خاموش</x-panel.pill>@endif
                        @if ($row['isTrustAnchor'])<x-panel.pill tone="info">مرجع اعتماد</x-panel.pill>@endif
                        <span x-show="health[@js($row['key'])]?.retried" x-cloak class="rounded-full bg-warn-soft px-2 py-1 text-xs text-warn">تلاش دوباره</span>
                    </div>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-surface-2 p-3 sm:grid-cols-4">
                    <div><dt class="text-xs text-muted">تأخیر</dt><dd class="num mt-1 text-sm font-semibold"><span x-text="health[@js($row['key'])]?.latencyMs ?? 0">{{ $row['health']['latencyMs'] ?? 0 }}</span> ms</dd></div>
                    <div><dt class="text-xs text-muted">نماد / ردشده</dt><dd class="num mt-1 text-sm font-semibold"><span x-text="health[@js($row['key'])]?.symbolCount ?? 0">{{ $row['health']['symbolCount'] ?? 0 }}</span> / <span class="text-warn" x-text="health[@js($row['key'])]?.rejectedCount ?? 0">{{ $row['health']['rejectedCount'] ?? 0 }}</span></dd></div>
                    <div><dt class="text-xs text-muted">شکست پیاپی</dt><dd class="num mt-1 text-sm font-semibold" :class="health[@js($row['key'])]?.consecutiveFailures > 0 && 'text-danger'" x-text="health[@js($row['key'])]?.consecutiveFailures ?? 0">{{ $row['health']['consecutiveFailures'] ?? 0 }}</dd></div>
                    <div><dt class="text-xs text-muted">ترتیب</dt><dd class="num mt-1 text-sm font-semibold">{{ $row['sort'] }}</dd></div>
                </dl>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted"><span>فاصله / مهلت: <span class="num">{{ $row['pollIntervalSeconds'] }} / {{ $row['timeout'] }} s</span></span><span>آخرین موفق: <span class="num" x-text="health[@js($row['key'])]?.lastSuccessDisplay || '—'">{{ $row['lastSuccessDisplay'] }}</span></span></div>
                <div x-show="health[@js($row['key'])]?.error" x-cloak class="mt-3 rounded-xl bg-danger-soft p-3"><p class="text-xs font-semibold text-danger">آخرین خطا</p><p class="ltr mt-1 break-all text-start text-xs text-danger" x-text="health[@js($row['key'])]?.error">{{ $row['health']['error'] ?? '' }}</p></div>
                <p x-show="!health[@js($row['key'])]?.error" class="mt-3 text-xs text-muted-3">خطایی ثبت نشده است.</p>
                @unless ($row['deleted'])
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line-2 pt-3">
                        <div class="flex flex-wrap items-center gap-1.5"><x-panel.pill tone="flat">{{ $row['kind']->label() }}</x-panel.pill>@if ($row['inComparison'])<x-panel.pill tone="flat">مقایسه</x-panel.pill>@endif @if ($row['onBoard'])<x-panel.pill tone="flat">تابلو</x-panel.pill>@endif @if ($row['inChart'])<x-panel.pill tone="flat">نمودار</x-panel.pill>@endif</div>
                        @can('providers.manage')
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('panel.providers.flags', $row['key']) }}">@csrf<input type="hidden" name="is_active" value="{{ $row['isActive'] ? 0 : 1 }}"><button type="submit" class="h-11 rounded-xl border border-line px-3 text-sm font-medium hover:bg-surface-2">{{ $row['isActive'] ? 'خاموش کردن' : 'روشن کردن' }}</button></form>
                            <a wire:navigate href="{{ route('panel.providers.spreads', $row['key']) }}" class="inline-flex h-11 items-center gap-1.5 rounded-xl px-3 text-sm text-muted hover:bg-surface-2" aria-label="اسپرد {{ $row['name'] }}"><x-icon name="tag" class="size-4" />اسپرد</a>
                            <a wire:navigate href="{{ route('panel.providers.edit', $row['key']) }}" class="inline-flex h-11 items-center gap-1.5 rounded-xl bg-brand-soft px-3 text-sm font-semibold text-brand-2" aria-label="ویرایش {{ $row['name'] }}"><x-icon name="pencil" class="size-4" />ویرایش</a>
                        </div>
                        @endcan
                    </div>
                @endunless
            </article>
        @empty
            <div class="rounded-2xl bg-surface p-8 text-center text-muted">هنوز منبعی ثبت نشده است.</div>
        @endforelse
    </section>
    </div>
</x-layouts.panel>
