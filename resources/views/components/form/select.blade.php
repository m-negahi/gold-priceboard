@props([
    'name' => null,
    'id' => null,
    'options' => [],
    'placeholder' => null,
    'size' => 'md',
])
@use('App\Modules\Panel\Support\FormControl')

{{--
    Select-e native ba chevron-e khodemun (`appearance-none`). `options` yek array-e value => label-e;
    ya `<option>` ha ro tu slot bede. `placeholder` yek option-e khali-ye disabled-e.
--}}
@php $id ??= FormControl::id($name); @endphp

<div class="relative">
    <select @if ($name) name="{{ $name }}" @endif @if ($id) id="{{ $id }}" @endif
            :aria-invalid="{{ FormControl::ARIA_INVALID }}"
            :aria-describedby="{{ FormControl::ARIA_DESCRIBED_BY }}"
            {{ $attributes->merge(['class' => FormControl::classes($size).' appearance-none pe-10']) }}>
        @if ($placeholder !== null)
            <option value="" disabled @if (! $attributes->has('x-model')) selected @endif>{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
        {{ $slot }}
    </select>
    <x-icon name="chevron" class="pointer-events-none absolute end-3.5 top-1/2 size-4 -translate-y-1/2 -rotate-90 text-muted-2" />
</div>
