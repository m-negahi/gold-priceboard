{{--
    Dialog-e ta'yid-e sarasari — yek bar tu layout. Har ja: `await $store.confirm.ask({...})`.
    Focus-e avalie rooye «انصراف» mishine (x-trap avalin focusable ro migire — `autofocus` dakhel-e
    x-show kar nemikone chon element az aval tu DOM hast), ta Enter-e nakhaste chizi ro pak nakone;
    variant-e danger dokme-ye ta'yid ro ghermez mikone.
--}}
<div x-data x-cloak x-show="$store.confirm.open"
     @keydown.escape.window="$store.confirm.open && $store.confirm.cancel()"
     @click.self="$store.confirm.cancel()"
     class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" data-confirm>

    <div x-show="$store.confirm.open" x-transition.opacity.duration.180ms class="pointer-events-none absolute inset-0 bg-ink/40" aria-hidden="true"></div>

    <div x-show="$store.confirm.open" x-trap.noscroll.inert="$store.confirm.open"
         x-transition:enter="transition duration-(--bp-dur-base) ease-out"
         x-transition:enter-start="translate-y-2 opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-body"
         class="relative w-full max-w-sm rounded-2xl bg-surface p-6 shadow-(--bp-elev-lg)">

        <div class="flex items-start gap-4">
            <span class="grid size-10 shrink-0 place-items-center rounded-full"
                  :class="$store.confirm.danger ? 'bg-danger-soft text-danger' : 'bg-info-soft text-info'" aria-hidden="true">
                <x-icon name="alert" class="size-5" x-show="$store.confirm.danger" />
                <x-icon name="info" class="size-5" x-show="!$store.confirm.danger" />
            </span>
            <div class="min-w-0 flex-1">
                <h2 id="confirm-title" class="text-base font-semibold text-ink" x-text="$store.confirm.title"></h2>
                <p id="confirm-body" x-show="$store.confirm.body" class="mt-1.5 text-sm text-muted" x-text="$store.confirm.body"></p>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <button type="button" @click="$store.confirm.cancel()"
                    class="inline-flex h-10 items-center rounded-lg bg-surface px-4 text-sm font-medium text-ink ring-1 ring-line hover:bg-surface-3"
                    x-text="$store.confirm.cancelLabel"></button>
            <button type="button" @click="$store.confirm.accept()"
                    class="inline-flex h-10 items-center rounded-lg px-4 text-sm font-semibold text-on-brand"
                    :class="$store.confirm.danger ? 'bg-danger hover:bg-danger/90' : 'bg-brand hover:bg-brand-2'"
                    x-text="$store.confirm.confirmLabel"></button>
        </div>
    </div>
</div>
