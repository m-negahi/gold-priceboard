<x-layouts.panel title="کیت: فیلد نشانی" current="pages" :breadcrumbs="['پنل', 'کیت', 'فیلد نشانی']">
    {{-- Namayesh-e x-form.slug dar se halat: record-e jadid (donbal-e title), record-e mojood (ghofl), va khata. --}}
    <h1 class="text-xl font-semibold">فیلد نشانی (slug)</h1>
    <p class="mt-1 text-sm text-muted">نمونه‌ی زنده‌ی <span class="ltr">x-form.slug</span> — فقط در محیط توسعه.</p>

    <section class="mt-6 rounded-card border border-line bg-surface p-6" aria-labelledby="kit-new" x-data="{ form: { title: '', slug: '' } }">
        <h2 id="kit-new" class="text-base font-semibold">صفحه‌ی جدید</h2>
        <p class="mt-1 text-sm text-muted">تا وقتی قفل است، نشانی از عنوان ساخته می‌شود؛ مداد آن را برای ویرایش دستی باز می‌کند.</p>

        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <div>
                <label for="kit-title" class="mb-2 block text-sm font-medium">عنوان صفحه</label>
                <input id="kit-title" type="text" x-model="form.title" autocomplete="off"
                       class="h-12 w-full rounded-xl border border-line-3 bg-surface px-4 text-ink outline-none transition-[border-color,box-shadow] duration-(--bp-dur-fast) focus:border-brand focus:shadow-(--bp-elev-brand)">
            </div>

            <x-form.slug name="slug" prefix="/pages/" source="#kit-title" x-model="form.slug" />
        </div>

        <p class="mt-4 text-sm text-muted">مقدار مدل: <code class="ltr num rounded-chip bg-surface-3 px-2 py-0.5" data-kit-model x-text="form.slug || '—'"></code></p>
    </section>

    <section class="mt-6 rounded-card border border-line bg-surface p-6" aria-labelledby="kit-existing" x-data="{ form: { title: 'قیمت طلا', slug: 'gold-price' } }">
        <h2 id="kit-existing" class="text-base font-semibold">صفحه‌ی موجود</h2>
        <p class="mt-1 text-sm text-muted">نشانی یک رکورد موجود قفل است و با تغییر عنوان عوض نمی‌شود؛ تغییر آن یک ۳۰۱ خودکار می‌سازد.</p>

        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <div>
                <label for="kit-title-existing" class="mb-2 block text-sm font-medium">عنوان صفحه</label>
                <input id="kit-title-existing" type="text" x-model="form.title" autocomplete="off"
                       class="h-12 w-full rounded-xl border border-line-3 bg-surface px-4 text-ink outline-none transition-[border-color,box-shadow] duration-(--bp-dur-fast) focus:border-brand focus:shadow-(--bp-elev-brand)">
            </div>

            <x-form.slug name="slug_existing" prefix="/pages/" source="#kit-title-existing" value="gold-price" existing x-model="form.slug" />
        </div>
    </section>

    <section class="mt-6 rounded-card border border-line bg-surface p-6" aria-labelledby="kit-error" x-data="{ form: { slug: 'api' } }">
        <h2 id="kit-error" class="text-base font-semibold">حالت خطا</h2>

        <div class="mt-5 lg:w-1/2">
            <x-form.slug name="slug_error" prefix="/pages/" value="api" error="نشانی «api» رزرو شده است و برای محتوا قابل استفاده نیست." x-model="form.slug" />
        </div>
    </section>
</x-layouts.panel>
