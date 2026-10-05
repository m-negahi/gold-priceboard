@props([
    'name',
    'options' => [],
    'label' => null,
    'inline' => false,
])
@use('App\Modules\Panel\Support\FormControl')

{{--
    Goroh-e radio. `options` value => label, ya value => ['label' => ..., 'description' => ...].
    Root yek fieldset-e ta legend label-e goroh bashe; x-model rooye har radio mishine.
--}}
<fieldset {{ $attributes->whereDoesntStartWith(['x-', ':', '@'])->except('disabled')->merge(['class' => 'min-w-0 border-0 p-0']) }}
          @if ($attributes->get('disabled')) disabled @endif>
    @if ($label)
        <legend class="mb-2 text-sm font-medium text-ink">{{ $label }}</legend>
    @endif
    <div @class(['flex gap-x-6 gap-y-2', 'flex-wrap' => $inline, 'flex-col' => ! $inline])>
        @foreach ($options as $value => $option)
            @php
                $optionLabel = is_array($option) ? ($option['label'] ?? $value) : $option;
                $optionDescription = is_array($option) ? ($option['description'] ?? null) : null;
                $id = FormControl::id($name.'-'.$value);
            @endphp
            <label for="{{ $id }}" class="flex cursor-pointer items-start gap-2.5 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60">
                <input type="radio" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}"
                       {{ $attributes->whereStartsWith(['x-', ':', '@']) }}
                       class="mt-0.5 size-5 shrink-0 cursor-pointer appearance-none rounded-full border border-line-3 bg-surface transition-[border-color,border-width] duration-(--bp-dur-fast)
                              hover:border-muted-3 checked:border-5 checked:border-brand disabled:cursor-not-allowed
                              group-data-[invalid=true]/field:border-danger">
                <span class="min-w-0 text-sm">
                    <span class="block text-ink">{{ $optionLabel }}</span>
                    @if ($optionDescription)<span class="block text-muted">{{ $optionDescription }}</span>@endif
                </span>
            </label>
        @endforeach
    </div>
</fieldset>
