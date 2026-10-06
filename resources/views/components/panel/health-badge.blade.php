@props([
    // healthy | degraded | down | unknown — string ya HealthState.
    'state' => 'unknown',
])

@php
    $value = $state instanceof \App\Modules\Pricing\Domain\Enums\HealthState ? $state->value : (string) $state;

    [$tone, $label] = match ($value) {
        'healthy' => ['ok', 'سالم'],
        'degraded' => ['warn', 'نیازمند بررسی'],
        'down' => ['down', 'قطع'],
        default => ['flat', 'نامشخص'],
    };
@endphp

<x-panel.pill :tone="$tone" dot {{ $attributes }}>{{ $label }}</x-panel.pill>
