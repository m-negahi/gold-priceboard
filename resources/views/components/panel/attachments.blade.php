@props(['files' => []])

{{-- Lista ye peyvast haye yek payam (P7-02). `url` link e EMZA-DAR e zamandar
     ast (az `TicketQuery`) — inja faghat namayesh. --}}
@if ($files !== [])
    <ul {{ $attributes->merge(['class' => 'mt-3 flex flex-wrap gap-2']) }}>
        @foreach ($files as $file)
            <li>
                <a href="{{ $file['url'] }}" data-attachment
                   class="inline-flex max-w-full items-center gap-2 rounded-lg bg-surface px-3 py-1.5 text-xs text-ink ring-1 ring-line hover:ring-muted-3">
                    <x-icon name="download" class="size-3.5 shrink-0 text-muted" />
                    <span class="ltr truncate">{{ $file['name'] }}</span>
                    <span class="ltr num shrink-0 text-muted-3">{{ $file['size'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
