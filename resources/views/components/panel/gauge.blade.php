@props([
    // 0..100
    'percent' => 0,
    'label' => null,
    // Legend: [['label' => '…', 'tone' => 'ok|warn|down|flat'], …]
    'legend' => [],
])

@php
    $clamped = max(0, min(100, (float) $percent));
    // Nim-dayere ba r=40: tool e kaman = pi * r ≈ 125.66.
    $arc = 125.66;
    $filled = $arc * $clamped / 100;
    $dots = ['ok' => 'bg-up-strong', 'warn' => 'bg-warn', 'down' => 'bg-down-strong', 'flat' => 'bg-muted-3'];
@endphp

{{-- Gauge ye nim-donut e Donezo (doc §5): darsad e bozorg vasat + legend. --}}
<div {{ $attributes->class(['flex flex-col items-center']) }}>
    <div class="relative w-full max-w-[12rem]">
        <svg viewBox="0 0 100 55" class="w-full" role="img" @if ($label) aria-label="{{ $label }}: {{ round($clamped) }}٪" @endif>
            {{-- Rah e khali --}}
            <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" class="stroke-surface-3" stroke-width="10" stroke-linecap="round" />
            {{-- Meghdar — rang az token e brand (chrome)، na up/down. --}}
            <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" class="stroke-brand-500" stroke-width="10" stroke-linecap="round"
                  stroke-dasharray="{{ number_format($filled, 2, '.', '') }} {{ $arc }}" />
        </svg>
        <div class="absolute inset-x-0 bottom-0 text-center">
            <span class="num text-2xl font-extrabold text-ink">{{ round($clamped) }}<span class="text-base font-bold text-muted">٪</span></span>
        </div>
    </div>

    @if ($label)
        <p class="mt-1 text-sm text-muted">{{ $label }}</p>
    @endif

    @if ($legend !== [])
        <ul class="mt-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs text-muted">
            @foreach ($legend as $item)
                <li class="inline-flex items-center gap-1.5">
                    <span class="size-1.5 rounded-full {{ $dots[$item['tone'] ?? 'flat'] ?? $dots['flat'] }}" aria-hidden="true"></span>
                    {{ $item['label'] }}
                </li>
            @endforeach
        </ul>
    @endif
</div>
