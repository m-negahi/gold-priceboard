@props([
    // Endpoint-e JSON ke envelope-e { data, meta } mide (Common\Http\Resources\TableResponse).
    'endpoint',
    // list<array{key, label, sortable?, sort?, align?, num?, ltr?, type?, badges?, empty?, class?, href?}> — href: cell mishe link ({id} template).
    'columns' => [],
    // list<array{key, label, options: list<array{value, label}>}> — select-haye toolbar.
    'filters' => [],
    'searchable' => true,
    'searchPlaceholder' => 'جستجو…',
    'perPage' => 25,
    'perPageOptions' => [10, 25, 50, 100],
    // Entekhab-e radif + navar-e bulk faghat vaghti seda-zanande bekhad.
    'selectable' => false,
    // list<array{key, label, url, payload?, method?, tone?, confirm?, done?}>
    'bulkActions' => [],
    // list<array{label, icon, href?, event?, tone?}> — href yek template-e matn-i ba {id}, na route(); event yek
    // $dispatch ba radif be jaye link (baraye modal / POST).
    'rowActions' => [],
    // array{title?, text?, action?: array{label, href}}
    'empty' => [],
    'rowKey' => 'id',
    'syncUrl' => true,
    'caption' => 'جدول',
])

@php
    // Class-haye tone inja-and (na tu JS) ta Tailwind hatman emit-eshun kone.
    $tones = [
        'ok' => 'bg-ok-soft text-ok',
        'warn' => 'bg-warn-soft text-warn',
        'info' => 'bg-info-soft text-info',
        'danger' => 'bg-danger-soft text-danger',
        'muted' => 'bg-surface-3 text-muted',
        'brand' => 'bg-brand-soft text-brand-2',
    ];
    $aligns = ['start' => 'text-right', 'center' => 'text-center', 'end' => 'text-left'];
    $config = [
        'endpoint' => $endpoint,
        'columns' => $columns,
        'filters' => $filters,
        'perPage' => $perPage,
        'perPageOptions' => $perPageOptions,
        'selectable' => $selectable,
        'bulkActions' => $bulkActions,
        'rowActions' => $rowActions,
        'empty' => $empty,
        'rowKey' => $rowKey,
        'syncUrl' => $syncUrl,
    ];
    $colspan = count($columns) + ($selectable ? 1 : 0) + ($rowActions !== [] ? 1 : 0);
@endphp

