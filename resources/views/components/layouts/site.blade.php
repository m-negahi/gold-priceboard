@props([
    'title' => null,
    'description' => null,
    'wide' => false,
    // `chrome` header/ticker/footer ro dor-e slot mizare; login va safhe-haye "bedoone menu" false midan.
    'chrome' => true,
    // Zamine ye body — TV (P5-03) tire ast (`bg-surface-dark`)؛ baghi canvas.
    'bodyClass' => 'bg-canvas text-ink',
    // SEO e kamel (P5-05): `App\Modules\Seo\Application\Dto\SeoMetaDto` —
    // title/description/canonical/robots/OG/JSON-LD. Bedun esh faghat title/description.
    'seo' => null,
])

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">

    @if ($seo)
        <title>{{ $seo->title }}</title>
        <meta name="description" content="{{ $seo->description }}">
        <meta name="robots" content="{{ $seo->robots }}">
        <link rel="canonical" href="{{ $seo->canonical }}">
        <meta property="og:type" content="website">
        <meta property="og:locale" content="fa_IR">
        <meta property="og:site_name" content="{{ $seo->siteName }}">
        <meta property="og:title" content="{{ $seo->title }}">
        <meta property="og:description" content="{{ $seo->description }}">
        <meta property="og:url" content="{{ $seo->canonical }}">
        @if ($seo->ogImage)
            <meta property="og:image" content="{{ $seo->ogImage }}">
        @endif
        <meta name="twitter:card" content="{{ $seo->ogImage ? 'summary_large_image' : 'summary' }}">
        @if ($seo->jsonLd !== [])
            {{-- JSON-LD: `JsonLd::encode` (JSON_HEX_TAG) — `</script>` tu matn e override farar nemikone. --}}
            <script type="application/ld+json">{!! $seo->jsonLdScript() !!}</script>
        @endif
    @else
        <title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
    @endif

    {{-- Vazirmatn-e self-hosted dakhel-e app.css miad (ADR-0023): hich origin-e shakhs-e sales nist. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('head')
</head>
<body class="flex min-h-dvh flex-col antialiased {{ $bodyClass }}">
    @if ($chrome)
        {{-- Header e site e omumi (Phase 5). --}}
        <header class="sticky top-0 z-40 border-b border-line bg-surface/90 backdrop-blur">
            <div class="container-wide flex h-16 items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5" aria-label="تابلو قیمت — صفحه اصلی" wire:navigate>
                    <span class="grid size-9 place-items-center rounded-xl bg-brand-grad text-on-brand shadow-(--bp-elev-brand)">
                        <x-icon name="coins" class="size-5" />
                    </span>
                    <span class="text-lg font-bold">تابلو قیمت</span>
                </a>

                <nav class="flex items-center gap-2 text-sm">
                    <a href="#api" class="hidden rounded-lg px-3 py-2 font-medium text-muted hover:text-ink sm:inline-block">API</a>
                    @auth
                        {{-- Panel faghat baraye admin (baraye baghi 404 ast)؛ moshtari be ticket haye khodesh mire (P7-01). --}}
                        @if (auth()->user()->is_admin)
                            <a href="{{ route('panel.dashboard') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 font-semibold text-on-brand shadow-(--bp-elev-brand) hover:bg-brand-2">
                                <x-icon name="gauge" class="size-4" />
                                پنل
                            </a>
                        @else
                            <a href="{{ route('account.tickets.index') }}" data-account-link class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 font-semibold text-on-brand shadow-(--bp-elev-brand) hover:bg-brand-2">
                                <x-icon name="ticket" class="size-4" />
                                تیکت‌ها
                            </a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 font-semibold text-on-brand shadow-(--bp-elev-brand) hover:bg-brand-2">
                            ورود
                            <x-icon name="arrow" class="size-4 rotate-180" />
                        </a>
                    @endauth
                </nav>
            </div>
        </header>
    @endif

    <main class="flex-1">{{ $slot }}</main>

    @if ($chrome)
        <footer class="border-t border-line bg-surface">
            <div class="container-wide flex flex-col gap-3 py-8 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
                <p>© تابلو قیمت — قیمت لحظه‌ای طلا، سکه و نقره از چند منبع.</p>
                <nav class="flex items-center gap-4">
                    <a href="{{ route('tv') }}" class="hover:text-ink">تابلوی تلویزیونی</a>
                    <a href="#terms" class="hover:text-ink">قوانین</a>
                    <a href="#privacy" class="hover:text-ink">حریم خصوصی</a>
                    <a href="#support" class="hover:text-ink">پشتیبانی</a>
                </nav>
            </div>
        </footer>
    @endif

    @stack('scripts')
</body>
</html>
