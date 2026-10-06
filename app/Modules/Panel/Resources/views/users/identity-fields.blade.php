<div class="grid gap-4 sm:grid-cols-2" x-data="{ customerType: @js(old('customer_type', $profile['customer_type'] ?? 'individual')) }">
    <x-form.field name="customer_type" label="نوع مشتری" required>
        <x-form.select name="customer_type" :options="['individual' => 'حقیقی', 'legal' => 'حقوقی']" :value="old('customer_type', $profile['customer_type'] ?? 'individual')" x-model="customerType" />
    </x-form.field>
    <div x-show="customerType === 'individual'" x-cloak>
        <x-form.field name="national_code" label="کد ملی">
            <x-form.input name="national_code" ltr :value="old('national_code', $profile['national_code'] ?? '')" maxlength="10" />
        </x-form.field>
    </div>
    <div x-show="customerType === 'individual'" x-cloak>
        <x-form.field name="birth_date" label="تاریخ تولد (شمسی)" hint="نمونه: 1370/01/15">
            <x-form.input name="birth_date" ltr :value="old('birth_date', $profile['birth_date'] ?? '')" placeholder="1370/01/15" />
        </x-form.field>
    </div>
    <div x-show="customerType === 'legal'" x-cloak class="contents">
        <x-form.field name="registered_name" label="نام ثبتی شرکت"><x-form.input name="registered_name" :value="old('registered_name', $profile['registered_name'] ?? '')" maxlength="200" /></x-form.field>
        <x-form.field name="company_national_id" label="شناسه ملی شرکت"><x-form.input name="company_national_id" ltr :value="old('company_national_id', $profile['company_national_id'] ?? '')" maxlength="11" /></x-form.field>
        <x-form.field name="economic_code" label="کد اقتصادی"><x-form.input name="economic_code" ltr :value="old('economic_code', $profile['economic_code'] ?? '')" maxlength="14" /></x-form.field>
        <x-form.field name="representative_national_code" label="کد ملی نماینده"><x-form.input name="representative_national_code" ltr :value="old('representative_national_code', $profile['representative_national_code'] ?? '')" maxlength="10" /></x-form.field>
        <x-form.field name="representative_birth_date" label="تاریخ تولد نماینده (شمسی)"><x-form.input name="representative_birth_date" ltr :value="old('representative_birth_date', $profile['representative_birth_date'] ?? '')" placeholder="1370/01/15" /></x-form.field>
    </div>
    <x-form.field name="postal_code" label="کد پستی"><x-form.input name="postal_code" ltr :value="old('postal_code', $profile['postal_code'] ?? '')" maxlength="10" /></x-form.field>
    <x-form.field name="landline" label="تلفن ثابت"><x-form.input name="landline" ltr :value="old('landline', $profile['landline'] ?? '')" maxlength="11" /></x-form.field>
    <div class="sm:col-span-2"><x-form.field name="address" label="نشانی"><x-form.textarea name="address" maxlength="500">{{ old('address', $profile['address'] ?? '') }}</x-form.textarea></x-form.field></div>
    <p class="text-xs text-muted sm:col-span-2">شاهکار، هویت، شرکت و کد پستی پس از ثبت با زحل بررسی می‌شوند. استعلام ناقص یا تنظیم‌نشده به‌عنوان هشدار نمایش داده می‌شود.</p>
</div>
