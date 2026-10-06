{{-- Safhe ye yek karbar (P6-03). Data kamel az `UserDetailQuery` miad —
     Blade faghat namayesh. --}}

<x-layouts.panel :title="$profile['name'] ?? $profile['phone']" current="customers" :breadcrumbs="['پنل', 'مشتریان', $profile['name'] ?? $profile['phone']]">
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
        <form method="POST" action="{{ route('panel.users.role', $profile['id']) }}" data-role-form>
            @csrf
            <input type="hidden" name="role" value="{{ $profile['isAdmin'] ? 'customer' : 'admin' }}">
            <button type="submit"
                    class="inline-flex h-10 items-center gap-2 rounded-xl px-4 text-sm font-semibold text-ink ring-1 ring-line hover:bg-surface-3">
                <x-icon name="shield" class="size-4" />
                {{ $profile['isAdmin'] ? 'تبدیل به مشتری' : 'ارتقا به ادمین' }}
            </button>
        </form>
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
                <dd class="ltr num text-end text-ink">{{ $profile['phone'] }}</dd>
                <dt class="text-muted">ایمیل</dt>
                <dd class="ltr truncate text-end text-ink">{{ $profile['email'] ?? '—' }}</dd>
                <dt class="text-muted">نوع مشتری</dt>
                <dd class="text-end text-ink">{{ $profile['customerType']->label() }}</dd>
                <dt class="text-muted">کد/شناسه ملی</dt>
                <dd class="ltr num text-end text-ink">{{ $profile['nationalCode'] ?? '—' }}</dd>
                <dt class="text-muted">سطح</dt>
                <dd class="text-end text-ink">{{ $profile['level']->label() }}</dd>
                <dt class="text-muted">عضویت</dt>
                <dd class="num text-end text-ink">{{ $profile['createdAt'] ?? '—' }}</dd>
                <dt class="text-muted">آخرین ورود</dt>
                <dd class="num text-end text-ink">{{ $profile['lastLoginAt'] ?? '—' }}</dd>
                @if ($profile['status']->value === 'disabled')
                    <dt class="text-muted">غیرفعال از</dt>
                    <dd class="num text-end text-down">{{ $profile['disabledAt'] ?? '—' }}</dd>
                    <dt class="text-muted">دلیل</dt>
                    <dd class="text-end text-down">{{ $profile['disabledReason'] ?? '—' }}</dd>
                @endif
            </dl>
        </section>

        {{-- ── Wallet ──────────────────────────────────────────────── --}}
        <section class="rounded-2xl bg-surface card-p shadow-xs xl:col-span-2" aria-labelledby="wallet-title" data-wallet>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="wallet-title" class="text-base font-bold text-ink">کیف پول</h2>
                    @if ($wallet !== null)
                        <p class="mt-2 text-sm text-muted">موجودی</p>
                        <p class="num text-3xl font-extrabold text-ink" data-wallet-balance>{{ $wallet['balanceToman'] }} <span class="text-sm font-medium text-muted">تومان</span></p>
                        @if ($wallet['isBlocked'])
                            <x-panel.pill tone="down" class="mt-2">مسدود</x-panel.pill>
                        @endif
                    @endif
                </div>
            </div>

            @if ($wallet === null)
                <p class="mt-4 rounded-xl bg-surface-3 px-4 py-6 text-center text-sm text-muted">هنوز کیف پولی ساخته نشده است.</p>
            @else
                <div class="relative mt-4 overflow-x-auto">
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead>
                            <tr class="border-b border-line text-xs font-semibold text-muted">
                                <th class="px-3 py-2 text-start font-semibold">زمان</th>
                                <th class="px-3 py-2 text-start font-semibold">نوع</th>
                                <th class="px-3 py-2 text-start font-semibold">مبلغ (تومان)</th>
                                <th class="px-3 py-2 text-start font-semibold">مانده</th>
                                <th class="px-3 py-2 text-start font-semibold">مرجع</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-2">
                            @forelse ($wallet['ledger'] as $tx)
                                <tr data-ledger-row>
                                    <td class="num whitespace-nowrap px-3 py-2 text-xs text-muted">{{ $tx['at'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-ink">
                                        {{ $tx['reason'] }}
                                        @if ($tx['note'])
                                            <p class="mt-0.5 text-xs text-muted">{{ $tx['note'] }}</p>
                                        @endif
                                    </td>
                                    <td @class(['num whitespace-nowrap px-3 py-2 font-semibold', 'text-ok' => $tx['credit'], 'text-down' => ! $tx['credit']])>
                                        {{ $tx['credit'] ? '+' : '−' }}{{ $tx['amountToman'] }}
                                    </td>
                                    <td class="num whitespace-nowrap px-3 py-2 text-muted">{{ $tx['balanceAfterToman'] }}</td>
                                    <td class="ltr max-w-40 truncate px-3 py-2 text-xs text-muted-3" title="{{ $tx['reference'] }}">{{ $tx['reference'] }}</td>
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
    </div>

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
                            <td class="ltr px-5 py-3 text-xs text-muted">{{ $site['clientId'] }}</td>
                            <td class="px-5 py-3">
                                @if ($site['plan'])
                                    <p class="text-ink">{{ $site['plan']['name'] }}</p>
                                    <p class="num mt-0.5 text-xs text-muted">{{ $site['plan']['callsPerDay'] }} فراخوانی/روز · {{ $site['plan']['costPerCallToman'] }} تومان/فراخوانی</p>
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
                            <td class="num px-5 py-3 text-xs text-muted">{{ $site['lastUsedAt'] ?? '—' }}</td>
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
        <p class="mt-3 rounded-xl bg-surface-3 px-4 py-6 text-center text-sm text-muted">سامانه تیکت به‌زودی (فاز ۷) فعال می‌شود.</p>
    </section>
</x-layouts.panel>
