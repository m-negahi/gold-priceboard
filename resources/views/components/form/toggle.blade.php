@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'description' => null,
    'value' => '1',
])
@use('App\Modules\Panel\Support\FormControl')

{{--
    Switch: yek checkbox-e native ba `role="switch"` ke visual-esh track/knob-e. Native mundan yani
    x-model, form-e mamooli, keyboard (Space) va screen reader hame bedoone JS kar mikonand.
    Knob yek pseudo-element-e track-e (peer-checked faghat be khod-e sibling mirese, na
    farzandanesh); tu RTL az rast shoroo mishe va ba check be chap mire.
--}}
@php $id ??= FormControl::id($name) ?? 'f-'.uniqid(); @endphp

<label for="{{ $id }}" {{ $attributes->whereDoesntStartWith(['x-', ':', '@'])->except(['disabled', 'checked'])->merge(['class' => 'group/toggle flex cursor-pointer items-start gap-3 has-[:disabled]:cursor-not-allowed']) }}>
    <input type="checkbox" role="switch" id="{{ $id }}" @if ($name) name="{{ $name }}" @endif value="{{ $value }}"
           @if ($label) aria-labelledby="{{ $id }}-label" @endif @if ($description) aria-describedby="{{ $id }}-description" @endif
           {{ $attributes->whereStartsWith(['x-', ':', '@']) }}
           @if ($attributes->get('disabled')) disabled @endif
           @if ($attributes->get('checked')) checked @endif
           class="peer sr-only">
    <span aria-hidden="true"
          class="relative mt-0.5 inline-block h-6 w-11 shrink-0 rounded-full bg-line-3 transition-colors duration-(--bp-dur-fast)
                 before:absolute before:top-0.5 before:right-0.5 before:size-5 before:rounded-full before:bg-surface before:shadow-(--bp-elev-sm)
                 before:transition-transform before:duration-(--bp-dur-base) before:ease-(--bp-ease-out) before:content-['']
                 peer-checked:bg-brand peer-checked:before:-translate-x-5
                 peer-focus-visible:shadow-(--bp-focus-ring) peer-disabled:opacity-50
                 group-data-[invalid=true]/field:bg-danger/40"></span>
    @if ($label || $description)
        <span class="min-w-0 text-sm">
            @if ($label)<span id="{{ $id }}-label" class="block font-medium text-ink">{{ $label }}</span>@endif
            @if ($description)<span id="{{ $id }}-description" class="block text-muted">{{ $description }}</span>@endif
        </span>
    @endif
</label>
