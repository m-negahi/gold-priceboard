@props([
    'title' => null,
    'description' => null,
    'size' => 'md',
    'dismissible' => true,
])

{{--
    Modal-e panel. Halat-e baz/baste mal-e parent-e: `<x-panel.modal x-model="open">`.

    Focus dakhel-e panel gir miofte, scroll-e body ghofl mishe va baghi-ye safhe inert mishe
    (`x-trap.noscroll.inert`). Esc va click rooye backdrop mibandand magar `:dismissible="false"`.
    Chon backdrop inert-e, click-esh be wrapper mirese — be hamin dalil `@click.self` rooye root-e.
    Slot-e `footer` baraye dokme ha; `x-panel.button` ha unja mishinand.
--}}
@php
    $sizes = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
    ];
    $titleId = 'modal-'.uniqid().'-title';
@endphp

<div x-data="panelModal({ dismissible: @js((bool) $dismissible) })" x-modelable="open"
     {{ $attributes->whereStartsWith('x-') }}
     x-show="open" x-cloak
     @keydown.escape.window="onEscape()"
     @click.self="close()"
     class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">

    <div x-show="open" x-transition.opacity.duration.180ms class="pointer-events-none absolute inset-0 bg-ink/40" aria-hidden="true"></div>

    <div x-show="open" x-trap.noscroll.inert="open"
         x-transition:enter="transition duration-(--bp-dur-base) ease-out"
         x-transition:enter-start="translate-y-2 opacity-0 sm:scale-[0.98]"
         x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
         x-transition:leave="transition duration-(--bp-dur-fast) ease-in"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         role="dialog" aria-modal="true" @if ($title) aria-labelledby="{{ $titleId }}" @endif
         {{ $attributes->whereDoesntStartWith('x-')->merge(['class' => 'relative flex max-h-[calc(100dvh-2rem)] w-full flex-col rounded-2xl bg-surface shadow-(--bp-elev-lg) '.($sizes[$size] ?? $sizes['md'])]) }}>

        @if ($title || $dismissible)
            <div class="flex items-start gap-3 px-6 pt-5">
                <div class="min-w-0 flex-1">
                    @if ($title)
                        <h2 id="{{ $titleId }}" class="text-lg font-semibold text-ink">{{ $title }}</h2>
                    @endif
                    @if ($description)
                        <p class="mt-1 text-sm text-muted">{{ $description }}</p>
                    @endif
                </div>
                @if ($dismissible)
                    <button type="button" @click="close()" aria-label="بستن"
                            class="-me-2 -mt-1 grid size-9 shrink-0 place-items-center rounded-lg text-muted-2 hover:bg-surface-3 hover:text-ink">
                        <x-icon name="close" class="size-5" />
                    </button>
                @endif
            </div>
        @endif

        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-line bg-surface-2 px-6 py-4 rounded-b-2xl">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
