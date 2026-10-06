@props(['groups' => [], 'current' => null])

{{--
    Do halat-e vaghe'i (@docs/frontend/admin-panel.md §1). Dar halat-e baste, tooltip tanha chizi-e
    ke label-e item ro hamel mikone, pas be :focus-visible ham javab mide na faghat hover, va har
    item dar har halati aria-label-esh ro negah midare.
--}}
{{-- Dakhel e shell e Donezo: sidebar shafaf ru surface-2 ye shell mishine
     (doc §4)؛ faghat yek hairline e logical (`border-e`) jodash mikone.

     P4-19 — Mobile: hamun aside yek DRAWER e off-canvas mishe (fixed،
     translate be samt e start) ke ba event e `toggle-sidebar` az hamburger
     e topbar baz mishe. Desktop (lg+) hamun rail e sticky ye ghabli-e. --}}
<aside x-data="panelSidebar"
       @toggle-sidebar.window="open = ! open"
       @keydown.escape.window="open = false"
       {{-- `drawer-start-closed` STATIC ast ta ghabl az init e Alpine ham
            drawer tu mobile baste bashe (hich flash)؛ `translate-x-0` e bound
            (open) va `lg:translate-x-0` (utilities، ba'd az components) bar an
            ghelebe mikonan. --}}
       class="drawer-start-closed fixed inset-y-0 start-0 z-50 flex shrink-0 flex-col border-e border-line bg-surface-2 transition-transform duration-200 lg:sticky lg:top-4 lg:z-auto lg:h-[calc(100dvh-2rem)] lg:translate-x-0 lg:bg-transparent lg:transition-[width]"
       :class="[
           open ? 'translate-x-0' : '',
           wide ? 'w-60' : 'w-60 lg:w-[4.25rem]',
       ]">

    {{-- Backdrop e mobile — teleport ta kharej az flex e shell beshine. --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak @click="open = false"
             class="fixed inset-0 z-40 bg-ink/40 lg:hidden" aria-hidden="true"></div>
    </template>

    <div class="flex h-16 items-center gap-2 border-b border-line px-3">
        {{-- Rail toggle — faghat desktop (tu mobile drawer hamishe pahn e). --}}
        <button type="button" @click="toggle()" :aria-expanded="expanded"
                aria-controls="panel-nav" aria-label="باز و بسته کردن منو"
                class="hidden size-10 shrink-0 place-items-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink lg:grid">
            <x-icon name="menu" />
        </button>
        {{-- Bastan e drawer — faghat mobile. --}}
        <button type="button" @click="open = false" aria-label="بستن منو"
                class="grid size-11 shrink-0 place-items-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink lg:hidden">
            <x-icon name="close" />
        </button>
        <span x-show="wide" x-cloak class="truncate font-semibold">تابلو قیمت</span>
    </div>

    <nav id="panel-nav" class="flex-1 overflow-y-auto overflow-x-hidden py-3">
        @foreach ($groups as $group)
            <div class="px-3 pb-1 pt-3 first:pt-0">
                <p x-show="wide" x-cloak class="px-2 text-xs font-medium text-muted-2">{{ $group['label'] }}</p>
                {{-- Dar halat-e baste, heading yek khat mishe: goroh-bandi dide mishe, kalame ha na. --}}
                <div x-show="!wide" x-cloak class="mx-2 my-2 border-t border-line-2"></div>
            </div>

            <ul class="space-y-0.5 px-3">
                @foreach ($group['items'] as $item)
                    @php
                        $children = $item['children'] ?? [];
                        $childKeys = array_column($children, 'key');
                        // Parent active e age khodesh ya yeki az bache ha `current` bashe.
                        $active = $current === $item['key'] || in_array($current, $childKeys, true);
                        $disabled = $item['route'] === null;
                    @endphp
                    <li @class(['relative', 'rounded-xl transition-colors' => $children !== []])
                        @if ($children !== [])
                            x-data="{ sub: @js($active) }"
                            {{-- Group e baz yek panel e narm migire ta parent va bache ha
                                 yek majmu'e dide shan (na yek list e saf). --}}
                            :class="wide && sub && 'bg-surface-3/70 p-1'"
                        @endif
                        @mouseenter="show('{{ $item['key'] }}')" @mouseleave="hide('{{ $item['key'] }}')"
                        @focusin="show('{{ $item['key'] }}')" @focusout="hide('{{ $item['key'] }}')">

                        @if ($children === [])
                            <a @if (! $disabled) href="{{ route($item['route']) }}" @else href="#" aria-disabled="true" tabindex="-1" @endif
                               aria-label="{{ $item['label'] }}"
                               @class([
                                   'relative flex h-10 items-center gap-3 rounded-lg px-2.5 text-sm transition-colors',
                                   // Donezo (doc §5): fa'al = navar e brand e samt + bg e surface + bold.
                                   'bg-surface font-bold text-ink shadow-xs' => $active,
                                   'text-ink-3 hover:bg-surface-3' => ! $active && ! $disabled,
                                   'text-muted-3 cursor-not-allowed' => $disabled,
                               ])>
                                @if ($active)
                                    <span class="absolute inset-y-2 start-0 w-0.5 rounded-full bg-brand-700" aria-hidden="true"></span>
                                @endif
                                <x-icon :name="$item['icon']" class="size-5 shrink-0" />
                                <span x-show="wide" x-cloak class="truncate">{{ $item['label'] }}</span>
                                @if ($disabled)
                                    <span x-show="wide" x-cloak class="ms-auto text-[10px] text-muted-3">به‌زودی</span>
                                @endif
                            </a>
                        @else
                            {{-- Parent: hamishe yek LINK be bache ye aval (ghabl az Alpine
                                 va tu rail e baste kar mikone)؛ vaghti sidebar baz e، click
                                 faghat zir-menu ro baz/baste mikone. Kart e sefid faghat
                                 vaghti zir-menu baste-st — vagarna bache ye active hilight
                                 dare va do hilight shologh mishe. --}}
                            <a href="{{ route($item['route']) }}"
                               aria-label="{{ $item['label'] }}"
                               @click="if (wide) { $event.preventDefault(); sub = ! sub }"
                               :aria-expanded="wide ? sub.toString() : null"
                               aria-controls="sub-{{ $item['key'] }}"
                               class="relative flex h-10 items-center gap-3 rounded-lg px-2.5 text-sm transition-colors"
                               :class="{{ $active ? 'true' : 'false' }} && ! (wide && sub)
                                   ? 'bg-surface font-bold text-ink shadow-xs'
                                   : ({{ $active ? 'true' : 'false' }} ? 'font-bold text-ink hover:bg-surface/70' : 'text-ink-3 hover:bg-surface-3')">
                                @if ($active)
                                    <span x-show="! (wide && sub)" class="absolute inset-y-2 start-0 w-0.5 rounded-full bg-brand-700" aria-hidden="true"></span>
                                @endif
                                <x-icon :name="$item['icon']" class="size-5 shrink-0" />
                                <span x-show="wide" x-cloak class="truncate">{{ $item['label'] }}</span>
                                <x-icon name="chevron-down" x-show="wide" x-cloak
                                        class="ms-auto size-4 shrink-0 text-muted transition-transform duration-200"
                                        ::class="sub && 'rotate-180'" />
                            </a>

                            {{-- Bache ha: ba khat e rahnama ZIR e markaz e icon e parent
                                 indent mishan (p-1 + px-2.5 + nesf e icon ≈ ms-5). --}}
                            <ul id="sub-{{ $item['key'] }}" x-show="wide && sub" x-cloak x-collapse
                                class="ms-5 mt-1 space-y-0.5 border-s border-line pb-1 ps-2">
                                @foreach ($children as $child)
                                    @php $childActive = $current === $child['key']; @endphp
                                    <li>
                                        <a href="{{ route($child['route']) }}"
                                           @if ($childActive) aria-current="page" @endif
                                           @class([
                                               'relative flex h-9 items-center rounded-lg px-3 text-[0.8125rem] transition-colors',
                                               'bg-surface font-semibold text-ink shadow-xs' => $childActive,
                                               'text-ink-3 hover:bg-surface/70 hover:text-ink' => ! $childActive,
                                           ])>
                                            @if ($childActive)
                                                {{-- Ru khod e khat e rahnama (ps-2 + 1px border) — hamun
                                                     tike az khat sabz mishe. --}}
                                                <span class="absolute inset-y-1.5 -start-[0.5625rem] w-0.5 rounded-full bg-brand-700" aria-hidden="true"></span>
                                            @endif
                                            <span class="truncate">{{ $child['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        {{-- RTL-aware: rail samt-e rast neshaste, pas tooltip be samt-e chap baz mishe
                             va hich vaght rooye icon-i ke tosif mikone nemishine. --}}
                        <div x-show="hovered === '{{ $item['key'] }}'" x-cloak x-transition.opacity.duration.120ms
                             role="tooltip"
                             class="pointer-events-none absolute start-full top-5 z-50 ms-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-ink px-2.5 py-1.5 text-xs text-ink-invert shadow-lg">
                            {{ $item['label'] }}
                        </div>
                    </li>
                @endforeach
            </ul>
        @endforeach
    </nav>

    {{--
        Karbar-e vared-shode + dokme-ye khoroj, ta'e sidebar. Hamin ja mimune ta tu rail-e baste
        ham (bedoone scroll-e nav) dide beshe, ba hamun olgoo-ye tooltip-e RTL-aware-e item-ha.
        `auth()->user()` mostaghiman: in bakhsh faghat dakhel-e panel-e ba middleware-e `auth`
        render mishe, pas hamishe yek karbar hast.
    --}}
    @php($user = auth()->user())
    <div class="border-t border-line px-3 py-3">
        <p x-show="wide" x-cloak class="truncate px-2.5 pb-2 text-xs text-muted" title="{{ $user?->name ?: $user?->phone }}">
            {{ $user?->name ?: $user?->phone }}
        </p>

        <div class="relative"
             @mouseenter="show('logout')" @mouseleave="hide('logout')"
             @focusin="show('logout')" @focusout="hide('logout')">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" aria-label="خروج"
                        class="flex h-10 w-full items-center gap-3 rounded-lg px-2.5 text-sm text-ink-3 transition-colors hover:bg-surface-3 hover:text-ink">
                    <x-icon name="logout" class="size-5 shrink-0" />
                    <span x-show="wide" x-cloak class="truncate">خروج</span>
                </button>
            </form>

            {{-- Hamun olgoo-ye tooltip-e item-haye nav: samt-e chap baz mishe, rooye icon nemishine. --}}
            <div x-show="hovered === 'logout'" x-cloak x-transition.opacity.duration.120ms
                 role="tooltip"
                 class="pointer-events-none absolute start-full top-1/2 z-50 ms-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-ink px-2.5 py-1.5 text-xs text-ink-invert shadow-lg">
                خروج
            </div>
        </div>
    </div>
</aside>
