@props([
    'length' => 6,
    'label' => 'کد ورود',
])

{{--
    Input-e code-e yekbar-masraf (PRD F5, .claude/rules/frontend.md "OTP screen").

    Box ha be tartib-e DOM CHAP-BE-RAST tu yek jazire-ye LTR chide shodan, pas Tab, arrow ha va caret
    hame ba tarz-e khoondan-e code hamkhan hastand. Raftar tu resources/js/otp-input.js-e:
    focus select mikone, type kardan jaygozin mikone va jelo mire, Backspace yek ghadam aghab mire,
    Delete sar-e ja pak mikone, arrow ha harekat midan, paste ya autofill har ja bashe az box-e aval
    por mikone, ragham-e farsi ghabool-e, va code-e kamel `complete` mide ta parent bedoone dokme submit kone.

    Root yek <fieldset>-e ta `:disabled` be sorat-e native be hame-ye box ha berese; `x-modelable`
    mizare parent kolle code ro ba x-model bind kone. Halat-e khata-ye visual ba data-invalid tasmim gerefte mishe.
--}}
<fieldset x-data="otpInput({ length: {{ (int) $length }} })" x-modelable="code"
          {{ $attributes->merge(['class' => 'group m-0 min-w-0 border-0 p-0']) }}
          dir="ltr" @paste.prevent="onPaste($event)">
    <legend class="sr-only">{{ $label }}</legend>

    <div class="flex justify-between gap-2 sm:gap-3">
        <template x-for="i in length" :key="i">
            <input data-otp-box type="text" inputmode="numeric" :autocomplete="i === 1 ? 'one-time-code' : 'off'"
                   maxlength="{{ (int) $length }}" :aria-label="'رقم ' + i" :value="digitAt(i - 1)"
                   @mousedown="onPointerDown(i - 1, $event)" @focus="onFocus(i - 1, $event)" @input="onInput(i - 1, $event)" @keydown="onKeydown(i - 1, $event)"
                   class="num h-14 w-full min-w-0 rounded-xl border border-line-3 bg-surface text-center text-xl font-semibold text-ink caret-brand transition-[border-color,box-shadow] duration-(--bp-dur-fast)
                          hover:border-muted-3 focus:border-brand focus:shadow-(--bp-elev-brand)
                          disabled:bg-surface-2 disabled:text-muted-2
                          group-data-[invalid=true]:border-danger group-data-[invalid=true]:bg-danger-soft
                          sm:h-16 sm:text-2xl">
        </template>
    </div>
</fieldset>
