<x-layouts.site :title="'حساب من'">
    <div class="container-site py-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h1 class="text-2xl font-extrabold text-ink md:text-3xl">حساب من</h1><p class="mt-1 text-sm text-muted">سایت‌ها، اشتراک‌ها، کیف پول و مصرف API</p></div>
            <a href="{{ route('account.tickets.index') }}" wire:navigate class="inline-flex h-11 items-center gap-2 rounded-xl bg-surface px-4 text-sm font-semibold text-ink ring-1 ring-line"><x-icon name="ticket" class="size-4" /> تیکت‌ها</a>
        </div>
        <nav class="mt-5 flex gap-2 overflow-x-auto pb-1 text-sm" aria-label="بخش‌های حساب">
            @foreach (['dashboard' => 'نمای کلی', 'sites' => 'سایت‌ها', 'subscriptions' => 'اشتراک‌ها', 'wallet' => 'کیف پول', 'usage' => 'مصرف'] as $key => $label)
                <a href="{{ route('account.billing.'.$key) }}" wire:navigate @class(['inline-flex h-11 shrink-0 items-center rounded-xl px-4 font-semibold', 'bg-brand text-on-brand' => $section === $key, 'bg-surface text-muted ring-1 ring-line hover:text-ink' => $section !== $key])>{{ $label }}</a>
            @endforeach
        </nav>

        @if ($errors->any())<p role="alert" class="mt-5 rounded-xl bg-down-soft p-3 text-sm text-down">{{ $errors->first() }}</p>@endif

        @if ($section === 'dashboard')
            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-panel.stat-card label="موجودی کیف پول" :value="$dashboard['walletBalance']" hint="ریال" featured />
                <x-panel.stat-card label="سایت" :value="$dashboard['siteCount']" />
                <x-panel.stat-card label="اشتراک فعال" :value="$dashboard['activeSubscriptionCount']" />
                <x-panel.stat-card label="فراخوانی 30 روز" :value="$dashboard['totalCalls']" />
            </div>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <section class="rounded-2xl bg-surface card-p shadow-xs">
                    <h2 class="font-bold text-ink">شروع سریع</h2>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('account.billing.sites') }}" wire:navigate class="inline-flex h-11 items-center rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand">افزودن سایت</a>
                        <a href="{{ route('account.billing.subscriptions') }}" wire:navigate class="inline-flex h-11 items-center rounded-xl bg-surface-2 px-4 text-sm font-semibold text-ink">دیدن پلن‌ها</a>
                        <a href="{{ route('account.billing.wallet') }}" wire:navigate class="inline-flex h-11 items-center rounded-xl bg-surface-2 px-4 text-sm font-semibold text-ink">کیف پول</a>
                    </div>
                    <p class="mt-4 text-sm text-muted">هزینه مصرف 30 روز اخیر: <span class="num font-semibold text-ink">{{ $dashboard['totalCost'] }}</span> ریال</p>
                </section>
                <section class="rounded-2xl bg-surface card-p shadow-xs">
                    <h2 class="font-bold text-ink">تیکت‌های اخیر</h2>
                    <ul class="mt-3 divide-y divide-line-2">
                        @forelse ($dashboard['tickets'] as $ticket)
                            <li><a href="{{ route('account.tickets.show', $ticket['id']) }}" wire:navigate class="flex min-h-11 items-center justify-between gap-2 text-sm hover:text-brand"><span>{{ $ticket['subject'] }}</span><x-panel.pill :tone="$ticket['status']->tone()">{{ $ticket['status']->label() }}</x-panel.pill></a></li>
                        @empty<li class="py-4 text-sm text-muted">تیکتی ثبت نشده است.</li>@endforelse
                    </ul>
                </section>
            </div>
        @elseif ($section === 'sites')
            @if (session('site_credentials'))
                <section class="mt-6 rounded-2xl bg-brand-soft card-p" data-site-credentials>
                    <h2 class="font-bold text-ink">کلید سایت ساخته شد</h2>
                    <p class="mt-1 text-sm text-muted">رمز فقط همین بار نمایش داده می‌شود. تا تأیید سایت، API فعال نیست.</p>
                    <dl class="mt-3 grid gap-2 text-sm">
                        <div><dt class="text-muted">شناسه کلاینت</dt><dd class="ltr break-all font-mono">{{ session('site_credentials')['client_id'] }}</dd></div>
                        <div><dt class="text-muted">رمز</dt><dd class="ltr break-all font-mono">{{ session('site_credentials')['secret'] }}</dd></div>
                    </dl>
                </section>
            @endif
            <section class="mt-6 rounded-2xl bg-surface card-p shadow-xs">
                <h2 class="font-bold">افزودن سایت</h2>
                <p class="mt-1 text-sm text-muted">بعد از ثبت، درخواست تأیید برای مدیر فرستاده می‌شود.</p>
                <form method="POST" action="{{ route('account.billing.sites.store') }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_2fr_auto] sm:items-end">
                    @csrf
                    <x-form.field name="name" label="نام سایت" required><x-form.input name="name" :value="old('name')" maxlength="120" /></x-form.field>
                    <x-form.field name="website_url" label="آدرس سایت" required><x-form.input name="website_url" ltr :value="old('website_url')" placeholder="https://example.com" /></x-form.field>
                    <button class="h-11 rounded-xl bg-brand px-4 font-semibold text-on-brand">ثبت سایت</button>
                </form>
            </section>
            <section class="mt-4 overflow-hidden rounded-2xl bg-surface shadow-xs">
                <h2 class="px-5 pt-5 font-bold">سایت‌های من</h2>
                <div class="overflow-x-auto"><table class="w-full min-w-[40rem] text-sm">
                    <thead><tr class="border-b border-line text-muted"><th class="px-5 py-3 text-start">سایت</th><th class="px-5 py-3 text-start">شناسه</th><th class="px-5 py-3 text-start">وضعیت</th><th class="px-5 py-3 text-start">اشتراک</th></tr></thead>
                    <tbody class="divide-y divide-line-2">
                        @forelse ($sites as $site)
                            <tr><td class="px-5 py-3 font-semibold">{{ $site['name'] }}<p class="ltr text-xs font-normal text-muted">{{ $site['websiteUrl'] }}</p></td><td class="ltr px-5 py-3 text-xs">{{ $site['clientId'] }}</td><td class="px-5 py-3"><x-panel.pill :tone="$site['status'] === 'approved' ? 'ok' : ($site['status'] === 'rejected' ? 'down' : 'warn')">{{ ['pending' => 'در انتظار تأیید', 'approved' => 'تأییدشده', 'rejected' => 'ردشده', 'suspended' => 'تعلیق‌شده'][$site['status']] ?? $site['status'] }}</x-panel.pill></td><td class="num px-5 py-3">{{ $site['subscriptionEndsAt'] ? 'تا '.$site['subscriptionEndsAt'].' · '.$site['callsRemaining'].' فراخوانی باقی' : 'بدون اشتراک؛ نرخ پیش‌فرض' }}</td></tr>
                        @empty<tr><td colspan="4" class="px-5 py-8 text-center text-muted">هنوز سایتی ثبت نکرده‌اید.</td></tr>@endforelse
                    </tbody>
                </table></div>
            </section>
        @elseif ($section === 'subscriptions')
            <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($plans as $plan)
                    <article class="rounded-2xl bg-surface card-p shadow-xs">
                        <h2 class="text-lg font-bold">{{ $plan['name'] }}</h2>
                        <p class="num mt-3 text-2xl font-extrabold">{{ $plan['priceRial'] }} <span class="text-sm font-normal text-muted">ریال</span></p>
                        <ul class="mt-3 space-y-1 text-sm text-muted">
                            <li class="num">{{ $plan['durationDays'] }} روز</li><li class="num">{{ $plan['callsIncluded'] }} فراخوانی در دوره</li>
                            <li class="num">{{ $plan['callsPerDay'] }} فراخوانی مشمول در روز</li><li class="num">مازاد: {{ $plan['overageRial'] }} ریال/فراخوانی</li>
                        </ul>
                        <form method="POST" action="{{ route('account.billing.subscriptions.purchase') }}" class="mt-4 space-y-2">
                            @csrf<input type="hidden" name="plan_id" value="{{ $plan['id'] }}"><input type="hidden" name="purchase_key" value="{{ $purchaseKey }}">
                            <select name="site_id" required aria-label="انتخاب سایت" class="h-11 w-full rounded-xl border border-line px-3 text-sm">
                                <option value="">سایت تأییدشده را انتخاب کنید</option>
                                @foreach ($sites as $site)@if ($site['status'] === 'approved')<option value="{{ $site['id'] }}">{{ $site['name'] }}</option>@endif@endforeach
                            </select>
                            <button class="h-11 w-full rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand">خرید از کیف پول</button>
                        </form>
                    </article>
                @empty<p class="text-muted">پلن فعالی وجود ندارد.</p>@endforelse
            </section>
            <section class="mt-5 rounded-2xl bg-surface card-p shadow-xs">
                <h2 class="font-bold">اشتراک‌های من</h2>
                <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[38rem] text-sm">
                    <thead><tr class="border-b border-line text-muted"><th class="py-2 text-start">پلن</th><th class="py-2 text-start">سایت</th><th class="py-2 text-start">شروع</th><th class="py-2 text-start">پایان</th><th class="py-2 text-start">مصرف</th><th class="py-2 text-start">قیمت (ریال)</th></tr></thead>
                    <tbody class="divide-y divide-line-2">@forelse ($subscriptions as $subscription)<tr><td class="py-3">{{ $subscription['planName'] }}</td><td class="num py-3">#{{ $subscription['siteId'] }}</td><td class="num py-3">{{ $subscription['startsAt'] }}</td><td class="num py-3">{{ $subscription['endsAt'] }}</td><td class="num py-3">{{ $subscription['callsUsed'] }} / {{ $subscription['callsIncluded'] }}</td><td class="num py-3">{{ $subscription['priceRial'] }}</td></tr>@empty<tr><td colspan="6" class="py-5 text-center text-muted">اشتراکی ثبت نشده است.</td></tr>@endforelse</tbody>
                </table></div>
            </section>
        @elseif ($section === 'wallet')
            <section class="mt-6 rounded-2xl bg-surface card-p shadow-xs">
                <p class="text-sm text-muted">موجودی کیف پول</p><p class="num mt-2 text-3xl font-extrabold">{{ $wallet['balanceRial'] }} <span class="text-sm font-normal text-muted">ریال</span></p>
                @if ($wallet['paymentConfigured'])
                    <form method="POST" action="{{ route('account.billing.topup.start') }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf<input type="hidden" name="request_key" value="{{ $topupKey }}">
                        <x-form.field name="amount_rial" label="مبلغ شارژ (ریال)" required><x-form.input name="amount_rial" ltr inputmode="numeric" :value="old('amount_rial')" placeholder="1,000,000" /></x-form.field>
                        <button class="h-11 rounded-xl bg-brand px-4 font-semibold text-on-brand">پرداخت با زیبال</button>
                    </form>
                @else<p class="mt-4 rounded-xl bg-surface-2 p-3 text-sm text-muted">درگاه پرداخت هنوز تنظیم نشده است.</p>@endif
            </section>
            <section class="mt-4 overflow-hidden rounded-2xl bg-surface shadow-xs">
                <h2 class="px-5 pt-5 font-bold">گردش کیف پول</h2>
                <div class="overflow-x-auto"><table class="w-full min-w-[42rem] text-sm">
                    <thead><tr class="border-b border-line text-muted"><th class="px-5 py-3 text-start">زمان</th><th class="px-5 py-3 text-start">دلیل</th><th class="px-5 py-3 text-start">مبلغ (ریال)</th><th class="px-5 py-3 text-start">مانده (ریال)</th></tr></thead>
                    <tbody class="divide-y divide-line-2">@forelse ($wallet['ledger'] as $transaction)<tr><td class="num px-5 py-3 text-xs">{{ $transaction['at'] }}</td><td class="px-5 py-3">{{ $transaction['reason'] }}<p class="ltr text-xs text-muted">{{ $transaction['reference'] }}</p></td><td class="num px-5 py-3">{{ $transaction['direction'] === 'credit' ? '+' : '−' }}{{ $transaction['amountRial'] }}</td><td class="num px-5 py-3">{{ $transaction['balanceRial'] }}</td></tr>@empty<tr><td colspan="4" class="px-5 py-7 text-center text-muted">گردشی ثبت نشده است.</td></tr>@endforelse</tbody>
                </table></div>
            </section>
            <section class="mt-4 rounded-2xl bg-surface card-p shadow-xs">
                <h2 class="font-bold">فاکتورها و رسیدها</h2><p class="mt-1 text-xs text-muted">سندها ثبت داخلی هستند و به سامانه مالیاتی ارسال نشده‌اند.</p>
                <ul class="mt-3 divide-y divide-line-2">@forelse ($wallet['invoices'] as $invoice)<li class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"><span class="num">{{ $invoice['number'] }} · {{ $invoice['amountRial'] }} ریال · {{ $invoice['issuedAt'] }}</span><a href="{{ route('account.billing.invoice', $invoice['id']) }}" wire:navigate class="text-brand">مشاهده و چاپ</a></li>@empty<li class="py-4 text-sm text-muted">فاکتوری صادر نشده است.</li>@endforelse</ul>
            </section>
        @elseif ($section === 'usage')
            <div class="mt-6 grid gap-4 sm:grid-cols-2"><x-panel.stat-card label="فراخوانی 30 روز اخیر" :value="$usage['totalCalls']" featured /><x-panel.stat-card label="هزینه 30 روز اخیر" :value="$usage['totalCostRial']" hint="ریال" /></div>
            <section class="mt-4 overflow-hidden rounded-2xl bg-surface shadow-xs"><div class="overflow-x-auto"><table class="w-full min-w-[25rem] text-sm"><thead><tr class="border-b border-line text-muted"><th class="px-5 py-3 text-start">روز</th><th class="px-5 py-3 text-start">فراخوانی</th><th class="px-5 py-3 text-start">هزینه (ریال)</th></tr></thead><tbody class="divide-y divide-line-2">@foreach ($usage['days'] as $day)<tr><td class="num px-5 py-2">{{ $day['day'] }}</td><td class="num px-5 py-2">{{ $day['calls'] }}</td><td class="num px-5 py-2">{{ $day['costMinor'] }}</td></tr>@endforeach</tbody></table></div></section>
        @endif
    </div>
</x-layouts.site>
