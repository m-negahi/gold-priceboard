<x-layouts.panel title="تنظیمات سئو" current="seo" :breadcrumbs="['پنل', 'سئو']">
    <x-panel.page-header title="تنظیمات سئو" description="فیلد خالی از مقدار پیش‌فرض سایت استفاده می‌کند." />
    @if(session('success'))<p class="mb-4 rounded-xl bg-brand-soft p-4 text-brand">{{ session('success') }}</p>@endif
    @foreach ($pages as $page)
        <form method="POST" action="{{ route('panel.seo.update', $page['key']) }}" class="max-w-3xl space-y-5 rounded-2xl bg-surface p-6 shadow-xs">
            @csrf @method('PUT')
            <h2 class="text-lg font-bold text-ink">{{ $page['label'] }}</h2>
            <x-form.field name="title" label="عنوان"><x-form.input name="title" :value="old('title', $page['values']['title'] ?? '')" maxlength="255" /></x-form.field>
            <x-form.field name="description" label="توضیحات"><x-form.textarea name="description" maxlength="500">{{ old('description', $page['values']['description'] ?? '') }}</x-form.textarea></x-form.field>
            <x-form.field name="canonical_url" label="نشانی اصلی (canonical)"><x-form.input name="canonical_url" :value="old('canonical_url', $page['values']['canonical_url'] ?? '')" ltr type="url" /></x-form.field>
            <x-form.field name="robots" label="دسترسی موتور جست‌وجو"><x-form.select name="robots" :options="['' => 'پیش‌فرض', 'index,follow' => 'ایندکس و دنبال‌کردن', 'noindex,follow' => 'عدم ایندکس؛ دنبال‌کردن لینک', 'index,nofollow' => 'ایندکس؛ عدم دنبال‌کردن لینک', 'noindex,nofollow' => 'عدم ایندکس و دنبال‌کردن']" :value="old('robots', $page['values']['robots'] ?? '')" /></x-form.field>
            <x-form.field name="og_image" label="نشانی تصویر اشتراک‌گذاری"><x-form.input name="og_image" :value="old('og_image', $page['values']['og_image'] ?? '')" ltr type="url" /></x-form.field>
            @can('seo.manage')<button class="min-h-11 rounded-xl bg-brand px-5 text-on-brand">ذخیره تنظیمات</button>@endcan
        </form>
    @endforeach
</x-layouts.panel>
