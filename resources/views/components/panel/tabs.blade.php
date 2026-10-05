@props([
    'tabs' => [],
    'default' => null,
    'sync' => false,
    'label' => 'زبانه‌ها',
])

{{--
    Tab ha (WAI-ARIA). `tabs` yek array-e key => label-e; panel ha ba `<x-panel.tab-panel key="...">`
    dakhel-e slot miyan va `active`-e hamin scope ro mikhoonand. Arrow ha RTL-aware-and
    (resources/js/alpine/panel-tabs.js) va `sync` key ro tu hash-e URL negah midare.
--}}
<div x-data="panelTabs({ tabs: @js(array_map('strval', array_keys($tabs))), initial: @js($default), sync: @js((bool) $sync) })"
     {{ $attributes }}>
    <div role="tablist" aria-label="{{ $label }}" @keydown="onKeydown($event)"
         class="flex gap-1 overflow-x-auto border-b border-line">
        @foreach ($tabs as $key => $tabLabel)
            <button type="button" role="tab" data-tab="{{ $key }}"
                    :id="tabId(@js((string) $key))"
                    :aria-selected="active === @js((string) $key)"
                    :aria-controls="panelId(@js((string) $key))"
                    :tabindex="active === @js((string) $key) ? 0 : -1"
                    @click="select(@js((string) $key))"
                    class="-mb-px inline-flex h-11 shrink-0 items-center gap-2 border-b-2 px-3.5 text-sm font-medium transition-colors duration-(--bp-dur-fast)"
                    :class="active === @js((string) $key)
                        ? 'border-brand text-brand-2'
                        : 'border-transparent text-muted hover:border-line-3 hover:text-ink'">
                {{ $tabLabel }}
            </button>
        @endforeach
    </div>

    {{ $slot }}
</div>
