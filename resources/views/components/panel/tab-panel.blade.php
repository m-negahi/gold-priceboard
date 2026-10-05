@props(['key'])

{{-- Badane-ye yek tab; bayad dakhel-e `x-panel.tabs` bashe ta `active` va id ha ro az un begire. --}}
<div role="tabpanel" tabindex="0" x-show="active === @js((string) $key)" x-cloak
     :id="panelId(@js((string) $key))" :aria-labelledby="tabId(@js((string) $key))"
     {{ $attributes->merge(['class' => 'pt-5']) }}>
    {{ $slot }}
</div>
