{{-- ⚠️ MOVAGHAT (P2-07): maghsad e login e admin ta dashboard e vaghei (P3-00/P3-05)
     biad — DashboardQuery e noskhe ye ghabli be Pricing e un vabaste-ast va bayad be
     BoardQuery e in proje vasl she. In safhe faghat sabet mikone auth kamel kar mikone. --}}
<x-layouts.site :chrome="false" title="میزکار">
    <main class="grid min-h-dvh place-items-center px-4">
        <div class="w-full max-w-md rounded-2xl border border-line bg-surface p-8 text-center shadow-sm">
            <span class="mx-auto grid size-12 place-items-center rounded-xl bg-brand text-on-brand">
                <x-icon name="gauge" class="size-6" />
            </span>
            <h1 class="mt-4 text-xl font-bold">میزکار به‌زودی</h1>
            <p class="mt-2 text-muted">ورود شما انجام شد ({{ auth()->user()?->phone }}). میزکار اصلی در فاز ۳ ساخته می‌شود.</p>
            <form method="post" action="{{ route('logout') }}" class="mt-6">
                @csrf
                <button type="submit" class="h-11 w-full rounded-xl border border-line font-medium text-ink hover:bg-surface-2">خروج</button>
            </form>
        </div>
    </main>
</x-layouts.site>
