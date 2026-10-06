@props(['groups' => [], 'current' => null])

{{--
    Do halat-e vaghe'i (@docs/frontend/admin-panel.md §1). Dar halat-e baste, tooltip tanha chizi-e
    ke label-e item ro hamel mikone, pas be :focus-visible ham javab mide na faghat hover, va har
    item dar har halati aria-label-esh ro negah midare.
--}}
{{-- Dakhel e shell e Donezo: sidebar shafaf ru surface-2 ye shell mishine
     (doc §4)؛ faghat yek hairline e logical (`border-e`) jodash mikone. --}}
<aside x-data="panelSidebar" class="sticky top-0 flex h-dvh shrink-0 flex-col border-e border-line bg-transparent transition-[width] duration-200 lg:top-4 lg:h-[calc(100dvh-2rem)]"
       :class="expanded ? 'w-60' : 'w-[4.25rem]'">

    <div class="flex h-16 items-center gap-2 border-b border-line px-3">
        <button type="button" @click="toggle()" :aria-expanded="expanded"
                aria-controls="panel-nav" aria-label="باز و بسته کردن منو"
                class="grid size-10 shrink-0 place-items-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink">
            <x-icon name="menu" />
        </button>
        <span x-show="expanded" x-cloak class="truncate font-semibold">تابلو قیمت</span>
    </div>

    <nav id="panel-nav" class="flex-1 overflow-y-auto overflow-x-hidden py-3">
        @foreach ($groups as $group)
            <div class="px-3 pb-1 pt-3 first:pt-0">
                <p x-show="expanded" x-cloak class="px-2 text-xs font-medium text-muted-2">{{ $group['label'] }}</p>
                {{-- Dar halat-e baste, heading yek khat mishe: goroh-bandi dide mishe, kalame ha na. --}}
                <div x-show="!expanded" x-cloak class="mx-2 my-2 border-t border-line-2"></div>
            </div>

            <ul class="space-y-0.5 px-3">
                @foreach ($group['items'] as $item)
                    @php
                        $active = $current === $item['key'];
                        $disabled = $item['route'] === null;
                    @endphp
                    <li class="relative"
                        @mouseenter="show('{{ $item['key'] }}')" @mouseleave="hide('{{ $item['key'] }}')"
                        @focusin="show('{{ $item['key'] }}')" @focusout="hide('{{ $item['key'] }}')">

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
                            <span x-show="expanded" x-cloak class="truncate">{{ $item['label'] }}</span>
                            @if ($disabled)
                                <span x-show="expanded" x-cloak class="ms-auto text-[10px] text-muted-3">به‌زودی</span>
                            @endif
                        </a>

                        {{-- RTL-aware: rail samt-e rast neshaste, pas tooltip be samt-e chap baz mishe
                             va hich vaght rooye icon-i ke tosif mikone nemishine. --}}
                        <div x-show="hovered === '{{ $item['key'] }}'" x-cloak x-transition.opacity.duration.120ms
                             role="tooltip"
                             class="pointer-events-none absolute start-full top-1/2 z-50 ms-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-ink px-2.5 py-1.5 text-xs text-ink-invert shadow-lg">
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
        <p x-show="expanded" x-cloak class="truncate px-2.5 pb-2 text-xs text-muted" title="{{ $user?->name ?: $user?->phone }}">
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
                    <span x-show="expanded" x-cloak class="truncate">خروج</span>
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