{{-- `datatable-reload` az har ja-ye safhe (ba'd az yek modal-e sakht/hazf) jadval ro dobare az server migire. --}}
<div x-data="datatable(@js($config))" {{ $attributes->merge(['class' => 'space-y-3']) }} data-datatable @datatable-reload.window="load()">

    {{-- ── Toolbar: yek radif — search, filter ha, per-page, refresh, slot-e seda-zanande ── --}}
    <div class="flex flex-wrap items-center gap-2">
        @if ($searchable)
            <label class="relative block w-full sm:w-72">
                <span class="sr-only">جستجو</span>
                <x-icon name="search" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-2" />
                <input type="search" x-model="q" placeholder="{{ $searchPlaceholder }}" autocomplete="off"
                       class="h-10 w-full rounded-lg border border-line bg-surface pr-9 pl-3 text-sm placeholder:text-muted-2 focus:border-brand">
            </label>
        @endif

        @foreach ($filters as $filter)
            <label class="flex h-10 items-center gap-2 rounded-lg border border-line bg-surface px-3 text-sm">
                <span class="text-muted">{{ $filter['label'] }}</span>
                <select x-model="filters.{{ $filter['key'] }}" class="bg-transparent text-sm text-ink focus:outline-none" aria-label="{{ $filter['label'] }}">
                    <option value="">همه</option>
                    @foreach ($filter['options'] as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>
        @endforeach

        <button type="button" x-show="isFiltered" x-cloak @click="clearFilters()"
                class="flex h-10 items-center gap-1 rounded-lg px-2 text-sm text-muted hover:bg-surface-3 hover:text-ink">
            <x-icon name="close" class="size-4" /> پاک کردن
        </button>

        <div class="mr-auto flex items-center gap-2">
            <label class="flex h-10 items-center gap-2 rounded-lg border border-line bg-surface px-3 text-sm">
                <span class="text-muted">در صفحه</span>
                <select x-model.number="perPage" class="num bg-transparent text-sm text-ink focus:outline-none" aria-label="تعداد در صفحه">
                    @foreach ($perPageOptions as $n)
                        <option value="{{ $n }}">{{ \App\Modules\Common\Helpers\Jalali::toPersianDigits((string) $n) }}</option>
                    @endforeach
                </select>
            </label>

            <button type="button" @click="load()" :disabled="loading" aria-label="بارگذاری دوباره" title="بارگذاری دوباره"
                    class="grid size-10 place-items-center rounded-lg border border-line bg-surface text-muted hover:text-ink disabled:opacity-50">
                <x-icon name="refresh" class="size-4" ::class="loading && 'animate-spin'" />
            </button>

            {{ $toolbar ?? '' }}
        </div>
    </div>

    {{-- ── Navar-e bulk: faghat vaghti chizi entekhab shode ── --}}
    @if ($selectable && $bulkActions !== [])
        <div x-show="selected.length > 0" x-cloak x-transition.opacity
             class="flex flex-wrap items-center gap-2 rounded-lg border border-brand-soft-2 bg-brand-soft px-3 py-2 text-sm" role="region" aria-label="عملیات گروهی">
            <span class="num text-brand-2" x-text="`${fa(selected.length)} ردیف انتخاب شده`"></span>
            @foreach ($bulkActions as $action)
                <button type="button" @click="runBulk(@js($action))" :disabled="busy"
                        class="h-8 rounded-md border border-line bg-surface px-3 text-sm hover:bg-surface-2 disabled:opacity-50 {{ ($action['tone'] ?? null) === 'danger' ? 'text-danger' : 'text-ink' }}">
                    {{ $action['label'] }}
                </button>
            @endforeach
            <button type="button" @click="selected = []" class="mr-auto text-sm text-muted hover:text-ink">لغو انتخاب</button>
        </div>
    @endif

    {{-- ── Jadval ── --}}
    <div class="overflow-x-auto rounded-card border border-line bg-surface" :aria-busy="loading">
        <table class="w-full text-sm" :class="loading && loaded && 'opacity-60'">
            <caption class="sr-only">{{ $caption }}</caption>
            <thead class="bg-surface-2 text-xs text-muted">
                <tr>
                    @if ($selectable)
                        <th scope="col" class="w-10 px-3 py-3">
                            <input type="checkbox" aria-label="انتخاب همه" :checked="allSelected" :indeterminate="someSelected"
                                   @change="toggleAll($event.target.checked)" class="size-4 rounded border-line-3 accent-brand">
                        </th>
                    @endif
                    @foreach ($columns as $column)
                        @php $align = $aligns[$column['align'] ?? 'start']; @endphp
                        <th scope="col" class="px-4 py-3 font-medium {{ $align }} {{ $column['class'] ?? '' }}"
                            :aria-sort="ariaSort(@js($column))">
                            @if ($column['sortable'] ?? false)
                                <button type="button" @click="toggleSort(@js($column))"
                                        class="group inline-flex items-center gap-1 rounded hover:text-ink focus-visible:text-ink"
                                        :class="isSorted(@js($column)) && 'text-ink'">
                                    <span>{{ $column['label'] }}</span>
                                    {{-- Icon samt-e chap-e label (enteha-ye khat dar RTL); flex khodesh jahat ro dorost mikone. --}}
                                    <span x-show="!isSorted(@js($column))" class="text-muted-3 group-hover:text-muted"><x-icon name="sort" class="size-3.5" /></span>
                                    <span x-show="isSorted(@js($column)) && direction === 'asc'" x-cloak><x-icon name="chevron-up" class="size-3.5" /></span>
                                    <span x-show="isSorted(@js($column)) && direction === 'desc'" x-cloak><x-icon name="chevron-down" class="size-3.5" /></span>
                                </button>
                            @else
                                {{ $column['label'] }}
                            @endif
                        </th>
                    @endforeach
                    @if ($rowActions !== [])
                        <th scope="col" class="w-24 px-3 py-3"><span class="sr-only">عملیات</span></th>
                    @endif
                </tr>
            </thead>

            {{-- Skeleton: faghat aval-e kar, ghabl az avalin javab. --}}
            <tbody x-show="showSkeleton" x-cloak class="divide-y divide-line-2" data-skeleton>
                @for ($i = 0; $i < 6; $i++)
                    <tr class="animate-pulse">
                        @if ($selectable)
                            <td class="px-3 py-3"><div class="size-4 rounded bg-surface-3"></div></td>
                        @endif
                        @foreach ($columns as $column)
                            <td class="px-4 py-3"><div class="h-3.5 rounded bg-surface-3 {{ $loop->even ? 'w-2/3' : 'w-1/2' }}"></div></td>
                        @endforeach
                        @if ($rowActions !== [])
                            <td class="px-3 py-3"></td>
                        @endif
                    </tr>
                @endfor
            </tbody>

            <tbody x-show="!showSkeleton" class="divide-y divide-line-2">
                <template x-for="row in rows" :key="row[rowKey]">
                    <tr class="hover:bg-surface-2" :class="isSelected(row) && 'bg-brand-soft/60'">
                        @if ($selectable)
                            <td class="px-3 py-3">
                                <input type="checkbox" :checked="isSelected(row)" @change="toggleRow(row)"
                                       :aria-label="`انتخاب ${row[rowKey]}`" class="size-4 rounded border-line-3 accent-brand">
                            </td>
                        @endif
                        @foreach ($columns as $column)
                            @php $align = $aligns[$column['align'] ?? 'start']; @endphp
                            <td class="px-4 py-3 {{ $align }} {{ $column['class'] ?? '' }}">
                                @if (($column['type'] ?? 'text') === 'badge')
                                    <span class="inline-flex rounded-chip px-2 py-0.5 text-xs font-medium"
                                          :class="@js($tones)[badge(@js($column), row['{{ $column['key'] }}']).tone] ?? '{{ $tones['muted'] }}'"
                                          x-text="cellText(row, @js($column))"></span>
                                @elseif (($column['type'] ?? 'text') === 'image')
                                    {{-- Sotoon-e tasvir (ketabkhane-ye rasane): meghdar URL-e thumbnail-e, `alt` az sotoon-e `altKey`. --}}
                                    <img :src="row['{{ $column['key'] }}']" :alt="row['{{ $column['altKey'] ?? 'alt' }}'] ?? ''" loading="lazy" decoding="async"
                                         class="size-12 rounded-lg bg-surface-2 object-cover ring-1 ring-line" data-cell-image>
                                @elseif (($column['type'] ?? 'text') === 'score')
                                    {{-- Nomre-ye 0–100 be shekl-e ring-e kuchik (≥۷۰ سبز، ≥۵۰ نارنجی، وگرنه قرمز); null = hanuz hesab nashode. --}}
                                    <span class="inline-flex items-center gap-1.5" data-cell-score
                                          :class="row['{{ $column['key'] }}'] === null ? 'text-muted-2' : row['{{ $column['key'] }}'] >= 70 ? 'text-ok' : row['{{ $column['key'] }}'] >= 50 ? 'text-warn' : 'text-danger'">
                                        <svg viewBox="0 0 20 20" class="size-5 -rotate-90" aria-hidden="true">
                                            <circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-opacity="0.2" stroke-width="3" />
                                            <circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                                    :stroke-dasharray="`${(Number(row['{{ $column['key'] }}'] ?? 0) / 100) * 50.27} 50.27`" />
                                        </svg>
                                        <span class="num font-medium" x-text="row['{{ $column['key'] }}'] === null ? '—' : fa(row['{{ $column['key'] }}'])"></span>
                                    </span>
                                @elseif (isset($column['href']))
                                    {{-- Sotoon-e link: hamun template-e {id}-e row action ha; masalan priority → nama-ye tartib. --}}
                                    <a :href="rowHref(@js($column['href']), row)" @class([
                                            'underline decoration-dotted underline-offset-4 hover:text-brand-2',
                                            'num' => $column['num'] ?? false,
                                            'ltr inline-block' => $column['ltr'] ?? false,
                                            'font-medium' => $column['strong'] ?? false,
                                        ]) x-text="cellText(row, @js($column))"></a>
                                @else
                                    <span @class([
                                            'num' => ($column['num'] ?? false) || in_array($column['type'] ?? 'text', ['money-toman', 'date-jalali'], true),
                                            'ltr inline-block' => $column['ltr'] ?? false,
                                            'font-medium' => $column['strong'] ?? false,
                                            'text-muted' => $column['muted'] ?? false,
                                        ]) x-text="cellText(row, @js($column))"></span>
                                @endif
                            </td>
                        @endforeach
                        @if ($rowActions !== [])
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    @foreach ($rowActions as $action)
                                        @if (isset($action['event']))
                                            {{-- Amal-e bedoone safhe (modal, POST): yek event ba radif be bala miferste; safhe goosh mide. --}}
                                            <button type="button" @click="$dispatch(@js($action['event']), row)" title="{{ $action['label'] }}" aria-label="{{ $action['label'] }}"
                                                    class="grid size-8 place-items-center rounded-md text-muted hover:bg-surface-3 hover:text-ink {{ ($action['tone'] ?? null) === 'danger' ? 'hover:text-danger' : '' }}">
                                                <x-icon :name="$action['icon'] ?? 'pencil'" class="size-4" />
                                            </button>
                                        @else
                                            <a :href="rowHref(@js($action['href']), row)" title="{{ $action['label'] }}" aria-label="{{ $action['label'] }}"
                                               class="grid size-8 place-items-center rounded-md text-muted hover:bg-surface-3 hover:text-ink">
                                                <x-icon :name="$action['icon'] ?? 'pencil'" class="size-4" />
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                        @endif
                    </tr>
                </template>

                {{-- Halat-e khali: "chizi nist" ba "jostojoo chizi nadad" fargh dare. --}}
                <tr x-show="showEmpty" x-cloak>
                    <td colspan="{{ $colspan }}" class="px-4 py-14 text-center" data-empty>
                        <x-icon name="inbox" class="mx-auto size-10 text-muted-3" />
                        <template x-if="isFiltered">
                            <div class="mt-3">
                                <p class="font-medium text-ink">چیزی پیدا نشد</p>
                                <p class="mt-1 text-sm text-muted">با این جستجو یا فیلترها ردیفی وجود ندارد.</p>
                                <button type="button" @click="clearFilters()" class="mt-4 h-9 rounded-lg border border-line bg-surface px-3 text-sm hover:bg-surface-2">پاک کردن جستجو و فیلترها</button>
                            </div>
                        </template>
                        <template x-if="!isFiltered">
                            <div class="mt-3">
                                <p class="font-medium text-ink">{{ $empty['title'] ?? 'هنوز چیزی ثبت نشده' }}</p>
                                @if (isset($empty['text']))
                                    <p class="mt-1 text-sm text-muted">{{ $empty['text'] }}</p>
                                @endif
                                @if (isset($empty['action']))
                                    <a href="{{ $empty['action']['href'] }}" class="mt-4 inline-flex h-9 items-center gap-1 rounded-lg bg-brand px-3 text-sm font-medium text-on-brand hover:bg-brand-2">
                                        <x-icon name="plus" class="size-4" /> {{ $empty['action']['label'] }}
                                    </a>
                                @endif
                            </div>
                        </template>
                    </td>
                </tr>

                {{-- Khata: payam + telash-e dobare; radif-haye ghabli agar bashan zir-esh mimunan. --}}
                <tr x-show="error" x-cloak>
                    <td colspan="{{ $colspan }}" class="px-4 py-10 text-center" role="alert" data-error>
                        <x-icon name="alert" class="mx-auto size-8 text-danger" />
                        <p class="mt-2 text-sm text-danger" x-text="error"></p>
                        <button type="button" @click="load()" class="mt-4 h-9 rounded-lg border border-line bg-surface px-3 text-sm hover:bg-surface-2">تلاش دوباره</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ── Safhe-bandi ── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted">
        <p class="num" aria-live="polite" x-text="rangeText"></p>

        <nav x-show="meta.last_page > 1" x-cloak aria-label="صفحه‌بندی" class="flex items-center gap-1">
            <button type="button" @click="goTo(page - 1)" :disabled="page <= 1 || loading" aria-label="صفحه قبل"
                    class="grid size-9 place-items-center rounded-lg border border-line bg-surface hover:bg-surface-2 disabled:opacity-40">
                {{-- Dar RTL "ghabl" samt-e rast-e; chevron-e ma be chap eshare mikone, pas bar'aks. --}}
                <x-icon name="chevron" class="size-4 rotate-180" />
            </button>
            <template x-for="item in pages" :key="item.key">
                <span>
                    <span x-show="item.gap" class="px-1 text-muted-3">…</span>
                    <button x-show="!item.gap" type="button" @click="goTo(item.n)" :disabled="loading"
                            :aria-current="item.n === page ? 'page' : null"
                            :class="item.n === page ? 'bg-brand text-on-brand border-brand' : 'bg-surface border-line hover:bg-surface-2'"
                            class="num h-9 min-w-9 rounded-lg border px-2" x-text="fa(item.n)"></button>
                </span>
            </template>
            <button type="button" @click="goTo(page + 1)" :disabled="page >= meta.last_page || loading" aria-label="صفحه بعد"
                    class="grid size-9 place-items-center rounded-lg border border-line bg-surface hover:bg-surface-2 disabled:opacity-40">
                <x-icon name="chevron" class="size-4" />
            </button>
        </nav>
    </div>
</div>
