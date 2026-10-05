@props(['title' => null, 'description' => null, 'padded' => true])

{{--
    Kart-e yek bakhsh: header (title, tosif, slot-e `actions`), badane, va slot-e `footer` rooye
    surface-2. `:padded="false"` baraye jadval ha ke ta labe miran.
--}}
<section {{ $attributes->merge(['class' => 'rounded-card border border-line bg-surface']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-muted">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['p-5' => $padded])>{{ $slot }}</div>

    @isset($footer)
        <footer class="flex flex-wrap items-center justify-end gap-2 rounded-b-card border-t border-line bg-surface-2 px-5 py-3">{{ $footer }}</footer>
    @endisset
</section>
