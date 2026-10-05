@props([
    'title' => 'راهنمای این صفحه',
    'key' => null,
    'label' => 'راهنما',
])

{{--
    Rahnama-ye safhe: har safhe-ye form/list yeki dare (ghanoon-e panel). Mohtava tu slot miad —
    matn-e sade ba <h3>/<p>/<ul>; jadval-e "field → ma'ni" baraye form ha. `key` esm-e safhe-ast
    ta "jadid" boodan-esh yek bar per browser yad-avari beshe.
--}}
<div x-data="panelHelp({ key: @js($key) })" x-id="['help']" {{ $attributes->only('class') }}>
    <button type="button" @click="show()"
            class="relative inline-flex h-9 items-center gap-1.5 rounded-lg bg-surface px-3 text-sm font-medium text-ink shadow-(--bp-elev-sm) ring-1 ring-line hover:bg-surface-2"
            :aria-expanded="open" aria-haspopup="dialog" data-help-button>
        <x-icon name="info" class="size-4 text-brand" />
        {{ $label }}
        <span x-show="!seen" x-cloak class="absolute -top-1 -left-1 size-2 rounded-full bg-brand" aria-hidden="true"></span>
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" :aria-labelledby="$id('help') + '-title'" data-help-drawer
             @keydown.escape.window="close()">
            <div class="absolute inset-0 bg-ink/40" @click="close()" aria-hidden="true"
                 x-show="open" x-transition.opacity.duration.150ms></div>

            <aside x-show="open" x-trap.noscroll.inert="open"
                   x-transition:enter="transition duration-(--bp-dur-base) ease-out" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                   x-transition:leave="transition duration-(--bp-dur-fast) ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                   class="absolute inset-y-0 left-0 flex w-full max-w-md flex-col bg-surface shadow-(--bp-elev-lg)">
                <header class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <h2 :id="$id('help') + '-title'" class="text-base font-bold text-ink">{{ $title }}</h2>
                    <button type="button" @click="close()" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-surface-2 hover:text-ink" aria-label="بستن راهنما">
                        <x-icon name="close" class="size-5" />
                    </button>
                </header>

                <div class="prose-help flex-1 space-y-4 overflow-y-auto px-5 py-5 text-sm leading-7 text-ink-2">
                    {{ $slot }}
                </div>

                <footer class="border-t border-line px-5 py-3 text-xs text-muted-2">
                    کلید <kbd class="rounded border border-line bg-surface-2 px-1.5 py-0.5 font-medium text-ink ltr">Esc</kbd> راهنما را می‌بندد.
                </footer>
            </aside>
        </div>
    </template>
</div>
