@props([
    // Maghsad-e form (masalan route-e verify-e chalesh-e board) va method-e HTTP.
    'action',
    'method' => 'POST',
    // Field-haye makhfi-e ezafi: masalan ['return' => $url].
    'hidden' => [],
    'name' => 'captcha_pass',
    // Ba'd az hal-e pazel khodesh submit kone (dokme faghat fallback-e) ya montazer-e click bemune.
    'auto' => true,
    'submit' => 'ادامه',
    'label' => 'تأیید انسان بودن',
])

{{--
    Gate-e amade baraye safhe-ye chalesh-e Board (docs/frontend/admin-panel.md «Captcha gate contract»):
    form + pazel + input-e makhfi-ye pass + dokme. Server-e maghsad `captcha_pass` ro ba rule-e
    `Captcha\Rules\CaptchaPassed` masraf mikone. Slot baraye matn-e tozihi bala-ye pazel.
--}}
@php $spoofed = strtoupper($method) !== 'POST' && strtoupper($method) !== 'GET'; @endphp

<form method="{{ strtoupper($method) === 'GET' ? 'GET' : 'POST' }}" action="{{ $action }}"
      x-data="{ ready: false, auto: @js((bool) $auto) }"
      @captcha-passed="ready = true; if (auto) $nextTick(() => $el.requestSubmit())"
      @captcha-reset.window="ready = false"
      {{ $attributes->merge(['class' => 'space-y-4']) }} data-captcha-gate>
    @if (strtoupper($method) !== 'GET')
        @csrf
    @endif
    @if ($spoofed)
        @method($method)
    @endif
    @foreach ($hidden as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach

    {{ $slot }}

    <x-captcha.puzzle :name="$name" :label="$label" />

    <button type="submit" :disabled="!ready"
            class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-brand px-5 text-sm font-medium text-on-brand shadow-(--bp-elev-brand) hover:bg-brand-2 disabled:bg-brand/60 disabled:shadow-none"
            data-captcha-gate-submit>
        {{ $submit }}
    </button>
</form>
