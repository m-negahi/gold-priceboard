@props([
    'name' => null,
    'label' => 'متن',
    'hint' => null,
    'value' => '',
    'placeholder' => 'متن صفحه را این‌جا بنویسید…',
])

{{--
    Editor-e rich-text (Tiptap, faghat panel — ADR-0024 §2.11). Dokme-haye toolbar `@mousedown.prevent`
    daran ta focus va stored-mark-e ProseMirror ba click nare (vagarna «پررنگ» rooye matn-e ba'di e'mal nemishe). Meghdar HTML-e (x-modelable `value`
    / hidden input); toolbar hamun allow-list-e HtmlSanitizer-e. Tasvir az modal-e ketabkhane
    (`<x-panel.media-library />` rooye safhe) miad. Alpine: resources/js/alpine/rich-text.js
--}}
@php
    $id = \App\Modules\Panel\Support\FormControl::id($name) ?? 'rt-'.uniqid();
    $tools = [
        ['h2', 'heading', 'تیتر ۲'], ['h3', 'heading', 'تیتر ۳'], ['bold', 'bold', 'پررنگ'], ['italic', 'italic', 'مورب'],
        ['underline', 'underline', 'زیرخط'], ['strike', 'strike', 'خط‌خورده'], ['bulletList', 'list', 'فهرست'],
        ['orderedList', 'list-ordered', 'فهرست شماره‌دار'], ['blockquote', 'quote', 'نقل‌قول'],
    ];
@endphp

<div x-data="richText({ value: @js((string) $value), placeholder: @js($placeholder) })" x-modelable="value"
     {{ $attributes->merge(['class' => 'min-w-0']) }} data-rich-text>
    @if ($label)<p class="mb-1.5 text-sm font-medium text-ink" id="{{ $id }}-label">{{ $label }}</p>@endif
    @if ($name)<input type="hidden" name="{{ $name }}" :value="value">@endif

    <div class="overflow-hidden rounded-xl border border-line-3 bg-surface focus-within:border-brand focus-within:shadow-(--bp-elev-brand)">
        <div class="flex flex-wrap items-center gap-0.5 border-b border-line bg-surface-2 px-2 py-1.5" role="toolbar" aria-label="ابزارهای ویرایش" aria-controls="{{ $id }}">
            @foreach ($tools as [$cmd, $icon, $title])
                <button type="button" @mousedown.prevent @click="cmd('{{ $cmd }}')" :aria-pressed="!!active.{{ $cmd }}" :disabled="!ready" title="{{ $title }}" aria-label="{{ $title }}"
                        class="grid size-8 place-items-center rounded-md text-ink-3 hover:bg-surface-3 hover:text-ink disabled:opacity-40 aria-pressed:bg-brand-soft aria-pressed:text-brand-2"
                        data-rt-tool="{{ $cmd }}">
                    <x-icon :name="$icon" class="size-4" />
                    @if ($cmd === 'h3')<span class="sr-only">۳</span>@endif
                </button>
                @if (in_array($cmd, ['h3', 'strike', 'orderedList'], true))
                    <span class="mx-1 h-5 w-px bg-line-3" aria-hidden="true"></span>
                @endif
            @endforeach

            <div class="relative">
                <button type="button" @mousedown.prevent @click="openLink()" :aria-pressed="!!active.link" :disabled="!ready" title="پیوند" aria-label="پیوند"
                        class="grid size-8 place-items-center rounded-md text-ink-3 hover:bg-surface-3 hover:text-ink disabled:opacity-40 aria-pressed:bg-brand-soft aria-pressed:text-brand-2" data-rt-tool="link">
                    <x-icon name="link" class="size-4" />
                </button>
                <div x-show="linkOpen" x-cloak @click.outside="linkOpen = false" @keydown.escape.stop="linkOpen = false"
                     class="absolute end-0 top-full z-20 mt-1 w-72 rounded-xl border border-line bg-surface p-3 shadow-(--bp-elev-lg)">
                    <label class="mb-1 block text-xs font-medium text-ink" for="{{ $id }}-link">نشانی پیوند</label>
                    <input id="{{ $id }}-link" type="url" x-ref="linkInput" x-model="linkHref" @keydown.enter.prevent="applyLink()" placeholder="https://…" dir="ltr"
                           class="{{ \App\Modules\Panel\Support\FormControl::classes('sm', true) }}">
                    <div class="mt-2 flex justify-between gap-2">
                        <x-panel.button variant="ghost" size="sm" icon="unlink" @click="unlink()">حذف پیوند</x-panel.button>
                        <x-panel.button size="sm" @click="applyLink()">اعمال</x-panel.button>
                    </div>
                </div>
            </div>

            <button type="button" @mousedown.prevent @click="insertImage()" :disabled="!ready" title="تصویر از کتابخانه" aria-label="تصویر از کتابخانه"
                    class="grid size-8 place-items-center rounded-md text-ink-3 hover:bg-surface-3 hover:text-ink disabled:opacity-40" data-rt-tool="image">
                <x-icon name="image" class="size-4" />
            </button>
            <button type="button" @mousedown.prevent @click="cmd('hr')" :disabled="!ready" title="خط جداکننده" aria-label="خط جداکننده"
                    class="grid size-8 place-items-center rounded-md text-ink-3 hover:bg-surface-3 hover:text-ink disabled:opacity-40" data-rt-tool="hr">
                <x-icon name="minus" class="size-4" />
            </button>

            <span class="mx-1 h-5 w-px bg-line-3" aria-hidden="true"></span>
            <button type="button" @mousedown.prevent @click="cmd('undo')" :disabled="!ready" title="واگرد" aria-label="واگرد" class="grid size-8 place-items-center rounded-md text-ink-3 hover:bg-surface-3 hover:text-ink disabled:opacity-40"><x-icon name="undo" class="size-4" /></button>
            <button type="button" @mousedown.prevent @click="cmd('redo')" :disabled="!ready" title="ازنو" aria-label="ازنو" class="grid size-8 place-items-center rounded-md text-ink-3 hover:bg-surface-3 hover:text-ink disabled:opacity-40"><x-icon name="redo" class="size-4" /></button>
        </div>

        {{-- Tiptap inja mount mishe; ta load shodan-esh skeleton. --}}
        <div x-ref="editor" id="{{ $id }}" aria-labelledby="{{ $id }}-label"></div>
        <div x-show="!ready" class="min-h-64 animate-pulse bg-surface-2" aria-hidden="true"></div>
    </div>

    @if ($hint)<p class="mt-1.5 text-xs text-muted-2">{{ $hint }}</p>@endif
</div>
