@props([
    // ok | warn | down | up | flat | info
    'tone' => 'flat',
    // Ru background e tire (card e featured) — nime-shafaf e sefid.
    'inverted' => false,
    // Dot e rang-i e aval (health-badge roosh misaze).
    'dot' => false,
])

@php
    // Tone -> class. `up`/`down` semantic e GHEYMAT-and، `ok`/`warn` hālat
    // e system — amdan joda (ghanun e brand ≠ up/down hamin ja ham hast).
    $tones = [
        'ok' => 'bg-ok-soft text-ok',
        'up' => 'bg-up-soft text-up',
        'warn' => 'bg-warn-soft text-warn',
        'down' => 'bg-down-soft text-down',
        'info' => 'bg-info-soft text-info',
        'flat' => 'bg-surface-3 text-muted',
    ];
    $dots = [
        'ok' => 'bg-up-strong',
        'up' => 'bg-up-strong',
        'warn' => 'bg-warn',
        'down' => 'bg-down-strong',
        'info' => 'bg-info',
        'flat' => 'bg-muted-3',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold',
    $inverted ? 'bg-on-brand/15 text-on-brand' : ($tones[$tone] ?? $tones['flat']),
]) }}>
    @if ($dot)
        <span class="size-1.5 shrink-0 rounded-full {{ $inverted ? 'bg-on-brand' : ($dots[$tone] ?? $dots['flat']) }}" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
