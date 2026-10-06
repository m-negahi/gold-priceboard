{{-- ⚠️ MOVAGHAT (P2-07): mohtava placeholder ast ta dashboard e vaghei (P3-05)
     biad — vali az P3-03 be ba'd tu LAYOUT e vaghei ye panel (shell e Donezo +
     sidebar + topbar) render mishe ta layout az hamin hala zire bar e vaghei
     test she. --}}
<x-layouts.panel title="میزکار" current="dashboard" :breadcrumbs="['پنل', 'میزکار']">
    <div class="grid min-h-[60dvh] place-items-center">
        <div class="w-full max-w-md rounded-2xl bg-surface p-8 text-center shadow-sm">
            <span class="mx-auto grid size-12 place-items-center rounded-xl bg-brand-grad text-on-brand">
                <x-icon name="gauge" class="size-6" />
            </span>
            <h1 class="mt-4 text-xl font-bold">میزکار به‌زودی</h1>
            <p class="mt-2 text-muted">ورود شما انجام شد ({{ auth()->user()?->phone }}). میزکار اصلی در فاز ۳ ساخته می‌شود.</p>
        </div>
    </div>
</x-layouts.panel>
