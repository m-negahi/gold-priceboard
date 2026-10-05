{{--
    Host-e toast ha — yek bar tu layout-e panel. Manba'-e haghighat `$store.toasts`-e
    (resources/js/alpine/toasts.js); in faghat render mikone.

    Ja: goshe-ye payin-e "end" (chap tu RTL) ta rooye sidebar-e samt-e rast nayofte. Khata va
    hoshdar `role="alert"` migiran (screen reader hamun lahze mikhoone), baghi `role="status"`.
    Flash-e Blade (`session('toast')`) ham az hamin ja vared-e store mishe ta yek redirect-e
    server hamun toast-i ro bede ke yek darkhast-e JSON mide.
--}}
@php
    $flash = session('toast');
    $flash = is_array($flash) ? $flash : ($flash ? ['kind' => 'success', 'title' => (string) $flash] : null);
@endphp

<div x-data
     @if ($flash) x-init="$store.toasts.push(@js($flash))" @endif
     class="pointer-events-none fixed bottom-4 end-4 z-(--bp-z-toast) flex w-[calc(100%-2rem)] max-w-sm flex-col gap-3 sm:bottom-6 sm:end-6"
     data-toasts>
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div x-data="{ shown: false }" x-init="$nextTick(() => shown = true)" x-show="shown"
             x-transition:enter="transition duration-(--bp-dur-base) ease-out"
             x-transition:enter-start="translate-y-2 opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             :role="toast.kind === 'error' || toast.kind === 'warning' ? 'alert' : 'status'"
             :data-kind="toast.kind"
             @mouseenter="$store.toasts.pause(toast.id)" @mouseleave="$store.toasts.resume(toast.id)"
             @focusin="$store.toasts.pause(toast.id)" @focusout="$store.toasts.resume(toast.id)"
             class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-surface p-4 shadow-(--bp-elev-lg)"
             :class="{
                 'border-ok/25': toast.kind === 'success',
                 'border-danger/25': toast.kind === 'error',
                 'border-warn/25': toast.kind === 'warning',
                 'border-info/25': toast.kind === 'info',
             }">
            <span class="grid size-8 shrink-0 place-items-center rounded-full"
                  :class="{
                      'bg-ok-soft text-ok': toast.kind === 'success',
                      'bg-danger-soft text-danger': toast.kind === 'error',
                      'bg-warn-soft text-warn': toast.kind === 'warning',
                      'bg-info-soft text-info': toast.kind === 'info',
                  }" aria-hidden="true">
                <x-icon name="circle-check" class="size-5" x-show="toast.kind === 'success'" />
                <x-icon name="circle-x" class="size-5" x-show="toast.kind === 'error'" />
                <x-icon name="alert" class="size-5" x-show="toast.kind === 'warning'" />
                <x-icon name="info" class="size-5" x-show="toast.kind === 'info'" />
            </span>

            <div class="min-w-0 flex-1 text-sm">
                <p class="font-semibold text-ink" x-text="toast.title"></p>
                <p x-show="toast.message" class="mt-0.5 text-muted" x-text="toast.message"></p>
            </div>

            <button type="button" @click="$store.toasts.dismiss(toast.id)" aria-label="بستن"
                    class="-m-1 grid size-7 shrink-0 place-items-center rounded-md text-muted-2 hover:bg-surface-3 hover:text-ink">
                <x-icon name="close" class="size-4" />
            </button>
        </div>
    </template>
</div>
