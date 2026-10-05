@props([
    'name' => null,
    'id' => null,
    'size' => 'md',
    'ltr' => false,
])
@use('App\Modules\Panel\Support\FormControl')

{{--
    Field-e adadi: `inputmode="decimal"` (na type=number — un ragham-e farsi ro rad mikone),
    argham-e farsi/arabi hamun lahze ASCII mishan (resources/js/alpine/form-number.js), va
    `num` ta sotoon-e adad ha nalarze. `ltr` adad ro chap-chin mikone.
--}}
@php $id ??= FormControl::id($name); @endphp

<input type="text" inputmode="decimal" autocomplete="off"
       @if ($name) name="{{ $name }}" @endif @if ($id) id="{{ $id }}" @endif
       @if ($ltr) dir="ltr" @endif
       x-data="formNumber" @input="normalize($event)"
       :aria-invalid="{{ FormControl::ARIA_INVALID }}"
       :aria-describedby="{{ FormControl::ARIA_DESCRIBED_BY }}"
       {{ $attributes->merge(['class' => FormControl::classes($size, (bool) $ltr).' num']) }}>
