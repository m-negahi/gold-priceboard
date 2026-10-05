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
<body class="min-h-dvh bg-canvas text-ink antialiased">
    <div class="flex">
        <x-panel.sidebar :groups="\App\Modules\Panel\Support\Navigation::groups()" :current="$current" />

        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-40 flex h-16 items-center gap-4 border-b border-line bg-surface/90 px-6 backdrop-blur">
                <nav class="flex min-w-0 items-center gap-2 text-sm text-muted" aria-label="مسیر">
                    @foreach ($breadcrumbs as $crumb)
                        @if (! $loop->first)
                            <x-icon name="chevron" class="size-4 shrink-0 text-muted-3" />
                        @endif
                        <span @class(['truncate', 'text-ink font-medium' => $loop->last])>{{ $crumb }}</span>
                    @endforeach
                </nav>

                {{-- Karbar va dokme-ye khoroj hala ta'e sidebar-and, ta tu rail-e baste ham dide beshan. --}}
            </header>

            <main class="p-6">{{ $slot }}</main>
        </div>
    </div>

    {{-- Host-haye sarasari: toast ha va dialog-e ta'yid yek bar render mishan, har safhe az store-eshun estefade mikone. --}}
    <x-panel.toasts />
    <x-panel.confirm />
</body>
</html>
