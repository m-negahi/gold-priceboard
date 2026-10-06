<x-layouts.panel title="ویرایش کاربر" current="customers" :breadcrumbs="['پنل', 'کاربران', 'ویرایش']">
    <div class="mx-auto w-full max-w-3xl space-y-5">
        <h1 class="text-2xl font-extrabold text-ink">ویرایش کاربر</h1>
        <p class="text-sm text-muted">با تغییر اطلاعات هویتی، شماره موبایل یا نشانی، تأیید فاکتور لغو می‌شود و احراز هویت دوباره لازم است. تغییر ایمیل این تأیید را لغو نمی‌کند.</p>
        <form method="POST" action="{{ route('panel.users.update', $profile['id']) }}" class="space-y-5" data-user-edit-form>
            @csrf
            @method('PUT')
            <section class="rounded-2xl bg-surface card-p-lg shadow-xs space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.field name="name" label="نام"><x-form.input name="name" :value="old('name', $profile['name'])" /></x-form.field>
                    <x-form.field name="phone" label="موبایل" hint="با تغییر شماره، تأیید قبلی شماره حذف می‌شود."><x-form.input name="phone" ltr :value="old('phone', $profile['phone'])" /></x-form.field>
                    <x-form.field name="email" label="ایمیل"><x-form.input name="email" type="email" ltr :value="old('email', $profile['email'])" /></x-form.field>
                    <x-form.field name="level" label="سطح"><x-form.select name="level" :options="$levels" :value="old('level', $profile['level'])" /></x-form.field>
                </div>
                @include('panel::users.identity-fields')
            </section>
            @can('users.manage')<x-panel.button type="submit">ذخیره و بررسی با زحل</x-panel.button>@endcan
            <a wire:navigate href="{{ route('panel.users.show', $profile['id']) }}" class="text-sm text-brand-700">بازگشت به کاربر</a>
        </form>
        @include('kyc::verification-report')
        <section class="rounded-2xl bg-surface card-p shadow-xs">
            <h2 class="text-lg font-bold text-ink">تاریخچه تغییرات</h2>
            @forelse ($audits as $audit)
                <details class="mt-3 rounded-xl border border-line p-3">
                    <summary class="cursor-pointer text-sm text-ink">{{ $audit['actor'] }} · <span class="num">{{ $audit['at'] }}</span></summary>
                    <p class="mt-2 text-xs text-muted">قبل</p><pre class="ltr mt-1 overflow-x-auto whitespace-pre-wrap break-all text-xs">{{ $audit['before'] }}</pre>
                    <p class="mt-2 text-xs text-muted">بعد</p><pre class="ltr mt-1 overflow-x-auto whitespace-pre-wrap break-all text-xs">{{ $audit['after'] }}</pre>
                </details>
            @empty <p class="mt-3 text-sm text-muted">تغییری ثبت نشده است.</p> @endforelse
        </section>
    </div>
</x-layouts.panel>
