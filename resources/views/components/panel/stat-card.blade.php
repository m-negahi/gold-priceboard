@props([
    'label',
    'value',
    // Pill e riz e kenar e value: ['text' => '…', 'tone' => 'ok|warn|down|flat'].
    'pill' => null,
    // Matn e kuchik e zir (moghayese/tozih).
    'hint' => null,
    // Avvalin card e Donezo: gradient e brand + matn e sefid.
    'featured' => false,
    // Link e dokme ye gerd e ↗ (bala-chap tu RTL). Null = bedun e dokme.
    'href' => null,
    'icon' => null,
])

{{-- Stat card e Donezo (docs/dashboard-design.md §5). --}}
<div {{ $attributes->class([
    'relative rounded-2xl card-p',
    'bg-brand-grad text-on-brand' => $featured,
    'bg-surface shadow-xs' => ! $featured,
]) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-2">
            @if ($icon)
                <span @class([
                    'grid size-9 place-items-center rounded-xl',
                    'bg-on-brand/15 text-on-brand' => $featured,
                    'bg-brand-soft text-brand-2' => ! $featured,
                ])>
                    <x-icon :name="$icon" class="size-5" />
                </span>
            @endif
            <p @class(['text-sm font-medium', 'text-on-brand-2' => $featured, 'text-muted' => ! $featured])>{{ $label }}</p>
        </div>

        @if ($href)
            {{-- Arrow tu RTL be bala-CHAP eshare mikone (doc §7). --}}
            <a href="{{ $href }}" @class([
                'grid size-8 shrink-0 place-items-center rounded-full border transition-colors',
                'border-on-brand/30 text-on-brand hover:bg-on-brand/10' => $featured,
                'border-line text-muted hover:bg-surface-3 hover:text-ink' => ! $featured,
            ]) aria-label="{{ $label }}">
                <x-icon name="external" class="size-4 rtl:-scale-x-100" />
            </a>
        @endif
    </div>

    <p @class(['num mt-3 text-3xl font-extrabold leading-tight', 'text-on-brand' => $featured, 'text-ink' => ! $featured])>{{ $value }}</p>

    @if ($pill || $hint)
        <div class="mt-2 flex items-center gap-2">
            @if ($pill)
                <x-panel.pill :tone="$pill['tone'] ?? 'flat'" :inverted="$featured">{{ $pill['text'] }}</x-panel.pill>
            @endif
            @if ($hint)
                <span @class(['truncate text-xs', 'text-on-brand-2' => $featured, 'text-muted' => ! $featured])>{{ $hint }}</span>
            @endif
        </div>
    @endif
</div>
