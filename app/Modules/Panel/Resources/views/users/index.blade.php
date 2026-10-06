{{-- Lista ye karbaran (P6-02). Server-rendered: filter ha form e GET e sade
     (URL share-pazir، bedun e JS ham kar mikone)، safhe-bandi az paginator. --}}

<x-layouts.panel title="مشتریان" current="customers" :breadcrumbs="['پنل', 'مشتریان']">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink md:text-3xl">مشتریان</h1>
            <p class="mt-1 text-sm text-muted">همه کاربران — مشتری و ادمین — با کیف پول، سایت‌ها و وضعیت.</p>
        </div>

        <a href="{{ route('panel.users.create') }}" data-user-create
           class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand shadow-(--bp-elev-brand) hover:bg-brand-2">
            <x-icon name="plus" class="size-4" />
            کاربر جدید
        </a>

    </div>

    <form method="GET" action="{{ route('panel.users.index') }}" data-user-filters
          class="mt-6 grid gap-3 rounded-2xl bg-surface p-4 shadow-xs sm:grid-cols-2 lg:grid-cols-5">
        <label class="lg:col-span-2">
            <span class="sr-only">جستجو</span>
            <x-form.input name="q" :value="$filters['q']" placeholder="جستجو با موبایل یا نام" autocomplete="off" />
        </label>
        <label>
            <span class="sr-only">نقش</span>
            <x-form.select name="role" :value="$filters['role']" :options="['' => 'همه نقش‌ها', 'admin' => 'ادمین', 'customer' => 'مشتری']" />
        </label>
        <label>
            <span class="sr-only">وضعیت</span>
            <x-form.select name="status" :value="$filters['status']" :options="['' => 'همه وضعیت‌ها'] + $statuses" />
        </label>
        <div class="flex gap-2">
            <label class="min-w-0 flex-1">
                <span class="sr-only">سطح</span>
                <x-form.select name="level" :value="$filters['level']" :options="['' => 'همه سطح‌ها'] + $levels" />
            </label>
            <button type="submit" aria-label="اعمال فیلتر"
                    class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand text-on-brand hover:bg-brand-2">
                <x-icon name="search" class="size-4" />
            </button>
        </div>
    </form>

    @if ($errors->any())
        <p class="mt-3 rounded-xl bg-down-soft px-4 py-2 text-sm text-down" role="alert">{{ $errors->first() }}</p>
    @endif

    <section class="mt-4 overflow-hidden rounded-2xl bg-surface shadow-xs">
        <div class="relative overflow-x-auto">
            <table class="w-full min-w-[52rem] text-sm">
                <thead>
                    <tr class="border-b border-line text-xs font-semibold text-muted">
                        <th class="px-5 py-3 text-start font-semibold">کاربر</th>
                        <th class="px-5 py-3 text-start font-semibold">نقش</th>
                        <th class="px-5 py-3 text-start font-semibold">وضعیت</th>
                        <th class="px-5 py-3 text-start font-semibold">سطح</th>
                        <th class="px-5 py-3 text-start font-semibold">کیف پول (تومان)</th>
                        <th class="px-5 py-3 text-start font-semibold">سایت</th>
                        <th class="px-5 py-3 text-start font-semibold">آخرین ورود</th>
                        <th class="px-5 py-3"><span class="sr-only">جزئیات</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-2">
                    @forelse ($users as $user)
                        <tr data-user-row data-user-id="{{ $user['id'] }}">
                            <td class="px-5 py-3">
                                <a href="{{ route('panel.users.show', $user['id']) }}" data-user-detail-link wire:navigate class="inline-flex min-h-11 items-center font-medium text-ink hover:text-brand">{{ $user['name'] ?? $user['phone'] }}</a>
                                <p class="ltr num mt-0.5 text-xs text-muted-3">{{ $user['phone'] }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <x-panel.pill :tone="$user['isAdmin'] ? 'info' : 'flat'">{{ $user['isAdmin'] ? 'ادمین' : 'مشتری' }}</x-panel.pill>
                            </td>
                            <td class="px-5 py-3">
                                <x-panel.pill :tone="$user['status']->value === 'active' ? 'ok' : 'down'" dot>{{ $user['status']->label() }}</x-panel.pill>
                            </td>
                            <td class="px-5 py-3 text-xs text-muted">{{ $user['level']->label() }}</td>
                            <td class="px-5 py-3 text-ink"><span class="ltr num">{{ $user['balanceToman'] ?? '—' }}</span></td>
                            <td class="num px-5 py-3 text-muted" data-sites="{{ $user['sitesCount'] }}">{{ $user['sitesCount'] }}</td>
                            <td class="px-5 py-3 text-xs text-muted"><span class="ltr num">{{ $user['lastLoginAt'] ?? '—' }}</span></td>
                            <td class="px-5 py-3 text-end">
                                <a href="{{ route('panel.users.show', $user['id']) }}"
                                   class="inline-flex size-9 items-center justify-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink"
                                   aria-label="جزئیات {{ $user['name'] ?? $user['phone'] }}" title="جزئیات">
                                    <x-icon name="eye" class="size-4" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-muted">کاربری با این فیلترها پیدا نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->lastPage() > 1)
            <nav class="flex items-center justify-between gap-3 border-t border-line px-5 py-3 text-sm" aria-label="صفحه‌بندی">
                <span class="num text-xs text-muted">صفحه {{ $users->currentPage() }} از {{ $users->lastPage() }} — {{ $users->total() }} کاربر</span>
                <div class="flex gap-2">
                    @if ($users->previousPageUrl())
                        <a href="{{ $users->previousPageUrl() }}" class="inline-flex h-9 items-center rounded-lg px-3 text-ink ring-1 ring-line hover:bg-surface-3">قبلی</a>
                    @endif
                    @if ($users->nextPageUrl())
                        <a href="{{ $users->nextPageUrl() }}" class="inline-flex h-9 items-center rounded-lg px-3 text-ink ring-1 ring-line hover:bg-surface-3">بعدی</a>
                    @endif
                </div>
            </nav>
        @endif
    </section>
</x-layouts.panel>
