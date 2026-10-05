@props([
    'name' => null,
    'label' => 'تصویر',
    'hint' => null,
    'value' => null,
    'media' => null,
    'required' => false,
])

{{--
    Field-e entekhab-e tasvir az ketabkhane-ye رسانه (docs/frontend/admin-panel.md §21). Meghdar
    (x-modelable `value` / hidden input) id-e rasane-ast; `media` row-e namayeshi (thumb_url, alt).
    Modal-e ketabkhane ro safhe yek bar ba `<x-panel.media-library />` render mikone; in field
    faghat event-e `media-library-open` mizane. Raftar tu resources/js/alpine/media-picker.js.
--}}
@php $id = \App\Modules\Panel\Support\FormControl::id($name) ?? 'mp-'.uniqid(); @endphp

<div x-data="mediaPicker({ value: @js($value), media: @js($media) })" x-modelable="value"
     {{ $attributes->merge(['class' => 'min-w-0']) }} data-media-picker>
    @if ($label)
        <p class="mb-1.5 flex items-center gap-1 text-sm font-medium text-ink" id="{{ $id }}-label">
            {{ $label }}
            @if ($required)<span class="text-danger" aria-hidden="true">*</span><span class="sr-only">(الزامی)</span>@endif
        </p>
    @endif

    @if ($name)<input type="hidden" name="{{ $name }}" :value="value ?? ''">@endif

    <div class="flex items-start gap-4 rounded-xl border border-line-3 bg-surface p-3" role="group" aria-labelledby="{{ $id }}-label">
        <template x-if="media">
            <img :src="media.thumb_url" :alt="media.alt" class="size-24 shrink-0 rounded-lg bg-surface-2 object-cover ring-1 ring-line" data-media-picker-thumb>
        </template>
        <template x-if="!media">
            <div class="grid size-24 shrink-0 place-items-center rounded-lg bg-surface-2 text-muted-3" aria-hidden="true">
                <x-icon name="image" class="size-8" />
            </div>
        </template>

        <div class="min-w-0 flex-1 space-y-2">
            <template x-if="media">
                <div class="min-w-0 text-sm">
                    <p class="truncate font-medium text-ink" x-text="media.alt" data-media-picker-alt></p>
                    <p class="truncate text-xs text-muted" x-text="media.name"></p>
                </div>
            </template>
            <p x-show="!media" class="text-sm text-muted">تصویری انتخاب نشده است.</p>

            <div class="flex flex-wrap gap-2">
                <x-panel.button variant="secondary" size="sm" icon="image" @click="choose()" x-text="media ? 'تغییر تصویر' : 'انتخاب از کتابخانه'"></x-panel.button>
                <x-panel.button variant="ghost" size="sm" icon="x" x-show="media" x-cloak @click="clear()">برداشتن</x-panel.button>
            </div>
        </div>
    </div>

    @if ($hint)<p class="mt-1.5 text-xs text-muted-2">{{ $hint }}</p>@endif
</div>
