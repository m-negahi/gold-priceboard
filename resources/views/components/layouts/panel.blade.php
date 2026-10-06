@props(['title' => null, 'current' => null, 'breadcrumbs' => []])

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — پنل' : 'پنل مدیریت' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Shell e Donezo (docs/dashboard-design.md §4): canvas e khakestari posht،
     kol e panel dakhel e yek card e gerd e bozorg («floating panel»). --}}
<body class="min-h-dvh bg-canvas text-ink antialiased lg:p-4">
    <div class="shell-app flex min-h-dvh lg:min-h-[calc(100dvh-2rem)]">
        <x-panel.sidebar :groups="\App\Modules\Panel\Support\Navigation::groups()" :current="$current" />

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Topbar: card e gerd e JODAGANE (doc §4)، na yek navar e full-width. --}}
            <header class="sticky top-0 z-40 px-4 pt-4 lg:px-6">
                <div class="flex h-14 items-center gap-2 rounded-2xl bg-surface px-3 shadow-sm lg:gap-4 lg:px-4">
                    {{-- Hamburger e mobile (P4-19) — drawer e sidebar ro ba event
                         baz mikone (sidebar kharej az scope e in dokme-ast). --}}
                    <button type="button" x-data @click="$dispatch('toggle-sidebar')"
                            aria-label="باز کردن منو"
                            class="grid size-11 shrink-0 place-items-center rounded-lg text-muted hover:bg-surface-2 hover:text-ink lg:hidden">
                        <x-icon name="menu" />
                    </button>

                    <nav class="flex min-w-0 flex-1 items-center gap-2 text-sm text-muted" aria-label="مسیر">
                        @foreach ($breadcrumbs as $crumb)
                            @if (! $loop->first)
                                <x-icon name="chevron" class="size-4 shrink-0 text-muted-3" />
                            @endif
                            <span @class(['truncate', 'text-ink font-medium' => $loop->last])>{{ $crumb }}</span>
                        @endforeach
                    </nav>

                    {{-- Karbar va khoroj ta'e sidebar-and ta tu rail e baste ham dide beshan. --}}
                </div>
            </header>

            <main class="flex-1 p-4 lg:p-6">{{ $slot }}</main>
        </div>
    </div>

    {{-- Host-haye sarasari: toast ha va dialog-e ta'yid yek bar render mishan, har safhe az store-eshun estefade mikone. --}}
    <x-panel.toasts />
    <x-panel.confirm />
</body>
</html>
