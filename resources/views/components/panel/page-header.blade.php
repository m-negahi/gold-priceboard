@props(['title', 'description' => null])

{{--
    Sar-e har safhe-ye panel: breadcrumb (slot-e ekhtiari), h1, yek khat tosif, va dokme ha samt-e
    "end" (slot-e `actions`). Rooye mobile dokme ha zir-e title miran.
--}}
<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-3']) }}>
    <div class="min-w-0">
        @isset($breadcrumb)
            <nav class="mb-1.5 flex flex-wrap items-center gap-1.5 text-xs text-muted" aria-label="مسیر">{{ $breadcrumb }}</nav>
        @endisset
        <h1 class="text-xl font-semibold leading-tight text-ink">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-muted">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
