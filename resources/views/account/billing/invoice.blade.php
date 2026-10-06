<x-layouts.site :title="($invoice['kind'] === 'subscription' ? 'فاکتور ' : 'رسید ').$invoice['number']" :chrome="false">
    <div class="container-site py-8">
        <div class="flex items-center justify-between gap-3 print:hidden">
            <a href="{{ route('account.billing.wallet') }}" wire:navigate class="text-sm text-brand">بازگشت به کیف پول</a>
            <button type="button" x-data x-cloak @click="window.print()" class="h-11 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand">چاپ سند</button>
        </div>
        <article class="mt-5 rounded-2xl bg-surface card-p shadow-xs">
            <div class="flex flex-wrap justify-between gap-4 border-b border-line pb-5">
                <div><h1 class="text-2xl font-extrabold text-ink">{{ $invoice['kind'] === 'subscription' ? 'فاکتور اشتراک API' : 'رسید افزایش اعتبار کیف پول' }}</h1><p class="mt-1 text-sm text-muted">ثبت داخلی؛ به سامانه مالیاتی ارسال نشده است.</p></div>
                <dl class="text-sm"><dt class="text-muted">شماره سند</dt><dd class="num font-bold">{{ $invoice['number'] }}</dd><dt class="mt-2 text-muted">زمان صدور</dt><dd class="num">{{ $invoice['issuedAt'] }}</dd></dl>
            </div>
            <section class="mt-5"><h2 class="font-bold">خریدار</h2><p class="mt-2 text-sm">{{ $invoice['buyer']['profile']['registered_name'] ?? $invoice['buyer']['name'] ?? '—' }}</p><p class="num text-sm text-muted">{{ $invoice['buyer']['phone'] ?? '—' }} · {{ $invoice['buyer']['national_code'] ?? '—' }}</p></section>
            <table class="mt-6 w-full text-sm"><thead><tr class="border-b border-line text-muted"><th class="py-3 text-start">شرح</th><th class="py-3 text-start">تعداد</th><th class="py-3 text-start">مبلغ (ریال)</th></tr></thead><tbody>@foreach ($invoice['lines'] as $line)<tr><td class="py-3">{{ $line['description'] }}</td><td class="num py-3">{{ $line['quantity'] }}</td><td class="num py-3">{{ $line['amountRial'] }}</td></tr>@endforeach</tbody></table>
            <p class="num mt-5 border-t border-line pt-4 text-xl font-extrabold">جمع: {{ $invoice['amountRial'] }} ریال</p>
        </article>
    </div>
</x-layouts.site>
