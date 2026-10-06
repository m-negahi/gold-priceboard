{{-- Ticket e jadid ba form e sakhtar-dar e hoviat. --}}

<x-layouts.site title="تیکت جدید">
    <div class="container py-8"><div class="mx-auto max-w-2xl">
        <a href="{{ route('account.tickets.index') }}" class="inline-flex items-center gap-1 text-xs text-muted hover:text-ink">
            <x-icon name="arrow-back" class="size-3.5 rtl:-scale-x-100" />
            تیکت‌های من
        </a>
        <h1 class="mt-1 text-2xl font-extrabold text-ink">تیکت جدید</h1>

        <form method="POST" action="{{ route('account.tickets.store') }}" enctype="multipart/form-data" class="mt-6 space-y-4 rounded-2xl bg-surface card-p-lg shadow-xs" data-ticket-create-form x-data="{ type: @js(old('request_type', 'support')) }">
            @csrf
            @include('ticket::account.request-fields', ['typeField' => 'request_type'])
            <div class="flex justify-end">
                <button type="submit"
                        class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-on-brand hover:bg-brand-2">
                    <x-icon name="check" class="size-4" />
                    ثبت تیکت
                </button>
            </div>
        </form>
    </div></div>
</x-layouts.site>
