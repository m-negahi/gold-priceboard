@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'icon' => null,
])

{{--
    Dokme-ye panel. `variant`: primary / secondary / danger / ghost. `size`: sm / md / lg.
    Ba `href` yek link-e ham-shekl mishe. `icon` esm-e x-icon-e ke ghabl az matn mishine.
    Ranghaye market (up/down) inja ja nadaran — dokme-ye khatarnak `danger`-e (design-system §3).
--}}
@php
    $variants = [
        'primary' => 'bg-brand text-on-brand shadow-(--bp-elev-brand) hover:bg-brand-2 disabled:bg-brand/60 disabled:shadow-none',
        'secondary' => 'bg-surface text-ink ring-1 ring-line shadow-(--bp-elev-sm) hover:bg-surface-3 disabled:text-muted-2',
        'danger' => 'bg-danger text-on-brand hover:bg-danger/90 disabled:bg-danger/60',
        'ghost' => 'text-ink-3 hover:bg-surface-3 hover:text-ink disabled:text-muted-2',
    ];
    $sizes = [
        'sm' => 'h-9 px-3 text-sm gap-1.5',
        'md' => 'h-10 px-4 text-sm gap-2',
        'lg' => 'h-12 px-5 text-base gap-2',
    ];
    $classes = 'inline-flex shrink-0 items-center justify-center rounded-lg font-medium transition-[background-color,color,transform] duration-(--bp-dur-fast) active:scale-[0.99] disabled:cursor-not-allowed disabled:active:scale-100 '
        .($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);

    // target="_blank" bedoone rel="noopener" tab-e jadid dastresi be window.opener mide (tabnabbing).
    // Chizi ke halate az $attributes bood hazf mishe ta merge() dobare ezafe kone, na inke rad kone.
    $blank = $attributes->get('target') === '_blank';
    $relWords = array_values(array_unique(array_filter([
        ...array_filter(explode(' ', (string) $attributes->get('rel'))),
        ...($blank ? ['noopener', 'noreferrer'] : []),
    ])));
    $rel = $relWords === [] ? null : implode(' ', $relWords);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->except('rel')->merge(array_filter(['class' => $classes, 'rel' => $rel])) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </button>
@endif
