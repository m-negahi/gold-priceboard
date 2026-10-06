{{-- Safhe ye yek karbar (P6-03). Data kamel az `UserDetailQuery` miad —
     Blade faghat namayesh. --}}

<x-layouts.panel :title="$profile['name'] ?? $profile['phone']" current="customers" :breadcrumbs="['پنل', 'مشتریان', $profile['name'] ?? $profile['phone']]">
    @if (auth()->user()->can('users.manage') && (! $profile['isAdmin'] || auth()->user()->can('access.manage')))
        <a wire:navigate href="{{ route('panel.users.edit', $profile['id']) }}" class="inline-flex h-11 items-center text-sm font-semibold text-brand-700">ویرایش اطلاعات و استعلام زحل</a>
    @endif
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('panel.users.index') }}" class="inline-flex items-center gap-1 text-xs text-muted hover:text-ink">
                <x-icon name="arrow-back" class="size-3.5 rtl:-scale-x-100" />
                بازگشت به فهرست
            </a>
            <h1 class="mt-1 truncate text-2xl font-extrabold text-ink md:text-3xl">{{ $profile['name'] ?? 'بدون نام' }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <span class="ltr num text-sm text-muted">{{ $profile['phone'] }}</span>
                <x-panel.pill :tone="$profile['isAdmin'] ? 'info' : 'flat'">{{ $profile['isAdmin'] ? 'ادمین' : 'مشتری' }}</x-panel.pill>
                <x-panel.pill :tone="$profile['status']->value === 'active' ? 'ok' : 'down'" dot data-user-status="{{ $profile['status']->value }}">{{ $profile['status']->label() }}</x-panel.pill>
                <x-panel.pill tone="flat">{{ $profile['level']->label() }}</x-panel.pill>
            </div>
        </div>

        {{-- Taghir e naghsh (P6-04) — POST e sade؛ mohafez e lockout tu Action. --}}
        @can('access.manage')
        <form method="POST" action="{{ route('panel.users.role', $profile['id']) }}" data-role-form>
            @csrf
            <input type="hidden" name="role" value="{{ $profile['isAdmin'] ? 'customer' : 'admin' }}">
            <button type="submit"
                    class="inline-flex h-10 items-center gap-2 rounded-xl px-4 text-sm font-semibold text-ink ring-1 ring-line hover:bg-surface-3">
                <x-icon name="shield" class="size-4" />
                {{ $profile['isAdmin'] ? 'تبدیل به مشتری' : 'ارتقا به ادمین' }}
            </button>
        </form>
        @endcan
    </div>

    @if ($errors->any())
        <p class="mt-3 rounded-xl bg-down-soft px-4 py-2 text-sm text-down" role="alert" data-user-error>{{ $errors->first() }}</p>
    @endif

    <div class="mt-6 grid gap-4 xl:grid-cols-3">
        {{-- ── Profile ─────────────────────────────────────────────── --}}
        <section class="rounded-2xl bg-surface card-p shadow-xs" aria-labelledby="profile-title">
            <h2 id="profile-title" class="text-base font-bold text-ink">مشخصات</h2>
            <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm">
                <dt class="text-muted">موبایل</dt>
                <dd class="text-end text-ink"><span class="ltr num">{{ $profile['phone'] }}</span></dd>
                <dt class="text-muted">ایمیل</dt>
                {{-- truncate ru span e LTR — ru dd e RTL ebteda ye email boride mishod. --}}
                <dd class="min-w-0 text-end text-ink"><span class="ltr block truncate" title="{{ $profile['email'] }}">{{ $profile['email'] ?? '—' }}</span></dd>
                <dt class="text-muted">نوع مشتری</dt>
                <dd class="text-end text-ink">{{ $profile['customerType']->label() }}</dd>
                <dt class="text-muted">کد/شناسه ملی</dt>
                <dd class="text-end text-ink"><span class="ltr num">{{ $profile['nationalCode'] ?? '—' }}</span></dd>
                <dt class="text-muted">سطح</dt>
                <dd class="text-end text-ink">{{ $profile['level']->label() }}</dd>
                <dt class="text-muted">عضویت</dt>
                <dd class="text-end text-ink"><span class="ltr num">{{ $profile['createdAt'] ?? '—' }}</span></dd>
                <dt class="text-muted">آخرین ورود</dt>
                <dd class="text-end text-ink"><span class="ltr num">{{ $profile['lastLoginAt'] ?? '—' }}</span></dd>
                @if ($profile['status']->value === 'disabled')
                    <dt class="text-muted">غیرفعال از</dt>
                    <dd class="text-end text-down"><span class="ltr num">{{ $profile['disabledAt'] ?? '—' }}</span></dd>
                    <dt class="text-muted">دلیل</dt>
                    <dd class="text-end text-down">{{ $profile['disabledReason'] ?? '—' }}</dd>
                @endif
            </dl>

            {{-- Dastresi (P6-05): disable = login + session haye baz + HAME ye site ha ru API. --}}
            @if (auth()->user()->can('users.manage') && (! $profile['isAdmin'] || auth()->user()->can('access.manage')))
            <div class="mt-5 border-t border-line-2 pt-4" data-user-access>
                @if ($profile['status']->value === 'disabled')
                    <form method="POST" action="{{ route('panel.users.enable', $profile['id']) }}">
                        @csrf
                        <button type="submit" data-user-enable
                                class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand hover:bg-brand-2">
                            <x-icon name="circle-check" class="size-4" />
                            فعال‌سازی دوباره
                        </button>
                    </form>
                @elseif ($isSelf)
                    <p class="text-xs text-muted" data-user-self>این حساب خودتان است؛ غیرفعال‌سازی آن ممکن نیست.</p>
                @else
                    <form method="POST" action="{{ route('panel.users.disable', $profile['id']) }}" class="space-y-3" data-user-disable-form>
                        @csrf
                        <x-form.field name="reason" label="دلیل غیرفعال‌سازی" required hint="ورود، نشست‌های باز و همهٔ سایت‌های این کاربر روی API بسته می‌شوند.">
                            <x-form.input name="reason" :value="old('reason')" maxlength="500" />
                        </x-form.field>
                        <button type="submit" data-user-disable
                                class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-danger-soft px-4 text-sm font-semibold text-danger ring-1 ring-danger/30 hover:ring-danger">
                            <x-icon name="ban" class="size-4" />
                            غیرفعال کردن کاربر
                        </button>
                    </form>
                @endif
            </div>
            @endif
        </section>

        @can('finance.view')
        {{-- ── Wallet ──────────────────────────────────────────────── --}}
        <section class="rounded-2xl bg-surface card-p shadow-xs xl:col-span-2" aria-labelledby="wallet-title" data-wallet>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="wallet-title" class="text-base font-bold text-ink">کیف پول</h2>
                    @if ($wallet !== null)
                        <p class="mt-2 text-sm text-muted">موجودی</p>
                        <p class="num text-3xl font-extrabold text-ink" data-wallet-balance>{{ $wallet['balanceRial'] }} <span class="text-sm font-medium text-muted">ریال</span></p>
                        @if ($wallet['isBlocked'])
                            <x-panel.pill tone="down" class="mt-2">مسدود</x-panel.pill>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Sharj/kasr e dasti (P6-06) — radif e JADID e ledger (`admin_adjust` + admin + dalil).
                 `idempotency_key` ba har render taze: ersal e dobare ye hamin form yek radif misaze. --}}
            @can('finance.manage')
            <form method="POST" action="{{ route('panel.users.wallet.adjust', $profile['id']) }}"
                  class="mt-4 grid gap-3 rounded-xl bg-surface-2 p-3 sm:grid-cols-[8rem_10rem_1fr_auto] sm:items-end" data-wallet-adjust>
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $walletAdjustKey }}">
                <x-form.field name="direction" label="نوع">
                    <x-form.select name="direction" :options="['credit' => 'شارژ (+)', 'debit' => 'کسر (−)']" :value="old('direction', 'credit')" />
                </x-form.field>
                <x-form.field name="amount" label="مبلغ (ریال)" required>
                    <x-form.money name="amount" :value="old('amount')" maxlength="32" placeholder="125,000" />
                </x-form.field>
                <x-form.field name="note" label="دلیل" required>
                    <x-form.input name="note" :value="old('note')" maxlength="255" />
                </x-form.field>
                <button type="submit" data-wallet-adjust-submit
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand hover:bg-brand-2">
                    <x-icon name="wallet" class="size-4" />
                    ثبت
                </button>
            </form>
            @endcan

            @if ($wallet === null)
                <p class="mt-4 rounded-xl bg-surface-3 px-4 py-6 text-center text-sm text-muted">هنوز کیف پولی ساخته نشده است.</p>
            @else
                <div class="relative mt-4 overflow-x-auto">
                    <table class="w-full min-w-[34rem] text-sm">
                        <thead>
                            <tr class="border-b border-line text-xs font-semibold text-muted">
                                <th class="px-3 py-2 text-start font-semibold">زمان</th>
                                <th class="px-3 py-2 text-start font-semibold">نوع</th>
                                <th class="px-3 py-2 text-start font-semibold">مبلغ (ریال)</th>
                                <th class="px-3 py-2 text-start font-semibold">مانده</th>
                                <th class="px-3 py-2 text-start font-semibold">مرجع</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-2">
                            @forelse ($wallet['ledger'] as $tx)
                                <tr data-ledger-row>
                                    <td class="whitespace-nowrap px-3 py-2 text-xs text-muted"><span class="ltr num">{{ $tx['at'] ?? '—' }}</span></td>
                                    <td class="px-3 py-2 text-ink">
                                        {{ $tx['reason'] }}
                                        @if ($tx['note'])
                                            <p class="mt-0.5 text-xs text-muted">{{ $tx['note'] }}</p>
                                        @endif
                                        @if ($tx['admin'])
                                            <p class="mt-0.5 text-xs text-muted-3" data-ledger-admin>ثبت توسط {{ $tx['admin'] }}</p>
                                        @endif
                                    </td>
                                    <td @class(['num whitespace-nowrap px-3 py-2 font-semibold', 'text-ok' => $tx['credit'], 'text-down' => ! $tx['credit']])>
                                        {{-- `ltr`: vagarna alamat e −/+ tu RTL be TAH e adad mipare. --}}
                                        <span class="ltr num">{{ $tx['credit'] ? '+' : '−' }}{{ $tx['amountRial'] }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-muted"><span class="ltr num">{{ $tx['balanceAfterRial'] }}</span></td>
                                    <td class="px-3 py-2 text-xs text-muted-3"><span class="ltr block max-w-32 truncate" title="{{ $tx['reference'] }}">{{ $tx['reference'] }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-6 text-center text-muted">تراکنشی ثبت نشده است.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
        @endcan
    </div>

    @can('finance.view')
    {{-- ── Usage e API (P6-08) — az `api_usage_daily` (rollup e har 15 daghighe). ── --}}
    <section class="mt-4 rounded-2xl bg-surface card-p shadow-xs" aria-labelledby="usage-title"
             data-usage-chart data-usage-total-calls="{{ $usage['totalCalls'] }}" data-usage-total-cost="{{ $usage['totalCostRial'] }}">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="usage-title" class="text-base font-bold text-ink">مصرف API</h2>
                <p class="mt-1 text-xs text-muted">{{ $usage['days'] }} روز اخیر — فراخوانی در روز (همهٔ سایت‌ها)</p>
            </div>
            <dl class="flex gap-6 text-sm">
                <div>
                    <dt class="text-xs text-muted">فراخوانی</dt>
                    <dd class="num text-lg font-bold text-ink">{{ $usage['totalCalls'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">هزینه (ریال)</dt>
                    <dd class="num text-lg font-bold text-ink">{{ $usage['totalCostRial'] }}</dd>
                </div>
            </dl>
        </div>
        <x-panel.bar-chart :bars="$usage['bars']" gap="gap-0.5 sm:gap-1.5" spill-labels class="mt-4" aria-label="نمودار فراخوانی روزانه" />
    </section>
    @endcan

    {{-- ── Site ha (api_clients) ──────────────────────────────────── --}}
    <section class="mt-4 overflow-hidden rounded-2xl bg-surface shadow-xs" aria-labelledby="sites-title">
        <h2 id="sites-title" class="px-5 pt-5 text-base font-bold text-ink">سایت‌ها و پلن‌ها</h2>
        <div class="relative mt-3 overflow-x-auto">
            <table class="w-full min-w-[44rem] text-sm">
                <thead>
                    <tr class="border-b border-line text-xs font-semibold text-muted">
                        <th class="px-5 py-3 text-start font-semibold">سایت</th>
                        <th class="px-5 py-3 text-start font-semibold">شناسه کلاینت</th>
                        <th class="px-5 py-3 text-start font-semibold">پلن</th>
                        <th class="px-5 py-3 text-start font-semibold">وضعیت</th>
                        <th class="px-5 py-3 text-start font-semibold">آخرین استفاده</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-2">
                    @forelse ($sites as $site)
                        <tr data-site-row data-site-active="{{ $site['isActive'] && ! $site['deleted'] ? 1 : 0 }}" @class(['opacity-60' => $site['deleted']])>
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink">{{ $site['name'] }}</p>
                                @if ($site['websiteUrl'])
                                    <p class="ltr mt-0.5 text-xs text-muted-3">{{ $site['websiteUrl'] }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-muted"><span class="ltr">{{ $site['clientId'] }}</span></td>
                            <td class="px-5 py-3">
                                @if ($site['plan'])
                                    <p class="text-ink">{{ $site['plan']['name'] }}</p>
                                    <p class="num mt-0.5 text-xs text-muted">{{ $site['plan']['callsPerDay'] }} فراخوانی/روز · {{ $site['plan']['costPerCallRial'] }} ریال/فراخوانی</p>
                                @else
                                    <span class="text-xs text-muted">بدون پلن (نرخ پیش‌فرض)</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if ($site['deleted'])
                                    <x-panel.pill tone="down">حذف‌شده</x-panel.pill>
                                @elseif ($site['isActive'])
                                    <x-panel.pill tone="ok" dot>فعال</x-panel.pill>
                                @else
                                    <x-panel.pill tone="flat" dot>غیرفعال</x-panel.pill>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-muted"><span class="ltr num">{{ $site['lastUsedAt'] ?? '—' }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-muted">این کاربر هنوز سایتی ثبت نکرده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ── Ticket ha (Phase 7) ─────────────────────────────────────── --}}
    <section class="mt-4 rounded-2xl bg-surface card-p shadow-xs" aria-labelledby="tickets-title">
        <h2 id="tickets-title" class="text-base font-bold text-ink">تیکت‌ها</h2>
        <ul class="mt-3 divide-y divide-line-2">
            @forelse ($tickets as $ticket)
                <li data-user-ticket>
                    <a href="{{ route('panel.tickets.show', $ticket['id']) }}" class="flex flex-wrap items-center justify-between gap-3 py-3 hover:text-brand-700">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-ink">{{ $ticket['subject'] }}</p>
                            <p class="num mt-0.5 text-xs text-muted"><span class="ltr">#{{ $ticket['id'] }}</span> · {{ $ticket['type']->label() }} · <span class="ltr">{{ $ticket['lastActivityAt'] }}</span></p>
                        </div>
                        <x-panel.pill :tone="$ticket['status']->tone()" dot>{{ $ticket['status']->label() }}</x-panel.pill>
                    </a>
                </li>
            @empty
                <li class="rounded-xl bg-surface-3 px-4 py-6 text-center text-sm text-muted">این کاربر تیکتی ندارد.</li>
            @endforelse
        </ul>
    </section>
</x-layouts.panel>
