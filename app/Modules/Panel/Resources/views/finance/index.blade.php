{{-- «امور مالی» — 6 tab (khaste ye sahebe proje، 2026-10-06). Tab e fa'al az
     query e `tab` miad (form haye GET un ro negah midaran) va ba `sync` tu
     hash ham mimune. Mablagh ha RIAL-and. --}}
<x-layouts.panel title="امور مالی" current="finance" :breadcrumbs="['پنل', 'امور مالی']">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">امور مالی</h1>
            <p class="mt-1 text-sm text-muted">گزارش ماهانه، تراکنش‌های کیف پول، دفتر کل، تسویه درگاه، فاکتورها و شارژ کیف پول؛ مبلغ‌ها به ریال.</p>
        </div>
    </div>

    @if (session('status'))
        <p role="status" class="mt-4 rounded-xl bg-ok-soft p-3 text-sm text-ok" data-flash>{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p role="alert" class="mt-4 rounded-xl bg-down-soft p-3 text-sm text-down">{{ $errors->first() }}</p>
    @endif

    <x-panel.tabs :tabs="['report' => 'گزارش ماهانه', 'ledger' => 'تراکنش‌ها', 'journal' => 'دفتر کل', 'settlement' => 'تسویه درگاه', 'invoices' => 'فاکتورها', 'wallet' => 'شارژ کیف پول']"
                  :default="$tab" sync label="بخش‌های مالی" class="mt-5">

        {{-- ── 1. Gozaresh e mahane ─────────────────────────────── --}}
        <x-panel.tab-panel key="report">
            <section class="rounded-2xl bg-surface card-p shadow-xs" data-report-period>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink"><x-icon name="calendar" class="size-4 text-brand" />بازهٔ گزارش</span>
                    <span class="num rounded-xl bg-surface-2 px-3 py-1.5 text-sm text-ink">{{ $report['rangeLabel'] }}</span>
                    <span class="flex-1"></span>
                    @foreach ($periods as $key => $label)
                        <a href="{{ route('panel.finance.index', ['tab' => 'report', 'period' => $key]) }}" wire:navigate data-period="{{ $key }}"
                           @class(['inline-flex h-9 items-center rounded-full border px-3 text-xs font-semibold', 'border-brand bg-brand-soft text-brand-2' => ! $report['custom'] && $report['period'] === $key, 'border-line text-muted hover:border-line-3 hover:text-ink' => $report['custom'] || $report['period'] !== $key])>{{ $label }}</a>
                    @endforeach
                </div>
                <form method="GET" action="{{ route('panel.finance.index') }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                    <input type="hidden" name="tab" value="report">
                    <x-form.jalali-date name="from" label="از تاریخ" :value="$filters['from'] ?? ''" />
                    <x-form.jalali-date name="to" label="تا تاریخ" :value="$filters['to'] ?? ''" />
                    <button class="h-11 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand">بازهٔ دلخواه</button>
                </form>
                <p class="mt-2 text-xs text-muted"><span class="num">{{ count($report['rows']) }}</span> ماه — هر سطر یک ماه شمسی؛ کلیک روی ماه، تراکنش‌های همان ماه را باز می‌کند.</p>
            </section>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <x-panel.stat-card label="شارژ درگاه" :value="$report['totals']['topupRial']" hint="ریال · بازه" />
                <x-panel.stat-card label="مصرف API" :value="$report['totals']['apiCallRial']" hint="ریال · بازه" />
                <x-panel.stat-card label="خرید اشتراک" :value="$report['totals']['planPurchaseRial']" hint="ریال · بازه" />
                <x-panel.stat-card label="خالص کیف پول‌ها" :value="$report['totals']['netRial']" hint="بستانکار − بدهکار" />
            </div>

            <section class="mt-4 overflow-hidden rounded-2xl bg-surface shadow-xs">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line p-4">
                    <div><h2 class="font-bold text-ink">گزارش مالی ماهانه</h2><p class="mt-1 text-xs text-muted">شارژ، مصرف، اشتراک و بازگشت — ماه به ماه، از اسناد واقعی کیف پول.</p></div>
                    <a href="{{ route('panel.finance.monthly-export', array_filter(['period' => $report['period'], 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null])) }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-line px-3 text-sm font-semibold text-ink hover:bg-surface-2"><x-icon name="download" class="size-4" />خروجی</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[64rem] text-sm" data-monthly-report>
                        <thead class="text-xs text-muted">
                            <tr>
                                <th class="px-4 py-3 text-start">ماه</th>
                                @foreach ($report['columns'] as $label)<th class="px-4 py-3 text-center">{{ $label }}</th>@endforeach
                                <th class="px-4 py-3 text-center">خالص</th><th class="px-4 py-3 text-center">تراکنش</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-2">
                            @foreach ($report['rows'] as $row)
                                <tr data-month="{{ $row['key'] }}">
                                    <td class="px-4 py-3"><a href="{{ route('panel.finance.index', ['tab' => 'ledger', 'from' => $row['from'], 'to' => $row['to']]) }}" wire:navigate class="num font-semibold text-brand hover:underline">{{ $row['label'] }}</a></td>
                                    @foreach (array_keys($report['columns']) as $key)<td class="num px-4 py-3 text-center">{{ $row[$key] === 0 ? '۰' : $row[$key.'Rial'] }}</td>@endforeach
                                    <td @class(['num px-4 py-3 text-center font-semibold', 'text-ok' => $row['netSign'] > 0, 'text-down' => $row['netSign'] < 0])>{{ $row['netRial'] }}</td>
                                    <td class="num px-4 py-3 text-center text-muted">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-brand-soft/40 font-bold" data-report-totals>
                                <td class="px-4 py-3"><span>جمع</span> <span class="text-xs font-normal text-muted">(بازه — ریال)</span></td>
                                @foreach (array_keys($report['columns']) as $key)<td class="num px-4 py-3 text-center">{{ $report['totals'][$key.'Rial'] }}</td>@endforeach
                                <td class="num px-4 py-3 text-center">{{ $report['totals']['netRial'] }}</td>
                                <td class="num px-4 py-3 text-center">{{ $report['totals']['count'] }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </x-panel.tab-panel>

        {{-- ── 2. Tarakonesh ha ─────────────────────────────────── --}}
        <x-panel.tab-panel key="ledger">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <x-panel.stat-card label="بستانکار کیف پول" :value="$totals['creditsRial']" hint="ریال" />
                <x-panel.stat-card label="بدهکار کیف پول" :value="$totals['debitsRial']" hint="ریال" />
                <x-panel.stat-card label="خالص" :value="$totals['netRial']" hint="ریال" />
                <x-panel.stat-card label="تراکنش" :value="$totals['count']" />
            </div>

            <form method="GET" action="{{ route('panel.finance.index') }}" class="mt-4 grid gap-3 rounded-2xl bg-surface p-4 shadow-xs sm:grid-cols-2 sm:items-end xl:grid-cols-7">
                <input type="hidden" name="tab" value="ledger">
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="مرجع، نام یا موبایل" aria-label="جستجو" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm xl:col-span-2">
                <input name="user_id" value="{{ $filters['user_id'] ?? '' }}" inputmode="numeric" placeholder="شناسه کاربر" aria-label="شناسه کاربر" class="num h-11 rounded-xl border border-line bg-surface px-3 text-sm">
                <select name="reason" aria-label="دلیل" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm">
                    <option value="">همه دلایل</option>
                    @foreach (['topup' => 'شارژ درگاه', 'admin_adjust' => 'اصلاح مدیر', 'admin_reversal' => 'ابطال', 'plan_purchase' => 'خرید اشتراک', 'api_call' => 'API', 'refund' => 'بازگشت'] as $reason => $label)
                        <option value="{{ $reason }}" @selected(($filters['reason'] ?? '') === $reason)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="direction" aria-label="جهت" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm">
                    <option value="">هر دو جهت</option>
                    <option value="credit" @selected(($filters['direction'] ?? '') === 'credit')>افزایش</option>
                    <option value="debit" @selected(($filters['direction'] ?? '') === 'debit')>کاهش</option>
                </select>
                {{-- Tarikh e shamsi ba picker e kit؛ hidden input ISO mide. --}}
                <x-form.jalali-date name="from" label="از تاریخ" :value="$filters['from'] ?? ''" />
                <x-form.jalali-date name="to" label="تا تاریخ" :value="$filters['to'] ?? ''" />
                <div class="flex gap-2 xl:col-span-7">
                    <button class="h-11 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand">اعمال فیلتر</button>
                    <a href="{{ route('panel.finance.export', array_merge($filters, ['page' => null, 'tab' => null])) }}" class="inline-flex h-11 items-center gap-2 rounded-xl border border-line bg-surface px-4 text-sm font-semibold text-ink hover:bg-surface-2"><x-icon name="download" class="size-4" />خروجی CSV</a>
                </div>
            </form>

            <section class="mt-4 overflow-hidden rounded-2xl bg-surface shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[56rem] text-sm">
                        <thead class="border-b border-line text-xs text-muted">
                            <tr>
                                <th class="px-4 py-3 text-start">سند</th><th class="px-4 py-3 text-start">زمان</th>
                                <th class="px-4 py-3 text-start">کاربر</th><th class="px-4 py-3 text-start">دلیل</th>
                                <th class="px-4 py-3 text-start">مبلغ (ریال)</th><th class="px-4 py-3 text-start">مانده (ریال)</th>
                                <th class="px-4 py-3 text-start">وضعیت</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-2">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3"><a href="{{ route('panel.finance.show', $row['id']) }}" class="num font-semibold text-brand hover:underline" wire:navigate>#{{ $row['id'] }}</a></td>
                                    <td class="num px-4 py-3 text-muted">{{ $row['at'] }}</td>
                                    <td class="px-4 py-3"><a href="{{ route('panel.users.show', $row['ownerId']) }}" class="text-ink hover:text-brand" wire:navigate>{{ $row['ownerName'] }}</a><p class="num text-xs text-muted">{{ $row['ownerPhone'] }}</p></td>
                                    <td class="px-4 py-3 text-ink">{{ $row['reason'] }}<p class="ltr max-w-36 truncate text-xs text-muted" title="{{ $row['reference'] }}">{{ $row['reference'] }}</p></td>
                                    <td @class(['num px-4 py-3 font-bold', 'text-ok' => $row['direction'] === 'credit', 'text-down' => $row['direction'] === 'debit'])>{{ $row['direction'] === 'credit' ? '+' : '−' }}{{ $row['amountRial'] }}</td>
                                    <td class="num px-4 py-3">{{ $row['balanceRial'] }}</td>
                                    <td class="px-4 py-3">@if ($row['isReversed'])<x-panel.pill tone="down">ابطال‌شده</x-panel.pill>@else<x-panel.pill tone="flat">ثبت‌شده</x-panel.pill>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-8 text-center text-muted">تراکنشی پیدا نشد.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($rows->lastPage() > 1)
                    <div class="flex justify-between border-t border-line p-4 text-sm">
                        <span class="num">صفحه {{ $rows->currentPage() }} از {{ $rows->lastPage() }}</span>
                        <div class="flex gap-3">
                            @if ($rows->previousPageUrl())<a href="{{ $rows->previousPageUrl() }}" wire:navigate>قبلی</a>@endif
                            @if ($rows->nextPageUrl())<a href="{{ $rows->nextPageUrl() }}" wire:navigate>بعدی</a>@endif
                        </div>
                    </div>
                @endif
            </section>
        </x-panel.tab-panel>

        {{-- ── 3. Daftar e kol ──────────────────────────────────── --}}
        <x-panel.tab-panel key="journal">
            <section class="rounded-2xl bg-surface card-p shadow-xs">
                <h2 class="text-lg font-bold text-ink">تراز آزمایشی</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[38rem] text-sm">
                        <thead class="border-b border-line text-xs text-muted"><tr><th class="py-2 text-start">حساب</th><th class="py-2 text-start">بدهکار (ریال)</th><th class="py-2 text-start">بستانکار (ریال)</th><th class="py-2 text-start">مانده</th></tr></thead>
                        <tbody class="divide-y divide-line-2">
                            @foreach ($trialBalance as $account)
                                <tr><td class="py-2"><span class="num text-muted">{{ $account['code'] }}</span> {{ $account['name'] }}</td><td class="num py-2">{{ $account['debits'] }}</td><td class="num py-2">{{ $account['credits'] }}</td><td class="num py-2">{{ $account['balance'] }} {{ $account['balanceSide'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <form method="GET" action="{{ route('panel.finance.index') }}" class="mt-4 grid gap-3 rounded-2xl bg-surface p-4 shadow-xs sm:grid-cols-2 sm:items-end xl:grid-cols-5">
                <input type="hidden" name="tab" value="journal">
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="شرح، منبع، نام یا موبایل" aria-label="جستجو" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm">
                <select name="account_code" aria-label="حساب دفتر کل" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm">
                    <option value="">همه حساب‌ها</option>
                    @foreach ($trialBalance as $account)
                        <option value="{{ $account['code'] }}" @selected(($filters['account_code'] ?? '') === $account['code'])>{{ $account['code'] }} · {{ $account['name'] }}</option>
                    @endforeach
                </select>
                <x-form.jalali-date name="from" label="از تاریخ" :value="$filters['from'] ?? ''" />
                <x-form.jalali-date name="to" label="تا تاریخ" :value="$filters['to'] ?? ''" />
                <button class="h-11 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand">اعمال فیلتر</button>
            </form>

            <section class="mt-4 overflow-hidden rounded-2xl bg-surface shadow-xs">
                <div class="p-4">
                    <h2 class="text-lg font-bold text-ink">دفتر کل حساب‌ها</h2>
                    <p class="mt-1 text-xs text-muted">هر ردیف، یک بدهکار یا بستانکار از سند دوطرفه ثبت‌شده است.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[62rem] text-sm">
                        <thead class="border-y border-line text-xs text-muted"><tr>
                            <th class="px-4 py-3 text-start">سند / زمان</th><th class="px-4 py-3 text-start">حساب</th><th class="px-4 py-3 text-start">کاربر</th><th class="px-4 py-3 text-start">شرح / منبع</th><th class="px-4 py-3 text-start">بدهکار (ریال)</th><th class="px-4 py-3 text-start">بستانکار (ریال)</th>
                        </tr></thead>
                        <tbody class="divide-y divide-line-2">
                            @forelse ($journalRows as $line)
                                <tr>
                                    <td class="num px-4 py-3">#{{ $line['id'] }}<p class="text-xs text-muted">{{ $line['at'] }}</p></td>
                                    <td class="px-4 py-3"><span class="num text-muted">{{ $line['code'] }}</span> {{ $line['account'] }}</td>
                                    <td class="px-4 py-3">{{ $line['user'] ?: '—' }} @if ($line['userId'])<span class="num text-xs text-muted">#{{ $line['userId'] }}</span>@endif</td>
                                    <td class="px-4 py-3">{{ $line['memo'] }}<p class="ltr max-w-48 truncate text-xs text-muted" title="{{ $line['source'] }}">{{ $line['source'] }}</p></td>
                                    <td class="num px-4 py-3">{{ $line['debitRial'] }}</td><td class="num px-4 py-3">{{ $line['creditRial'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-8 text-center text-muted">سندی برای این فیلتر پیدا نشد.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($journalRows->lastPage() > 1)
                    <div class="flex justify-between border-t border-line p-4 text-sm">
                        <span class="num">صفحه {{ $journalRows->currentPage() }} از {{ $journalRows->lastPage() }}</span>
                        <div class="flex gap-3">
                            @if ($journalRows->previousPageUrl())<a href="{{ $journalRows->previousPageUrl() }}" wire:navigate>قبلی</a>@endif
                            @if ($journalRows->nextPageUrl())<a href="{{ $journalRows->nextPageUrl() }}" wire:navigate>بعدی</a>@endif
                        </div>
                    </div>
                @endif
            </section>
        </x-panel.tab-panel>

        {{-- ── 4. Tasviye ye dargah ─────────────────────────────── --}}
        <x-panel.tab-panel key="settlement">
            <section class="rounded-2xl bg-surface card-p shadow-xs">
                <h2 class="text-lg font-bold text-ink">تسویه درگاه</h2>
                <p class="mt-1 text-xs text-muted">پرداخت تأییدشده تا ثبت واریز بانکی در حساب «وجوه در راه درگاه» می‌ماند.</p>
                <div class="mt-3 space-y-3">
                    @forelse ($unsettledPayments as $payment)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface-2 p-3 text-sm">
                            <div><p class="font-semibold">{{ $payment['userName'] }} · <span class="num">{{ $payment['amountRial'] }} ریال</span></p><p class="ltr text-xs text-muted">Zibal {{ $payment['trackId'] }} · {{ $payment['gatewayReference'] ?? '—' }}</p></div>
                            @can('finance.manage')
                                <form method="POST" action="{{ route('panel.finance.settle', $payment['id']) }}" class="flex flex-wrap gap-2">
                                    @csrf
                                    <input name="bank_reference" required maxlength="100" placeholder="شناسه واریز بانک" aria-label="شناسه واریز بانک" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm">
                                    <button class="h-11 rounded-xl bg-brand px-4 font-semibold text-on-brand">ثبت تسویه</button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="text-sm text-muted">پرداخت تأییدشدهٔ تسویه‌نشده‌ای وجود ندارد.</p>
                    @endforelse
                </div>
            </section>
        </x-panel.tab-panel>

        {{-- ── 5. Factor ha ─────────────────────────────────────── --}}
        <x-panel.tab-panel key="invoices">
            @can('finance.manage')
            <section class="rounded-2xl bg-surface card-p shadow-xs" data-issuable>
                <h2 class="text-lg font-bold text-ink">صدور فاکتور رسمی</h2>
                <p class="mt-1 text-xs text-muted">پرداخت‌ها و اشتراک‌های کاربرانِ «تأیید فاکتور» که هنوز فاکتور ندارند. فاکتور بعد از صدور تغییرناپذیر است.</p>
                <div class="mt-3 space-y-2">
                    @forelse ($issuable as $item)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface-2 p-3 text-sm">
                            <div><p class="font-semibold">{{ $item['userName'] }} · {{ $item['description'] }}</p><p class="num text-xs text-muted">{{ $item['amountRial'] }} ریال · {{ $item['at'] }} · {{ $item['kindLabel'] }}</p></div>
                            <form method="POST" action="{{ route('panel.finance.invoices.issue') }}">
                                @csrf
                                <input type="hidden" name="kind" value="{{ $item['kind'] }}"><input type="hidden" name="source" value="{{ $item['id'] }}">
                                <button class="h-10 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand">صدور فاکتور</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-muted">سند قابل صدوری وجود ندارد.</p>
                    @endforelse
                </div>
            </section>
            @endcan

            <section class="mt-4 overflow-hidden rounded-2xl bg-surface shadow-xs">
                <div class="p-4"><h2 class="text-lg font-bold text-ink">فاکتورهای صادرشده</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[48rem] text-sm" data-invoices>
                        <thead class="border-y border-line text-xs text-muted"><tr>
                            <th class="px-4 py-3 text-start">شماره</th><th class="px-4 py-3 text-start">نوع</th><th class="px-4 py-3 text-start">خریدار</th><th class="px-4 py-3 text-start">مبلغ (ریال)</th><th class="px-4 py-3 text-start">صدور</th><th class="px-4 py-3 text-start"></th>
                        </tr></thead>
                        <tbody class="divide-y divide-line-2">
                            @forelse ($invoices as $invoice)
                                <tr>
                                    <td class="num px-4 py-3 font-semibold">{{ $invoice['number'] }}</td>
                                    <td class="px-4 py-3">{{ $invoice['kindLabel'] }}</td>
                                    <td class="px-4 py-3"><a href="{{ route('panel.users.show', $invoice['userId']) }}" class="text-ink hover:text-brand" wire:navigate>{{ $invoice['userName'] }}</a><p class="num text-xs text-muted">{{ $invoice['userPhone'] }}</p></td>
                                    <td class="num px-4 py-3">{{ $invoice['amountRial'] }}</td>
                                    <td class="num px-4 py-3 text-muted">{{ $invoice['issuedAt'] }}</td>
                                    <td class="px-4 py-3 text-end"><a href="{{ route('panel.finance.invoice', $invoice['id']) }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-line px-3 text-xs font-semibold hover:bg-surface-2"><x-icon name="receipt" class="size-3.5" />مشاهده / چاپ</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-8 text-center text-muted">هنوز فاکتوری صادر نشده.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($invoices->lastPage() > 1)
                    <div class="flex justify-between border-t border-line p-4 text-sm">
                        <span class="num">صفحه {{ $invoices->currentPage() }} از {{ $invoices->lastPage() }}</span>
                        <div class="flex gap-3">
                            @if ($invoices->previousPageUrl())<a href="{{ $invoices->previousPageUrl() }}" wire:navigate>قبلی</a>@endif
                            @if ($invoices->nextPageUrl())<a href="{{ $invoices->nextPageUrl() }}" wire:navigate>بعدی</a>@endif
                        </div>
                    </div>
                @endif
            </section>
        </x-panel.tab-panel>

        {{-- ── 6. Sharj e kif pool ──────────────────────────────── --}}
        <x-panel.tab-panel key="wallet">
            <section class="rounded-2xl bg-surface card-p shadow-xs">
                <h2 class="text-lg font-bold text-ink">شارژ / کسر کیف پول</h2>
                <p class="mt-1 text-xs text-muted">کاربر را با شماره موبایل یا شناسه پیدا کنید. هر ثبت یک سند دوطرفه در دفتر کل می‌سازد و قابل ابطال است.</p>
                @can('finance.manage')
                    <form method="POST" action="{{ route('panel.finance.wallet-adjust') }}" class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-[1fr_9rem_12rem_1fr_auto] xl:items-end" data-wallet-adjust>
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $walletAdjustKey }}">
                        <x-form.field name="user" label="کاربر (موبایل یا شناسه)" required>
                            <x-form.input name="user" ltr inputmode="numeric" autocomplete="off" :value="old('user')" placeholder="09121234567" />
                        </x-form.field>
                        <x-form.field name="direction" label="نوع">
                            <x-form.select name="direction" :options="['credit' => 'شارژ (+)', 'debit' => 'کسر (−)']" :value="old('direction', 'credit')" />
                        </x-form.field>
                        <x-form.field name="amount" label="مبلغ (ریال)" required>
                            <x-form.money name="amount" :value="old('amount')" maxlength="32" placeholder="1,250,000" />
                        </x-form.field>
                        <x-form.field name="note" label="دلیل" required>
                            <x-form.input name="note" :value="old('note')" maxlength="255" />
                        </x-form.field>
                        <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand hover:bg-brand-2"><x-icon name="wallet" class="size-4" />ثبت</button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-muted">دسترسی ثبت تراکنش ندارید.</p>
                @endcan
            </section>
        </x-panel.tab-panel>
    </x-panel.tabs>
</x-layouts.panel>
