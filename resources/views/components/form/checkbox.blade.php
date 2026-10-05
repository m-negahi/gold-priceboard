@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'description' => null,
    'value' => '1',
])
@use('App\Modules\Panel\Support\FormControl')

{{-- Checkbox-e native ba `appearance-none`; tick yek svg-e sibling-e ke ba `peer-checked` zaher mishe.
     Esm-e accessible faghat label-e (aria-labelledby); tosif joda ba aria-describedby miad. --}}
@php $id ??= ($name !== null ? FormControl::id($name.'-'.$value) : null) ?? 'f-'.uniqid(); @endphp

<label for="{{ $id }}" {{ $attributes->whereDoesntStartWith(['x-', ':', '@'])->except(['disabled', 'checked'])->merge(['class' => 'flex cursor-pointer items-start gap-2.5 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60']) }}>
    <span class="relative mt-0.5 inline-grid size-5 shrink-0 place-items-center">
        <input type="checkbox" id="{{ $id }}" @if ($name) name="{{ $name }}" @endif value="{{ $value }}"
           @if ($label) aria-labelledby="{{ $id }}-label" @endif @if ($description) aria-describedby="{{ $id }}-description" @endif
               {{ $attributes->whereStartsWith(['x-', ':', '@']) }}
               @if ($attributes->get('disabled')) disabled @endif
               @if ($attributes->get('checked')) checked @endif
               class="peer col-start-1 row-start-1 size-5 cursor-pointer appearance-none rounded-md border border-line-3 bg-surface transition-colors duration-(--bp-dur-fast)
                      hover:border-muted-3 checked:border-brand checked:bg-brand disabled:cursor-not-allowed
                      group-data-[invalid=true]/field:border-danger">
        <x-icon name="check" class="pointer-events-none col-start-1 row-start-1 size-3.5 text-on-brand opacity-0 transition-opacity peer-checked:opacity-100" />
    </span>
    @if ($label || $description)
        <span class="min-w-0 text-sm">
            @if ($label)<span id="{{ $id }}-label" class="block text-ink">{{ $label }}</span>@endif
            @if ($description)<span id="{{ $id }}-description" class="block text-muted">{{ $description }}</span>@endif
        </span>
    @endif
</label>
