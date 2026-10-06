{{-- Lista ye manba' ha (P3-06). Server-rendered — ~chand radif bishtar nist،
     datatable e AJAX-i inja overkill e. Toggle ha form e POST e sade-and. --}}

<x-layouts.panel title="منابع قیمت" current="providers" :breadcrumbs="['پنل', 'منابع قیمت']">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink md:text-3xl">منابع قیمت</h1>
            <p class="mt-1 text-sm text-muted">هر منبع یک درایور دارد؛ کلید بعد از ساخت قفل است.</p>
        </div>

        <div class="flex items-center gap-2">
            <x-panel.help key="providers" title="راهنمای منابع">
                <h3>روال افزودن منبع</h3>
                <ul>
                    <li>منبع جدید همیشه خاموش ساخته می‌شود.</li>
                    <li>یک tick دستی بزنید و «پایش زنده» را ببینید؛ رد شده نباید داشته باشد.</li>
                    <li>بعد روشنش کنید — بررسی بین‌منبعی برای اولین قیمتش فعال است.</li>
                </ul>
                <dl>
                    <dt>مقایسه</dt><dd>در جدول مقایسه سایت بیاید؟</dd>
                    <dt>تابلو</dt><dd>در تابلو TV بیاید؟</dd>
                    <dt>نمودار</dt><dd>در نمودار انتخاب‌پذیر باشد؟</dd>
                </dl>
            </x-panel.help>

            <a href="{{ route('panel.providers.create') }}"
               class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand shadow-(--bp-elev-brand) hover:bg-brand-2">
                <x-icon name="plus" class="size-4" />
                منبع جدید
            </a>
        </div>
    </div>

    <section class="mt-6 overflow-hidden rounded-2xl bg-surface shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[44rem] text-sm">
                <thead>
                    <tr class="border-b border-line text-xs font-semibold text-muted">
                        <th class="px-5 py-3 text-start font-semibold">منبع</th>
                        <th class="px-5 py-3 text-start font-semibold">درایور</th>
                        <th class="px-5 py-3 text-start font-semibold">نوع</th>
                        <th class="num px-5 py-3 text-start font-semibold">بازه / مهلت</th>
                        <th class="px-5 py-3 text-start font-semibold">سلامت</th>
                        <th class="px-5 py-3 text-start font-semibold">وضعیت</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-2">
                    @forelse ($rows as $row)
                        <tr @class(['opacity-60' => $row['deleted']])>
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink">{{ $row['name'] }}</p>
                                <p class="ltr mt-0.5 text-xs text-muted-3">{{ $row['key'] }}</p>
                            </td>
                            <td class="ltr px-5 py-3 text-xs text-muted">{{ $row['driver'] }}</td>
                            <td class="px-5 py-3">
                                <x-panel.pill :tone="$row['kind']->value === 'platform' ? 'info' : 'flat'">{{ $row['kind']->label() }}</x-panel.pill>
                            </td>
                            <td class="num px-5 py-3 text-xs text-muted">{{ $row['pollIntervalSeconds'] }}s / {{ $row['timeout'] }}s</td>
                            <td class="px-5 py-3"><x-panel.health-badge :state="$row['healthState']" /></td>
                            <td class="px-5 py-3">
                                @if ($row['deleted'])
                                    <x-panel.pill tone="down">حذف‌شده</x-panel.pill>
                                @else
                                    {{-- Toggle e is_active: POST e sade + redirect (toast az session). --}}
                                    <form method="POST" action="{{ route('panel.providers.flags', $row['key']) }}">
                                        @csrf
                                        <input type="hidden" name="is_active" value="{{ $row['isActive'] ? 0 : 1 }}">
                                        <button type="submit"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold ring-1 transition-colors {{ $row['isActive'] ? 'bg-ok-soft text-ok ring-ok/20 hover:bg-ok-soft/70' : 'bg-surface-3 text-muted ring-line hover:text-ink' }}">
                                            <span class="size-1.5 rounded-full {{ $row['isActive'] ? 'bg-up-strong' : 'bg-muted-3' }}" aria-hidden="true"></span>
                                            {{ $row['isActive'] ? 'روشن' : 'خاموش' }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-end">
                                @unless ($row['deleted'])
                                    <div class="inline-flex items-center gap-1">
                                        <a href="{{ route('panel.providers.spreads', $row['key']) }}"
                                           class="inline-flex size-9 items-center justify-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink"
                                           aria-label="اسپرد {{ $row['name'] }}" title="اسپرد">
                                            <x-icon name="tag" class="size-4" />
                                        </a>
                                        <a href="{{ route('panel.providers.edit', $row['key']) }}"
                                           class="inline-flex size-9 items-center justify-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink"
                                           aria-label="ویرایش {{ $row['name'] }}" title="ویرایش">
                                            <x-icon name="pencil" class="size-4" />
                                        </a>
                                    </div>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-muted">هنوز منبعی ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.panel>
