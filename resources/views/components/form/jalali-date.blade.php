@props([
    'name' => null,
    'id' => null,
    'value' => null,
    'label' => 'تاریخ',
    'placeholder' => '۱۴۰۵/۰۷/۰۴',
    'hint' => null,
    'min' => null,
    'max' => null,
    'disabled' => false,
])

{{--
    Field-e tarikh-e shamsi ba picker-e charkhi-ye iOS-style (docs/frontend/admin-panel.md §7).

    Gharardad: `value`/`x-model`/hidden input hamishe ISO-ye miladi-e (`2026-09-26`) ya '' — server
    hich vaght jalali nemibine. Karbar tu box `۱۴۰۵/۰۷/۰۴` mibine, mitoone type kone (ragham-e farsi ya
    latin, ba ya bi joda-konande; format khodesh mishine) ya az se charkh-e rooz/mah/sal entekhab kone.
    Raftar tu resources/js/alpine/jalali-date.js-e; server ba `Common\Rules\JalaliDate` validate mikone.

    `min`/`max` ISO-and. Parent ba x-model be value miresed (x-modelable) ya ba `name` hidden input migire.
--}}
@use('App\Modules\Panel\Support\FormControl')
@php
    // Hamun gharardad-e id-e baghi-ye x-form.* (`published_at` → `f-published-at`); bi-esm yek id-e tasadofi.
    $id ??= FormControl::id($name) ?? 'jd-'.uniqid();
    $drumLabels = ['day' => 'روز', 'month' => 'ماه', 'year' => 'سال'];
@endphp

