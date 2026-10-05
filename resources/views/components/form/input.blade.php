@props([
    'name' => null,
    'id' => null,
    'type' => 'text',
    'size' => 'md',
    'ltr' => false,
])
@use('App\Modules\Panel\Support\FormControl')

{{--
    Input-e matni. `ltr` baraye code, URL va email (matn chap-be-rast dakhel-e form-e RTL).
    aria-invalid / aria-describedby az x-form.field-e dor-e-sh miad; birun-e field bi-sar-o-seda null-and.
--}}
@php $id ??= FormControl::id($name); @endphp

<input type="{{ $type }}" @if ($name) name="{{ $name }}" @endif @if ($id) id="{{ $id }}" @endif
       @if ($ltr) dir="ltr" @endif
       :aria-invalid="{{ FormControl::ARIA_INVALID }}"
       :aria-describedby="{{ FormControl::ARIA_DESCRIBED_BY }}"
       {{ $attributes->merge(['class' => FormControl::classes($size, (bool) $ltr)]) }}>
