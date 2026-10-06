<x-form.field name="{{ $typeField ?? 'type' }}" label="نوع درخواست"><x-form.select name="{{ $typeField ?? 'type' }}" :options="['support' => 'پشتیبانی', 'kyc_upgrade' => 'احراز هویت / فاکتور رسمی']" :value="old($typeField ?? 'type', 'support')" x-model="type" /></x-form.field>
<x-form.field name="subject" label="موضوع" required><x-form.input name="subject" :value="old('subject')" maxlength="200" /></x-form.field>
<x-form.field name="body" label="متن پیام" required><x-form.textarea name="body" rows="5" maxlength="5000">{{ old('body') }}</x-form.textarea></x-form.field>
<div x-show="type === 'kyc_upgrade'" x-cloak class="space-y-4">
    <x-form.field name="name" label="نام و نام خانوادگی شخص / نماینده" required><x-form.input name="name" :value="old('name')" maxlength="120" /></x-form.field>@include('panel::users.identity-fields', ['profile' => []])</div>
<x-form.field name="files" label="پیوست‌ها" :error="$errors->first('files') ?: $errors->first('files.*')" hint="حداکثر 5 فایل، هر فایل 5 مگابایت و مجموعاً 15 مگابایت؛ PDF/JPG/PNG/WEBP.">
    <x-form.file />
</x-form.field>
