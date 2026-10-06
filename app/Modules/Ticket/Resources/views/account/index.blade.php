{{-- Ticket haye KHOD e karbar (P7-01). Data az `TicketQuery::listForOwner`. --}}

<x-layouts.site title="تیکت‌های من">
    <div class="container-wide py-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-ink">تیکت‌های من</h1>
                <p class="mt-1 text-sm text-muted">درخواست‌ها و گفت‌وگو با پشتیبانی.</p>
            </div>
            <div class="flex flex-wrap gap-2">
            <a href="{{ route('onboarding.show') }}" data-kyc-link
               class="inline-flex h-11 items-center gap-2 rounded-xl px-4 text-sm font-semibold text-ink ring-1 ring-line hover:bg-surface-3">
                <x-icon name="shield" class="size-4" />
                فاکتور رسمی / ارتقای سطح
            </a>
            <a href="{{ route('account.tickets.create') }}" data-ticket-create
               class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand shadow-(--bp-elev-brand) hover:bg-brand-2">
                <x-icon name="plus" class="size-4" />
                تیکت جدید
            </a>
            </div>
        </div>

        @if (session('status'))
            <p class="mt-4 rounded-xl bg-ok-soft px-4 py-2 text-sm text-ok" role="status" data-account-status>{{ session('status') }}</p>
        @endif

        <ul class="mt-6 space-y-3">
            @forelse ($tickets as $ticket)
                <li data-ticket-row data-ticket-id="{{ $ticket['id'] }}">
                    <a href="{{ route('account.tickets.show', $ticket['id']) }}"
                       class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-surface p-4 shadow-xs ring-1 ring-transparent hover:ring-line">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-ink">{{ $ticket['subject'] }}</p>
                            <p class="num mt-1 text-xs text-muted">#{{ $ticket['id'] }} · {{ $ticket['type']->label() }} · {{ $ticket['lastActivityAt'] }}</p>
                        </div>
                        <x-panel.pill :tone="$ticket['status']->tone()" dot>{{ $ticket['status']->label() }}</x-panel.pill>
                    </a>
                </li>
            @empty
                <li class="rounded-2xl bg-surface px-4 py-10 text-center text-sm text-muted shadow-xs">هنوز تیکتی ثبت نکرده‌اید.</li>
            @endforelse
        </ul>
    </div>
</x-layouts.site>
