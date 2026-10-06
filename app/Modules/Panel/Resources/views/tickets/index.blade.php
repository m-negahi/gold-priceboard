{{-- Inbox e ticket ha (P7-01). Server-rendered: filter ha form e GET e sade
     (URL share-pazir)؛ montazer-e-admin ha (باز/در حال بررسی) aval. --}}

<x-layouts.panel title="تیکت‌ها" current="tickets" :breadcrumbs="['پنل', 'تیکت‌ها']">
    @can('tickets.manage')<a wire:navigate href="{{ route('panel.tickets.create') }}" class="inline-flex h-11 items-center text-sm font-semibold text-brand-700">تیکت جدید برای کاربر</a>@endcan
    <div>
        <h1 class="text-2xl font-extrabold text-ink md:text-3xl">تیکت‌ها</h1>
        <p class="mt-1 text-sm text-muted">همه درخواست‌های کاربران — ارتقای سطح، تأیید سایت و پشتیبانی.</p>
    </div>

    <form method="GET" action="{{ route('panel.tickets.index') }}" data-ticket-filters
          class="mt-6 grid gap-3 rounded-2xl bg-surface p-4 shadow-xs sm:grid-cols-2 lg:grid-cols-4">
        <label class="lg:col-span-2">
            <span class="sr-only">جستجو</span>
            <x-form.input name="q" :value="$filters['q']" placeholder="جستجو با موضوع، شماره تیکت یا موبایل" autocomplete="off" />
        </label>
        <label>
            <span class="sr-only">نوع</span>
            <x-form.select name="type" :value="$filters['type']" :options="['' => 'همه انواع'] + $types" />
        </label>
        <div class="flex gap-2">
            <label class="min-w-0 flex-1">
                <span class="sr-only">وضعیت</span>
                <x-form.select name="status" :value="$filters['status']" :options="['' => 'همه وضعیت‌ها'] + $statuses" />
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
            <table class="w-full min-w-[48rem] text-sm">
                <thead>
                    <tr class="border-b border-line text-xs font-semibold text-muted">
                        <th class="px-5 py-3 text-start font-semibold">#</th>
                        <th class="px-5 py-3 text-start font-semibold">موضوع</th>
                        <th class="px-5 py-3 text-start font-semibold">کاربر</th>
                        <th class="px-5 py-3 text-start font-semibold">نوع</th>
                        <th class="px-5 py-3 text-start font-semibold">وضعیت</th>
                        <th class="px-5 py-3 text-start font-semibold">آخرین فعالیت</th>
                        <th class="px-5 py-3"><span class="sr-only">جزئیات</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-2">
                    @forelse ($tickets as $ticket)
                        <tr data-ticket-row data-ticket-id="{{ $ticket['id'] }}" data-ticket-status="{{ $ticket['status']->value }}">
                            <td class="num px-5 py-3 text-xs text-muted">{{ $ticket['id'] }}</td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink">{{ $ticket['subject'] }}</p>
                                <p class="num mt-0.5 text-xs text-muted-3">{{ $ticket['messagesCount'] }} پیام</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-ink">{{ $ticket['userName'] ?? '—' }}</p>
                                <p class="ltr num mt-0.5 text-xs text-muted-3">{{ $ticket['userPhone'] }}</p>
                            </td>
                            <td class="px-5 py-3 text-xs text-muted">{{ $ticket['type']->label() }}</td>
                            <td class="px-5 py-3">
                                <x-panel.pill :tone="$ticket['status']->tone()" dot>{{ $ticket['status']->label() }}</x-panel.pill>
                            </td>
                            <td class="num px-5 py-3 text-xs text-muted">{{ $ticket['lastActivityAt'] ?? '—' }}</td>
                            <td class="px-5 py-3 text-end">
                                <a href="{{ route('panel.tickets.show', $ticket['id']) }}"
                                   class="inline-flex size-9 items-center justify-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink"
                                   aria-label="جزئیات تیکت {{ $ticket['id'] }}" title="جزئیات">
                                    <x-icon name="eye" class="size-4" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-muted">تیکتی با این فیلترها پیدا نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tickets->lastPage() > 1)
            <nav class="flex items-center justify-between gap-3 border-t border-line px-5 py-3 text-sm" aria-label="صفحه‌بندی">
                <span class="num text-xs text-muted">صفحه {{ $tickets->currentPage() }} از {{ $tickets->lastPage() }} — {{ $tickets->total() }} تیکت</span>
                <div class="flex gap-2">
                    @if ($tickets->previousPageUrl())
                        <a href="{{ $tickets->previousPageUrl() }}" class="inline-flex h-9 items-center rounded-lg px-3 text-ink ring-1 ring-line hover:bg-surface-3">قبلی</a>
                    @endif
                    @if ($tickets->nextPageUrl())
                        <a href="{{ $tickets->nextPageUrl() }}" class="inline-flex h-9 items-center rounded-lg px-3 text-ink ring-1 ring-line hover:bg-surface-3">بعدی</a>
                    @endif
                </div>
            </nav>
        @endif
    </section>
</x-layouts.panel>
