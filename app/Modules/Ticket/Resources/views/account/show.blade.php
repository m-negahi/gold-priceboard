{{-- Yek ticket e KHOD e karbar (P7-01). Data az `TicketQuery::detail(staff: false)`:
     javab e admin «پشتیبانی» ast va audit penhan. --}}

<x-layouts.site :title="'تیکت #'.$ticket['id']">
    <div class="container py-8"><div class="mx-auto max-w-3xl">
        <a href="{{ route('account.tickets.index') }}" class="inline-flex items-center gap-1 text-xs text-muted hover:text-ink">
            <x-icon name="arrow-back" class="size-3.5 rtl:-scale-x-100" />
            تیکت‌های من
        </a>
        <h1 class="mt-1 text-2xl font-extrabold text-ink">{{ $ticket['subject'] }}</h1>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <span class="num text-sm text-muted">#{{ $ticket['id'] }}</span>
            <x-panel.pill tone="flat">{{ $ticket['type']->label() }}</x-panel.pill>
            <x-panel.pill :tone="$ticket['status']->tone()" dot data-ticket-status="{{ $ticket['status']->value }}">{{ $ticket['status']->label() }}</x-panel.pill>
        </div>

        @if (session('status'))
            <p class="mt-4 rounded-xl bg-ok-soft px-4 py-2 text-sm text-ok" role="status" data-account-status>{{ session('status') }}</p>
        @endif

        <ol class="mt-6 space-y-3">
            @foreach ($messages as $message)
                <li data-ticket-message data-staff="{{ $message['staff'] ? 1 : 0 }}"
                    @class(['rounded-2xl p-4 shadow-xs', 'bg-brand-50 me-6' => $message['staff'], 'bg-surface ms-6' => ! $message['staff']])>
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
                        <span class="font-semibold text-ink">{{ $message['author'] }}</span>
                        <span class="num">{{ $message['at'] }}</span>
                    </div>
                    <p class="mt-2 whitespace-pre-line break-words text-sm text-ink">{{ $message['body'] }}</p>
                </li>
            @endforeach
        </ol>

        @if ($ticket['canReply'])
            <form method="POST" action="{{ route('account.tickets.reply', $ticket['id']) }}" class="mt-6 space-y-3 rounded-2xl bg-surface card-p shadow-xs" data-ticket-reply>
                @csrf
                <x-form.field name="body" label="پیام شما" required>
                    <x-form.textarea name="body" rows="4" maxlength="5000">{{ old('body') }}</x-form.textarea>
                </x-form.field>
                <div class="flex justify-end">
                    <button type="submit"
                            class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-on-brand hover:bg-brand-2">
                        <x-icon name="sms" class="size-4" />
                        ارسال
                    </button>
                </div>
            </form>
        @else
            <p class="mt-6 rounded-xl bg-surface-3 px-4 py-3 text-center text-sm text-muted">این تیکت بسته شده است.</p>
        @endif
    </div></div>
</x-layouts.site>
