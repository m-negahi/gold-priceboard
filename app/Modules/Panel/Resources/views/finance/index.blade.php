<x-layouts.panel title="امور مالی" current="finance" :breadcrumbs="['پنل', 'امور مالی']">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">دفتر مالی</h1>
            <p class="mt-1 text-sm text-muted">تراکنش‌های همه کیف پول‌ها و تراز حساب‌ها؛ مبلغ‌ها به ریال هستند.</p>
        </div>
        <a href="{{ route('panel.finance.export', array_merge($filters, ['page' => null])) }}" class="inline-flex h-11 items-center gap-2 rounded-xl bg-surface px-4 text-sm font-semibold text-ink ring-1 ring-line hover:bg-surface-2" wire:navigate>
            <x-icon name="download" class="size-4" /> خروجی CSV
        </a>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-panel.stat-card label="بستانکار کیف پول" :value="$totals['creditsRial']" hint="ریال" />
        <x-panel.stat-card label="بدهکار کیف پول" :value="$totals['debitsRial']" hint="ریال" />
        <x-panel.stat-card label="خالص" :value="$totals['netRial']" hint="ریال" />
        <x-panel.stat-card label="تراکنش" :value="$totals['count']" />
    </div>

    <form method="GET" action="{{ route('panel.finance.index') }}" class="mt-5 grid gap-3 rounded-2xl bg-surface p-4 shadow-xs sm:grid-cols-2 xl:grid-cols-7">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="مرجع، نام یا موبایل" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm xl:col-span-2">
        <input name="user_id" value="{{ $filters['user_id'] ?? '' }}" inputmode="numeric" placeholder="شناسه کاربر" class="num h-11 rounded-xl border border-line bg-surface px-3 text-sm">
        <select name="reason" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm">
            <option value="">همه دلایل</option>
            @foreach (['topup' => 'شارژ درگاه', 'admin_adjust' => 'اصلاح مدیر', 'admin_reversal' => 'ابطال', 'plan_purchase' => 'خرید اشتراک', 'api_call' => 'API', 'refund' => 'بازگشت'] as $reason => $label)
                <option value="{{ $reason }}" @selected(($filters['reason'] ?? '') === $reason)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="direction" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm">
            <option value="">هر دو جهت</option>
            <option value="credit" @selected(($filters['direction'] ?? '') === 'credit')>افزایش</option>
            <option value="debit" @selected(($filters['direction'] ?? '') === 'debit')>کاهش</option>
        </select>
        <select name="account_code" aria-label="حساب دفتر کل" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm">
            <option value="">همه حساب‌ها</option>
            @foreach ($trialBalance as $account)
                <option value="{{ $account['code'] }}" @selected(($filters['account_code'] ?? '') === $account['code'])>{{ $account['code'] }} · {{ $account['name'] }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="از تاریخ" class="num h-11 rounded-xl border border-line bg-surface px-3 text-sm">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="تا تاریخ" class="num h-11 rounded-xl border border-line bg-surface px-3 text-sm">
        <button class="h-11 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand xl:col-span-7">اعمال فیلتر</button>
    </form>

    @if ($errors->any())
        <p role="alert" class="mt-3 rounded-xl bg-down-soft p-3 text-sm text-down">{{ $errors->first() }}</p>
    @endif

    <section class="mt-5 overflow-hidden rounded-2xl bg-surface shadow-xs">
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

    <section class="mt-5 rounded-2xl bg-surface card-p shadow-xs">
        <h2 class="text-lg font-bold text-ink">تسویه درگاه</h2>
        <p class="mt-1 text-xs text-muted">پرداخت تأییدشده تا ثبت واریز بانکی در حساب «وجوه در راه درگاه» می‌ماند.</p>
        <div class="mt-3 space-y-3">
            @forelse ($unsettledPayments as $payment)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface-2 p-3 text-sm">
                    <div><p class="font-semibold">{{ $payment['userName'] }} · <span class="num">{{ $payment['amountRial'] }} ریال</span></p><p class="ltr text-xs text-muted">Zibal {{ $payment['trackId'] }} · {{ $payment['gatewayReference'] ?? '—' }}</p></div>
                    @can('finance.manage')
                        <form method="POST" action="{{ route('panel.finance.settle', $payment['id']) }}" class="flex flex-wrap gap-2">
                            @csrf
                            <input name="bank_reference" required maxlength="100" placeholder="شناسه واریز بانک" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm">
                            <button class="h-11 rounded-xl bg-brand px-4 font-semibold text-on-brand">ثبت تسویه</button>
                        </form>
                    @endcan
                </div>
            @empty
                <p class="text-sm text-muted">پرداخت تأییدشدهٔ تسویه‌نشده‌ای وجود ندارد.</p>
            @endforelse
        </div>
    </section>

    <section class="mt-5 rounded-2xl bg-surface card-p shadow-xs">
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

    <section class="mt-5 overflow-hidden rounded-2xl bg-surface shadow-xs">
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
</x-layouts.panel>
