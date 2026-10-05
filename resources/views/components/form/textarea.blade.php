@props([
    'name' => null,
    'id' => null,
    'rows' => 4,
    'ltr' => false,
])
@use('App\Modules\Panel\Support\FormControl')

{{-- Textarea-ye chand-khati; hamun zaban-e input, bedoone height-e sabet. --}}
@php $id ??= FormControl::id($name); @endphp

<textarea rows="{{ (int) $rows }}" @if ($name) name="{{ $name }}" @endif @if ($id) id="{{ $id }}" @endif
          @if ($ltr) dir="ltr" @endif
          :aria-invalid="{{ FormControl::ARIA_INVALID }}"
          :aria-describedby="{{ FormControl::ARIA_DESCRIBED_BY }}"
          {{ $attributes->merge(['class' => FormControl::multilineClasses((bool) $ltr)]) }}>{{ $slot }}</textarea>
