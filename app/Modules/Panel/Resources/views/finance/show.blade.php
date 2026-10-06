<x-layouts.panel title="جزئیات سند" current="finance" :breadcrumbs="['پنل', 'امور مالی', 'سند #'.$row['id']]">
    <a href="{{ route('panel.finance.index') }}" wire:navigate class="text-sm text-brand">بازگشت به دفتر مالی</a>
    <h1 class="mt-2 text-2xl font-extrabold text-ink">سند #<span class="num">{{ $row['id'] }}</span></h1>
    @if ($errors->any())<p role="alert" class="mt-4 rounded-xl bg-down-soft p-3 text-down">{{ $errors->first() }}</p>@endif
    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        <section class="rounded-2xl bg-surface card-p shadow-xs">
            <h2 class="font-bold">اطلاعات تراکنش</h2>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <dt class="text-muted">کاربر</dt><dd><a href="{{ route('panel.users.show', $row['ownerId']) }}" wire:navigate>{{ $row['ownerName'] }}</a></dd>
                <dt class="text-muted">زمان</dt><dd class="num">{{ $row['at'] }}</dd>
                <dt class="text-muted">دلیل</dt><dd>{{ $row['reason'] }}</dd>
                <dt class="text-muted">مرجع</dt><dd class="ltr break-all">{{ $row['reference'] }}</dd>
                <dt class="text-muted">مبلغ</dt><dd class="num">{{ $row['amountRial'] }} ریال</dd>
                <dt class="text-muted">مانده پس از ثبت</dt><dd class="num">{{ $row['balanceRial'] }} ریال</dd>
                <dt class="text-muted">یادداشت</dt><dd>{{ $row['note'] ?? '—' }}</dd>
                <dt class="text-muted">وضعیت</dt><dd>{{ $row['isReversed'] ? 'ابطال‌شده' : 'ثبت‌شده' }}</dd>
            </dl>
        </section>
        <section class="rounded-2xl bg-surface card-p shadow-xs">
            <h2 class="font-bold">ثبت حسابداری</h2>
            <table class="mt-4 w-full text-sm">
                <thead><tr class="border-b border-line text-muted"><th class="py-2 text-start">حساب</th><th class="py-2 text-start">بدهکار</th><th class="py-2 text-start">بستانکار</th></tr></thead>
                <tbody class="divide-y divide-line-2">
                    @forelse ($journalLines as $line)
                        <tr><td class="py-2"><span class="num">{{ $line['code'] }}</span> {{ $line['name'] }}</td><td class="num py-2">{{ $line['debitRial'] }}</td><td class="num py-2">{{ $line['creditRial'] }}</td></tr>
                    @empty<tr><td colspan="3" class="py-4 text-muted">این سند در مانده افتتاحیه حسابداری ثبت شده است.</td></tr>@endforelse
                </tbody>
            </table>
        </section>
    </div>
    @can('finance.manage')
        @if ($row['reason'] === 'admin_adjust' && ! $row['isReversed'])
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <form method="POST" action="{{ route('panel.finance.reverse', $row['id']) }}" class="rounded-2xl bg-surface card-p shadow-xs">
                    @csrf
                    <h2 class="font-bold">ابطال سند</h2>
                    <p class="mt-1 text-xs text-muted">یک سند با جهت مخالف ثبت می‌شود. موجودی منفی مجاز نیست.</p>
                    <input name="note" required maxlength="255" placeholder="دلیل ابطال" class="mt-3 h-11 w-full rounded-xl border border-line px-3 text-sm">
                    <button class="mt-3 h-11 rounded-xl bg-down-soft px-4 font-semibold text-down">ثبت ابطال</button>
                </form>
                <form method="POST" action="{{ route('panel.finance.correct', $row['id']) }}" class="rounded-2xl bg-surface card-p shadow-xs">
                    @csrf
                    <h2 class="font-bold">اصلاح سند</h2>
                    <p class="mt-1 text-xs text-muted">ابطال و سند جایگزین در یک تراکنش ثبت می‌شوند.</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <select name="direction" class="h-11 rounded-xl border border-line px-3"><option value="credit">افزایش</option><option value="debit">کاهش</option></select>
                        <input name="amount_rial" required inputmode="numeric" placeholder="مبلغ (ریال)" class="num h-11 rounded-xl border border-line px-3">
                    </div>
                    <input name="note" required maxlength="255" placeholder="دلیل اصلاح" class="mt-2 h-11 w-full rounded-xl border border-line px-3">
                    <button class="mt-3 h-11 rounded-xl bg-brand px-4 font-semibold text-on-brand">ثبت اصلاح</button>
                </form>
            </div>
        @endif
    @endcan
</x-layouts.panel>
