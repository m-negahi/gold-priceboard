@props([
    'label' => null,
    'name' => null,
    'for' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
])
@use('App\Modules\Panel\Support\FormControl')

{{--
    Wrapper-e yek field: label, control (slot), hint, khata.

    id-e control az `name` sakhte mishe (FormControl::id) va control-haye x-form.* hamin ghanoon
    ro daran, pas label va input bedoone dast-kari be ham vasl-and. Khata ya az server miad
    (`$errors`) ya az object-e `errors`-e Alpine-e parent; `data-invalid` rooye wrapper mishine
    ta control ha ba `group-data-[invalid=true]/field:` ghermez beshan.
--}}
@php
    $id = $for ?? FormControl::id($name) ?? 'f-'.uniqid();
    $serverError = $error ?? ($name !== null && $errors->has($name) ? $errors->first($name) : null);
@endphp

<div x-data="formField({ name: @js($name), error: @js($serverError), hint: @js($hint), id: @js($id) })"
     :data-invalid="invalid"
     {{ $attributes->merge(['class' => 'group/field min-w-0']) }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 flex items-center gap-1 text-sm font-medium text-ink">
            {{ $label }}
            @if ($required)
                <span class="text-danger" aria-hidden="true">*</span>
                <span class="sr-only">(الزامی)</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p id="{{ $id }}-hint" x-show="!invalid" class="mt-1.5 text-xs text-muted-2">{{ $hint }}</p>
    @endif
    <p id="{{ $id }}-error" x-show="invalid" x-cloak x-text="message" role="alert" class="mt-1.5 text-xs text-danger"></p>
</div>
