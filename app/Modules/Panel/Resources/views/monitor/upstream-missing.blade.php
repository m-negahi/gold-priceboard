{{-- 404 e SADEGH baraye call-i ke az daftarche ye halghe-i birun rafte (P4-20). --}}

<x-layouts.panel title="فراخوانی پیدا نشد" current="monitor.upstream" :breadcrumbs="['پنل', 'پایش', 'منابع']">
    <section class="mx-auto mt-10 max-w-lg rounded-2xl bg-surface card-p py-10 text-center shadow-xs">
        <x-icon name="clock" class="mx-auto size-10 text-muted-3" />
        <h1 class="mt-3 text-xl font-bold text-ink">این فراخوانی دیگر در دسترس نیست</h1>
        <p class="mt-2 text-sm text-muted">
            دفترچه فقط ~یک ساعت آخر را نگه می‌دارد؛ این مورد از دفترچه خارج شده یا شناسه‌اش درست نیست.
        </p>
        <a href="{{ route('panel.monitor.upstream') }}"
           class="mt-5 inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand hover:bg-brand-2">
            <x-icon name="arrow-back" class="size-4" />
            بازگشت به پایش منابع
        </a>
    </section>
</x-layouts.panel>
