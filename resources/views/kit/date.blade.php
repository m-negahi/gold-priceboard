<x-layouts.panel title="فیلد تاریخ شمسی" current="kit" :breadcrumbs="['پنل', 'ابزارک‌ها', 'تاریخ شمسی']">
    <h1 class="text-xl font-semibold">فیلد تاریخ شمسی</h1>
    <p class="mt-1 text-sm text-muted">
        <code class="ltr text-xs">x-form.jalali-date</code> — تایپ با ارقام فارسی یا لاتین، یا انتخاب از چرخ‌های روز / ماه / سال. مقدار خروجی همیشه تاریخ میلادی ISO است.
    </p>

    {{-- Yek state-e Alpine baraye kol-e safhe ta khorooji-ye har field zende dide beshe --}}
    <div x-data="{ plain: '', bound: '2026-09-26', ranged: '', named: '' }" class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-card border border-line bg-surface p-5">
            <h2 class="text-sm font-semibold text-ink">ساده</h2>
            <p class="mt-1 text-xs text-muted">بدون مقدار اولیه، بدون محدوده.</p>
            <div class="mt-4">
                <x-form.jalali-date x-model="plain" label="تاریخ انتشار" hint="خالی یعنی انتشار همین حالا." data-kit="plain" />
            </div>
            <p class="mt-3 text-xs text-muted">ISO: <output data-output="plain" class="ltr num font-medium text-ink" x-text="plain || '—'"></output></p>
        </section>

        <section class="rounded-card border border-line bg-surface p-5">
            <h2 class="text-sm font-semibold text-ink">با مقدار اولیه</h2>
            <p class="mt-1 text-xs text-muted">از سرور <span class="ltr num">2026-09-26</span> آمده و باید <span class="num">۱۴۰۵/۰۷/۰۴</span> نشان دهد.</p>
            <div class="mt-4">
                <x-form.jalali-date x-model="bound" label="تاریخ ثبت" data-kit="bound" />
            </div>
            <p class="mt-3 text-xs text-muted">ISO: <output data-output="bound" class="ltr num font-medium text-ink" x-text="bound || '—'"></output></p>
            <button type="button" class="mt-2 text-xs text-brand hover:underline" @click="bound = '2025-03-20'" data-kit-set-bound>از بیرون روی ۱۴۰۳/۱۲/۳۰ بگذار</button>
        </section>

        <section class="rounded-card border border-line bg-surface p-5">
            <h2 class="text-sm font-semibold text-ink">با محدوده</h2>
            <p class="mt-1 text-xs text-muted">فقط از <span class="num">۱۴۰۵/۰۱/۰۱</span> تا <span class="num">۱۴۰۵/۱۲/۲۹</span>؛ بیرون از آن تایپ نمی‌شود و چرخ‌ها هم رد می‌شوند.</p>
            <div class="mt-4">
                <x-form.jalali-date x-model="ranged" label="تاریخ در سال ۱۴۰۵" min="2026-03-21" max="2027-03-20" data-kit="ranged" />
            </div>
            <p class="mt-3 text-xs text-muted">ISO: <output data-output="ranged" class="ltr num font-medium text-ink" x-text="ranged || '—'"></output></p>
        </section>

        <section class="rounded-card border border-line bg-surface p-5">
            <h2 class="text-sm font-semibold text-ink">غیرفعال و فرم کلاسیک</h2>
            <p class="mt-1 text-xs text-muted">اولی قفل است؛ دومی با <code class="ltr text-xs">name</code> یک input مخفی برای فرم بدون Alpine دارد.</p>
            <div class="mt-4 grid gap-4">
                <x-form.jalali-date label="تاریخ قفل‌شده" value="2026-09-26" :disabled="true" data-kit="disabled" />
                <form data-kit-form @submit.prevent>
                    <x-form.jalali-date x-model="named" name="published_at" label="تاریخ انتشار (با name)" data-kit="named" />
                </form>
            </div>
            <p class="mt-3 text-xs text-muted">ISO: <output data-output="named" class="ltr num font-medium text-ink" x-text="named || '—'"></output></p>
        </section>
    </div>
</x-layouts.panel>
