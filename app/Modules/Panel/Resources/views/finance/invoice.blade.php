{{-- Factor e rasmi — nama ye ADMIN baraye chap. Bedun e chrome e panel ta
     chap tamiz bashe؛ foroshande az config e `billing.seller`. --}}
<x-layouts.site :title="$invoice['kindLabel'].' '.$invoice['number']" :chrome="false">
    <div class="container-site py-8">
        <div class="flex items-center justify-between gap-3 print:hidden">
            <a href="{{ route('panel.finance.index', ['tab' => 'invoices']) }}" wire:navigate class="text-sm text-brand">بازگشت به امور مالی</a>
            <button type="button" x-data x-cloak @click="window.print()" class="h-11 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand">چاپ فاکتور</button>
        </div>

        <article class="mt-5 rounded-2xl bg-surface card-p shadow-xs" data-invoice>
            <div class="flex flex-wrap justify-between gap-4 border-b border-line pb-5">
                <div>
                    <h1 class="text-2xl font-extrabold text-ink">{{ $invoice['kindLabel'] }}</h1>
                    @if (($invoice['seller']['name'] ?? '') === '')
                        <p class="mt-1 text-sm text-warn">مشخصات فروشنده تنظیم نشده (BILLING_SELLER_*)؛ این سند هنوز فاکتور رسمی نیست.</p>
                    @endif
                </div>
                <dl class="text-sm">
                    <dt class="text-muted">شماره</dt><dd class="num font-bold">{{ $invoice['number'] }}</dd>
                    <dt class="mt-2 text-muted">تاریخ صدور</dt><dd class="num">{{ $invoice['issuedAt'] }}</dd>
                </dl>
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <section data-seller>
                    <h2 class="font-bold">فروشنده</h2>
                    <p class="mt-2 text-sm">{{ $invoice['seller']['name'] ?: '—' }}</p>
                    <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs text-muted">
                        @foreach (['economic_code' => 'کد اقتصادی', 'national_id' => 'شناسه ملی', 'registration_number' => 'شماره ثبت', 'postal_code' => 'کد پستی', 'phone' => 'تلفن', 'address' => 'نشانی'] as $key => $label)
                            @if (($invoice['seller'][$key] ?? '') !== '')
                                <dt>{{ $label }}</dt><dd class="num">{{ $invoice['seller'][$key] }}</dd>
                            @endif
                        @endforeach
                    </dl>
                </section>
                <section data-buyer>
                    <h2 class="font-bold">خریدار</h2>
                    <p class="mt-2 text-sm">{{ $invoice['buyer']['profile']['registered_name'] ?? $invoice['buyer']['name'] ?? '—' }}</p>
                    <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs text-muted">
                        <dt>موبایل</dt><dd class="num">{{ $invoice['buyer']['phone'] ?? '—' }}</dd>
                        <dt>کد ملی</dt><dd class="num">{{ $invoice['buyer']['national_code'] ?? '—' }}</dd>
                        @foreach (['economic_code' => 'کد اقتصادی', 'national_id' => 'شناسه ملی', 'postal_code' => 'کد پستی', 'address' => 'نشانی'] as $key => $label)
                            @if (($invoice['buyer']['profile'][$key] ?? '') !== '')
                                <dt>{{ $label }}</dt><dd class="num">{{ $invoice['buyer']['profile'][$key] }}</dd>
                            @endif
                        @endforeach
                    </dl>
                </section>
            </div>

            <table class="mt-6 w-full text-sm">
                <thead><tr class="border-b border-line text-muted"><th class="py-3 text-start">شرح</th><th class="py-3 text-start">تعداد</th><th class="py-3 text-start">قیمت واحد (ریال)</th><th class="py-3 text-start">مبلغ (ریال)</th></tr></thead>
                <tbody>
                    @foreach ($invoice['lines'] as $line)
                        <tr class="border-b border-line-2"><td class="py-3">{{ $line['description'] }}</td><td class="num py-3">{{ $line['quantity'] }}</td><td class="num py-3">{{ $line['unitPriceRial'] }}</td><td class="num py-3">{{ $line['amountRial'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>

            <dl class="mt-5 ms-auto max-w-xs space-y-1 text-sm">
                @if ($invoice['vatPercent'] > 0)
                    <div class="flex justify-between"><dt class="text-muted">جمع بدون مالیات</dt><dd class="num">{{ $invoice['subtotalRial'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">مالیات بر ارزش افزوده ({{ $invoice['vatPercent'] }}٪)</dt><dd class="num">{{ $invoice['vatRial'] }}</dd></div>
                @endif
                <div class="flex justify-between border-t border-line pt-2 text-lg font-extrabold"><dt>جمع کل (ریال)</dt><dd class="num">{{ $invoice['amountRial'] }}</dd></div>
            </dl>
        </article>
    </div>
</x-layouts.site>