<div x-data="jalaliDate({ value: @js($value), min: @js($min), max: @js($max), disabled: @js((bool) $disabled) })"
     x-modelable="value" x-id="['jalali-date']"
     :data-invalid="error !== ''" data-jalali-date
     @click.outside="close()"
     {{ $attributes->merge(['class' => 'group/jd relative min-w-0']) }}>
    <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink">{{ $label }}</label>

    <div class="relative">
        <input type="text" id="{{ $id }}" x-ref="input" inputmode="numeric" autocomplete="off" dir="ltr"
               :value="text" placeholder="{{ $placeholder }}" :disabled="disabled"
               aria-haspopup="dialog" :aria-expanded="open" :aria-invalid="error !== '' ? 'true' : null"
               :aria-describedby="error !== '' ? $id('jalali-date') + '-error' : null"
               @input="onInput($event)" @keydown="onInputKeydown($event)" @focus="onFocus()" @blur="onBlur()" @change.stop
               class="num block h-11 w-full min-w-0 rounded-xl border border-line-3 bg-surface pl-20 pr-3.5 text-right text-sm text-ink placeholder:text-muted-3
                      transition-[border-color,box-shadow,background-color] duration-(--bp-dur-fast)
                      hover:border-muted-3 focus:border-brand focus:shadow-(--bp-elev-brand) focus:outline-none
                      disabled:cursor-not-allowed disabled:bg-surface-2 disabled:text-muted-2 disabled:hover:border-line-3
                      group-data-[invalid=true]/jd:border-danger group-data-[invalid=true]/jd:bg-danger-soft group-data-[invalid=true]/jd:focus:shadow-none">

        {{-- Dokme ha samt-e chap-e box (RTL: enteha-ye khat) --}}
        <div class="absolute inset-y-0 left-1.5 flex items-center gap-0.5">
            <button type="button" x-show="value !== '' && !disabled" @click="clear()" aria-label="پاک کردن تاریخ"
                    class="flex size-8 items-center justify-center rounded-lg text-muted-2 hover:bg-surface-3 hover:text-ink">
                <x-icon name="close" class="size-4" />
            </button>
            <button type="button" @click="toggle()" :disabled="disabled" aria-label="انتخاب تاریخ" :aria-expanded="open" aria-haspopup="dialog"
                    class="flex size-8 items-center justify-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink disabled:cursor-not-allowed disabled:text-muted-3 disabled:hover:bg-transparent">
                <x-icon name="calendar" class="size-4" />
            </button>
        </div>
    </div>

    @if ($name)
        <input type="hidden" name="{{ $name }}" :value="value">
    @endif

    @if ($hint)
        <p x-show="error === ''" class="mt-1.5 text-xs text-muted-2">{{ $hint }}</p>
    @endif
    <p x-show="error !== ''" style="display: none" :id="$id('jalali-date') + '-error'" x-text="error" role="alert" class="mt-1.5 text-xs text-danger"></p>
    <p class="sr-only" aria-live="polite" x-text="announcement()"></p>

    {{-- Picker: absolute zir-e (ya bala-ye) box, samt-e rast ham-tarz --}}
    <div x-show="open" style="display: none" role="dialog" aria-label="انتخاب تاریخ" aria-modal="false"
         x-transition:enter="transition duration-(--bp-dur-base) ease-(--bp-ease-out)"
         x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100"
         x-transition:leave="transition duration-(--bp-dur-fast)" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         :class="openUp ? 'bottom-full mb-2 origin-bottom-right' : 'top-full mt-2 origin-top-right'"
         class="absolute right-0 z-50 w-72 rounded-card border border-line bg-surface p-3 shadow-(--bp-elev-lg)"
         @keydown.escape.stop="cancel()">
        <div class="flex gap-1" dir="rtl">
            @foreach ($drumLabels as $drum => $drumLabel)
                <div class="min-w-0 flex-1">
                    <p class="mb-1 text-center text-xs text-muted" aria-hidden="true">{{ $drumLabel }}</p>
                    <div class="relative">
                        {{-- Navar-e entekhab, zir-e radif ha --}}
                        <div class="pointer-events-none absolute inset-x-0 top-20 h-10 rounded-lg border-y border-line bg-brand-soft" aria-hidden="true"></div>

                        <div data-drum="{{ $drum }}" role="listbox" tabindex="0" aria-label="{{ $drumLabel }}"
                             :aria-activedescendant="activeId('{{ $drum }}')"
                             :class="dragging === '{{ $drum }}' ? 'snap-none cursor-grabbing' : 'snap-y snap-mandatory cursor-grab'"
                             @scroll="onScroll('{{ $drum }}')" @wheel.passive="onUserScroll('{{ $drum }}')" @touchstart.passive="onUserScroll('{{ $drum }}')"
                             @pointerdown="onPointerDown('{{ $drum }}', $event)" @pointermove="onPointerMove('{{ $drum }}', $event)"
                             @pointerup="onPointerUp('{{ $drum }}', $event)" @pointercancel="onPointerUp('{{ $drum }}', $event)"
                             @keydown="onDrumKeydown('{{ $drum }}', $event)" @focus="focusedDrum = '{{ $drum }}'"
                             class="relative h-50 overflow-y-auto overscroll-contain select-none rounded-lg [scrollbar-width:none] [&::-webkit-scrollbar]:hidden
                                    [mask-image:linear-gradient(to_bottom,transparent,black_28%,black_72%,transparent)]
                                    focus-visible:shadow-(--bp-focus-ring) focus-visible:outline-none">
                            <div class="py-20">
                                <template x-for="(n, i) in options('{{ $drum }}')" :key="n">
                                    <div data-drum-option :data-index="i" role="option" :id="optionId('{{ $drum }}', n)"
                                         :aria-selected="i === index('{{ $drum }}') ? 'true' : 'false'"
                                         :aria-disabled="isOut('{{ $drum }}', n) ? 'true' : null"
                                         @class(['flex h-10 snap-center items-center justify-center text-sm transition-[color,transform] duration-(--bp-dur-fast)', 'num' => $drum !== 'month'])
                                         :class="{
                                             'font-semibold text-ink text-base': i === index('{{ $drum }}'),
                                             'text-muted': Math.abs(i - index('{{ $drum }}')) === 1,
                                             'text-muted-3': Math.abs(i - index('{{ $drum }}')) > 1,
                                             'opacity-40': isOut('{{ $drum }}', n),
                                         }"
                                         x-text="label('{{ $drum }}', n)"></div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3 flex items-center gap-2">
            <button type="button" @click="pickToday()" class="h-9 rounded-lg px-3 text-sm text-ink-3 hover:bg-surface-3 hover:text-ink">امروز</button>
            <button type="button" @click="clear()" class="h-9 rounded-lg px-3 text-sm text-ink-3 hover:bg-surface-3 hover:text-ink">پاک کردن</button>
            <button type="button" @click="confirm()" class="mr-auto h-9 rounded-lg bg-brand px-4 text-sm font-medium text-on-brand shadow-(--bp-elev-brand) hover:bg-brand-2">تأیید</button>
        </div>
    </div>
</div>
