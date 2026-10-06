@props([
    // [['label' => '…', 'value' => int, 'active' => bool], …]
    'bars' => [],
    // Saghf e mehvar؛ null = bishtarin value.
    'max' => null,
    'height' => 'h-36',
])

@php
    $values = array_map(static fn (array $b): float => (float) ($b['value'] ?? 0), $bars);
    $ceiling = max(1.0, (float) ($max ?? ($values === [] ? 1 : max($values))));
@endphp

{{-- Bar chart e Donezo (doc §5): bar haye CAPSULE؛ fa'al = brand، gheyr e
     fa'al = hachure (emza ye theme). --}}
<div {{ $attributes->class(['flex items-end justify-between gap-2', $height]) }} role="img">
    @foreach ($bars as $bar)
        @php $ratio = max(0.06, min(1, ((float) ($bar['value'] ?? 0)) / $ceiling)); @endphp
        <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1.5">
            <div class="flex w-full max-w-7 flex-1 items-end">
                {{-- `--bar-h` DATA-st (darsad e mohasebe-shode)، na design token؛
                     utility ye `h-(--bar-h)` masraf esh mikone — hich style e
                     tarrahi tu Blade nist (hamun ruh e ghanun e «hich style»). --}}
                <div @class([
                        'h-(--bar-h) w-full rounded-full',
                        'bg-brand-500' => (bool) ($bar['active'] ?? false),
                        'hachure' => ! ($bar['active'] ?? false),
                    ])
                     style="--bar-h: {{ round($ratio * 100, 1) }}%"
                     title="{{ $bar['label'] ?? '' }}: {{ $bar['value'] ?? 0 }}"></div>
            </div>
            @if (($bar['label'] ?? '') !== '')
                <span class="num max-w-full truncate text-[0.6875rem] text-muted-2">{{ $bar['label'] }}</span>
            @endif
        </div>
    @endforeach
</div>
