{{-- Sakht e karbar (P6-04). Classic POST + redirect؛ khata ha az `$errors`
     (x-form.field khodesh neshun mide). Naghsh tasmim e sarih e admin ast —
     `UserAdminAction` ba `forceFill` set esh mikone، na mass-assignment. --}}

<x-layouts.panel title="کاربر جدید" current="customers" :breadcrumbs="['پنل', 'مشتریان', 'جدید']">
    <div class="mx-auto w-full max-w-2xl">
        <h1 class="text-2xl font-extrabold text-ink">کاربر جدید</h1>
        <p class="mt-1 text-sm text-muted">ورود با همین موبایل و کد یکبارمصرف است؛ رمز عبور ندارد.</p>

        <form method="POST" action="{{ route('panel.users.store') }}" class="mt-6 space-y-6" data-user-create-form>
            @csrf

            <section class="rounded-2xl bg-surface card-p-lg shadow-xs">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.field name="phone" label="موبایل" required hint="مثلاً 09121234567">
                        <x-form.input name="phone" ltr inputmode="tel" autocomplete="off" :value="old('phone')" maxlength="32" placeholder="09121234567" />
                    </x-form.field>

                    <x-form.field name="name" label="نام">
                        <x-form.input name="name" :value="old('name')" maxlength="120" />
                    </x-form.field>

                    <x-form.field name="role" label="نقش" required>
                        <x-form.select name="role" :options="auth()->user()->can('access.manage') ? ['customer' => 'مشتری', 'admin' => 'ادمین'] : ['customer' => 'مشتری']" :value="old('role', 'customer')" />
                    </x-form.field>

                    <x-form.field name="email" label="ایمیل"><x-form.input name="email" type="email" ltr :value="old('email')" /></x-form.field>
                </div>
            </section>
            <section class="rounded-2xl bg-surface card-p-lg shadow-xs">
                @include('panel::users.identity-fields', ['profile' => []])
            </section>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('panel.users.index') }}"
                   class="inline-flex h-11 items-center rounded-xl border border-line px-4 text-sm font-medium text-ink hover:bg-surface-2">انصراف</a>
                <button type="submit"
                        class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-on-brand shadow-(--bp-elev-brand) hover:bg-brand-2">
                    <x-icon name="check" class="size-4" />
                    ساخت کاربر
                </button>
            </div>
        </form>
    </div>
</x-layouts.panel>
