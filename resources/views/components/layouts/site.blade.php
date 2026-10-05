@props([
    'title' => null,
    'description' => null,
    'wide' => false,
    // `chrome` header/ticker/footer ro dor-e slot mizare; login va safhe-haye "bedoone menu" false midan.
    'chrome' => true,
])

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">

    <title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif

    {{-- Vazirmatn-e self-hosted dakhel-e app.css miad (ADR-0023): hich origin-e shakhs-e sales nist. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('head')
</head>
<body class="flex min-h-dvh flex-col bg-canvas text-ink antialiased">
    {{-- ⚠️ `chrome` fe'lan render NEMISHE: component haye <x-site.ticker/header/footer>
         e noskhe ye ghabli hanuz nayamadan (Phase 5 — site e omumi). Login `chrome=false`
         mide pas masir esh salem ast؛ rooz-i ke chrome khasti، aval un 3 component ro biar. --}}
    {{ $slot }}

    @stack('scripts')
</body>
</html>
