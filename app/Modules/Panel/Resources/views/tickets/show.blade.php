{{-- Safhe ye yek ticket (P7-01). Data kamel az `TicketQuery::detail(staff: true)`
     — Blade faghat namayesh. Form ha POST e sade + redirect. --}}

<x-layouts.panel :title="'تیکت #'.$ticket['id']" current="tickets" :breadcrumbs="['پنل', 'تیکت‌ها', '#'.$ticket['id']]">
    <div class="min-w-0">
        <a href="{{ route('panel.tickets.index') }}" class="inline-flex items-center gap-1 text-xs text-muted hover:text-ink">
            <x-icon name="arrow-back" class="size-3.5 rtl:-scale-x-100" />
            بازگشت به تیکت‌ها
        </a>
        <h1 class="mt-1 text-2xl font-extrabold text-ink md:text-3xl">{{ $ticket['subject'] }}</h1>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <span class="num text-sm text-muted">#{{ $ticket['id'] }}</span>
            <x-panel.pill tone="flat">{{ $ticket['type']->label() }}</x-panel.pill>
            <x-panel.pill :tone="$ticket['status']->tone()" dot data-ticket-status="{{ $ticket['status']->value }}">{{ $ticket['status']->label() }}</x-panel.pill>
            <span class="num text-xs text-muted-3">ثبت: {{ $ticket['createdAt'] }}</span>
        </div>
    </div>

    @if ($errors->any())
        <p class="mt-3 rounded-xl bg-down-soft px-4 py-2 text-sm text-down" role="alert" data-ticket-error>{{ $errors->first() }}</p>
    @endif

    <div class="mt-6 grid gap-4 xl:grid-cols-3">
        <div class="min-w-0 space-y-4 xl:col-span-2">
            @if ($ticket['metaRows'] !== [])
                <section class="rounded-2xl bg-surface card-p shadow-xs" aria-labelledby="meta-title" data-ticket-meta>
                    <h2 id="meta-title" class="text-base font-bold text-ink">اطلاعات درخواست</h2>
                    <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        @foreach ($ticket['metaRows'] as $row)
                            <div class="min-w-0">
                                <dt class="text-xs text-muted">{{ $row['label'] }}</dt>
                                <dd class="mt-0.5 break-words text-ink" data-meta="{{ $row['key'] }}">{{ $row['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif

            {{-- ── Goft-o-gu ───────────────────────────────────────── --}}
            <section class="rounded-2xl bg-surface card-p shadow-xs" aria-labelledby="messages-title">
                <h2 id="messages-title" class="text-base font-bold text-ink">گفت‌وگو</h2>
                <ol class="mt-4 space-y-3">
                    @foreach ($messages as $message)
                        <li data-ticket-message data-staff="{{ $message['staff'] ? 1 : 0 }}"
                            @class(['rounded-xl p-4', 'bg-brand-50 ms-6' => $message['staff'], 'bg-surface-2 me-6' => ! $message['staff']])>
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
                                <span class="font-semibold text-ink">{{ $message['author'] }}</span>
                                <span class="num">{{ $message['at'] }}</span>
                            </div>
                            <p class="mt-2 whitespace-pre-line break-words text-sm text-ink">{{ $message['body'] }}</p>
                        </li>
                    @endforeach
                </ol>

                @if ($ticket['canReply'])
                    <form method="POST" action="{{ route('panel.tickets.reply', $ticket['id']) }}" class="mt-4 space-y-3" data-ticket-reply>
                        @csrf
                        <x-form.field name="body" label="پاسخ پشتیبانی" required>
                            <x-form.textarea name="body" rows="4" maxlength="5000">{{ old('body') }}</x-form.textarea>
                        </x-form.field>
                        <div class="flex justify-end">
                            <button type="submit"
                                    class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-on-brand hover:bg-brand-2">
                                <x-icon name="sms" class="size-4" />
                                ارسال پاسخ
                            </button>
                        </div>
                    </form>
                @else
                    <p class="mt-4 rounded-xl bg-surface-3 px-4 py-3 text-center text-sm text-muted">تیکت بسته است؛ برای پاسخ آن را دوباره باز کنید.</p>
                @endif
            </section>
        </div>

        <div class="min-w-0 space-y-4">
            @if ($owner)
                <section class="rounded-2xl bg-surface card-p shadow-xs" aria-labelledby="owner-title">
                    <h2 id="owner-title" class="text-base font-bold text-ink">کاربر</h2>
                    <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm">
                        <dt class="text-muted">نام</dt>
                        <dd class="text-end text-ink">{{ $owner['name'] ?? '—' }}</dd>
                        <dt class="text-muted">موبایل</dt>
                        <dd class="ltr num text-end text-ink">{{ $owner['phone'] }}</dd>
                        <dt class="text-muted">سطح</dt>
                        <dd class="text-end text-ink" data-owner-level="{{ $owner['level']->value }}">{{ $owner['level']->label() }}</dd>
                        <dt class="text-muted">نوع</dt>
                        <dd class="text-end text-ink">{{ $owner['customerType']->label() }}</dd>
                    </dl>
                    <a href="{{ route('panel.users.show', $owner['id']) }}" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-800">
                        صفحه کاربر
                        <x-icon name="arrow-back" class="size-3.5" />
                    </a>
                </section>
            @endif

            @if ($statusTargets !== [])
                <section class="rounded-2xl bg-surface card-p shadow-xs" aria-labelledby="status-title">
                    <h2 id="status-title" class="text-base font-bold text-ink">تغییر وضعیت</h2>
                    <form method="POST" action="{{ route('panel.tickets.status', $ticket['id']) }}" class="mt-4 space-y-3" data-ticket-status-form>
                        @csrf
                        <x-form.field name="status" label="وضعیت جدید">
                            <x-form.select name="status" :options="$statusTargets" />
                        </x-form.field>
                        <x-form.field name="note" label="یادداشت (اختیاری)">
                            <x-form.input name="note" maxlength="500" />
                        </x-form.field>
                        <button type="submit"
                                class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold text-ink ring-1 ring-line hover:bg-surface-3">
                            <x-icon name="refresh" class="size-4" />
                            ثبت وضعیت
                        </button>
                    </form>
                </section>
            @endif

            {{-- ── Audit e vaziat ─────────────────────────────────── --}}
            <section class="rounded-2xl bg-surface card-p shadow-xs" aria-labelledby="events-title">
                <h2 id="events-title" class="text-base font-bold text-ink">تاریخچه</h2>
                <ol class="mt-4 space-y-3 border-s border-line ps-4">
                    @foreach ($events as $event)
                        <li class="text-sm" data-ticket-event data-kind="{{ $event['kind'] }}">
                            <p class="font-medium text-ink">
                                {{ $event['label'] }}
                                @if ($event['from'] && $event['to'])
                                    <span class="text-muted">— {{ $event['from'] }} ← {{ $event['to'] }}</span>
                                @elseif ($event['to'])
                                    <span class="text-muted">— {{ $event['to'] }}</span>
                                @endif
                            </p>
                            @if ($event['note'])
                                <p class="mt-0.5 text-xs text-muted">{{ $event['note'] }}</p>
                            @endif
                            <p class="num mt-0.5 text-xs text-muted-3">{{ $event['actor'] }} · {{ $event['at'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
    </div>
</x-layouts.panel>
