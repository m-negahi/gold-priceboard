@props([
    'name' => null,
    'id' => null,
    'size' => 'md',
    'value' => null,
])
@use('App\Modules\Panel\Support\FormControl')

{{--
    Field-e mablagh (rial/toman) ba jodakonande-ye hezargan-e zende: karbar `1250000` type
    mikone, `1,250,000` mibine. Meghdar-e submit-shode comma dare — server ba
    `Money::rialFromInput()` mikhoone. Hamishe `ltr` + `num` (adad). Raftar tu
    resources/js/alpine/form-money.js.
--}}
@php $id ??= FormControl::id($name); @endphp

<input type="text" inputmode="numeric" autocomplete="off" dir="ltr"
       @if ($name) name="{{ $name }}" @endif @if ($id) id="{{ $id }}" @endif
       value="{{ $value }}"
       x-data="formMoney" @input="format($event)"
       :aria-invalid="{{ FormControl::ARIA_INVALID }}"
       :aria-describedby="{{ FormControl::ARIA_DESCRIBED_BY }}"
       {{ $attributes->merge(['class' => FormControl::classes($size, true).' num']) }}>
