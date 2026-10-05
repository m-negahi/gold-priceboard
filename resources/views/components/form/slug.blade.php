@props([
    'name' => 'slug',
    'label' => 'شناسه / نشانی عمومی',
    'prefix' => '/',
    'value' => '',
    'source' => null,
    'existing' => false,
    'error' => null,
])

{{--
    Field-e slug (docs/frontend/admin-panel.md §4). Raftar tu resources/js/alpine/slug-field.js-e:

    - Chip-e prefix nashani-ye vaghei ro neshun mide; faghat segment-e akhar ghabel-e virayesh-e.
    - Ghofl (pishfarz): record-e jadid slug ro az field-e `source` (yek CSS selector, masalan #title)
      ba 400ms debounce misaze; record-e mojood (`existing`) ghofl yani daghighan hamin mimune.
      Medad ghofl ro baz mikone → virayesh-e dasti; ghofl-e dobare be raftar-e ghabli bar migarde.
    - Hich vaght khata nemide: `resources/js/lib/slug.js` ayene-ye SlugHelper-e va vaghti chizi ke type
      shode ba shekl-e normal fargh dare, «هنگام ذخیره به این شکل اصلاح می‌شود» ro neshun mide.
    - `x-modelable="value"` → parent ba x-model string-e kham ro migire; hidden input hamun ro post mikone.
    - Khod-e slug LTR-e (chip chap, segment rast); label va note RTL mimunand.
--}}
@use('App\Modules\Panel\Support\FormControl')
@php
    // Hamun gharardad-e id-e baghi-ye x-form.* (`slug` → `f-slug`) ta x-form.field-e dor-e-sh `for` ro dorost bede.
    $id = FormControl::id($name) ?? 'f-slug';
@endphp

<div x-data="slugField({ value: @js((string) $value), source: @js($source), existing: @js((bool) $existing) })" x-modelable="value"
     {{ $attributes->merge(['class' => 'group min-w-0']) }} data-slug-field data-invalid="{{ $error ? 'true' : 'false' }}">
    <label for="{{ $id }}" class="mb-2 block text-sm font-medium">{{ $label }}</label>

    <div dir="ltr" class="flex h-12 items-stretch overflow-hidden rounded-xl border bg-surface transition-[border-color,box-shadow] duration-(--bp-dur-fast) focus-within:border-brand focus-within:shadow-(--bp-elev-brand)
                {{ $error ? 'border-danger bg-danger-soft' : 'border-line-3' }}">
        <span class="ltr num flex shrink-0 items-center border-r border-line bg-surface-2 px-3 text-sm text-muted select-none" aria-hidden="true">{{ $prefix }}</span>

        <input id="{{ $id }}" type="text" x-ref="input" :value="value" @input="onInput($event)" :readonly="locked"
               autocomplete="off" autocapitalize="off" spellcheck="false" dir="ltr"
               aria-describedby="{{ $id }}-note" @if ($error) aria-invalid="true" @endif
               class="ltr min-w-0 flex-1 bg-transparent px-3 text-ink outline-none read-only:text-muted">

        <button type="button" @click="toggle()" :aria-pressed="!locked" :aria-label="locked ? 'ویرایش نشانی' : 'قفل کردن نشانی'"
                :title="locked ? 'ویرایش نشانی' : 'قفل کردن نشانی'"
                class="flex w-11 shrink-0 items-center justify-center border-l border-line text-muted transition-colors duration-(--bp-dur-fast) hover:bg-surface-3 hover:text-ink focus-visible:outline-none focus-visible:shadow-(--bp-focus-ring)">
            <x-icon name="lock" class="size-4" x-show="locked" />
            <x-icon name="pencil" class="size-4" x-show="!locked" x-cloak />
        </button>
    </div>

    <input type="hidden" name="{{ $name }}" :value="value">

    <p id="{{ $id }}-note" class="mt-2 text-sm">
        @if ($error)
            <span class="text-danger" role="alert">{{ $error }}</span>
        @else
            <span x-show="differs" x-cloak class="text-warn" data-slug-note>
                هنگام ذخیره به این شکل اصلاح می‌شود: <span class="ltr font-medium" x-text="preview"></span>
            </span>
            <span x-show="!differs" class="text-muted-2">
                @if ($existing)
                    تغییر نشانی، یک ریدایرکت ۳۰۱ از نشانی قبلی می‌سازد.
                @else
                    حروف فارسی و انگلیسی، رقم و خط تیره؛ خودکار از عنوان ساخته می‌شود.
                @endif
            </span>
        @endif
    </p>
</div>
