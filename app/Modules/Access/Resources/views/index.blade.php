<x-layouts.panel title="نقش و دسترسی" current="access" :breadcrumbs="['پنل', 'نقش و دسترسی']">
    <x-panel.page-header title="نقش و دسترسی" description="نقش‌ها را تعریف کنید و دسترسی هر مدیر را مشخص کنید." />
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-danger bg-danger-soft p-4 text-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('success'))<p class="mb-4 rounded-xl bg-brand-soft p-4 text-brand">{{ session('success') }}</p>@endif
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="space-y-4">
            <h2 class="text-lg font-bold text-ink">نقش‌ها</h2>
            @foreach ($roles as $role)
                <details class="rounded-2xl bg-surface p-5 shadow-xs">
                    <summary class="min-h-11 cursor-pointer font-semibold text-ink">{{ $role->name }}</summary>
                    <form method="POST" action="{{ route('panel.access.roles.update', $role->id) }}" class="mt-4 space-y-4">
                        @csrf @method('PUT')
                        <x-form.field name="name" label="نام نقش"><x-form.input name="name" :value="$role->name" required maxlength="100" /></x-form.field>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($permissions as $key => $label)
                                <x-form.checkbox name="permissions[]" :id="'role-'.$role->id.'-'.$loop->index" :value="$key" :label="$label" :checked="in_array($key, $role->permissions, true)" class="min-h-11" />
                            @endforeach
                        </div>
                        <button class="min-h-11 rounded-xl bg-brand px-5 text-on-brand">ذخیره نقش</button>
                    </form>
                    <form method="POST" action="{{ route('panel.access.roles.destroy', $role->id) }}" class="mt-3">
                        @csrf @method('DELETE')<button class="min-h-11 rounded-xl border border-danger px-4 text-danger">حذف نقش بدون استفاده</button>
                    </form>
                </details>
            @endforeach
            <form method="POST" action="{{ route('panel.access.roles.store') }}" class="space-y-4 rounded-2xl bg-surface p-5 shadow-xs" data-role-create>
                @csrf
                <h3 class="font-semibold text-ink">نقش جدید</h3>
                <x-form.field name="name" label="نام نقش"><x-form.input name="name" :value="old('name')" required maxlength="100" /></x-form.field>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($permissions as $key => $label)
                        <x-form.checkbox name="permissions[]" :id="'new-role-'.$loop->index" :value="$key" :label="$label" class="min-h-11" />
                    @endforeach
                </div>
                <button class="min-h-11 rounded-xl bg-brand px-5 text-on-brand">ساخت نقش</button>
            </form>
        </section>
        <section class="space-y-4">
            <h2 class="text-lg font-bold text-ink">دسترسی مدیران</h2>
            <p class="text-sm text-muted">دسترسی کامل همه بخش‌ها را باز می‌کند. با خاموش بودن آن، فقط مجوزهای نقش‌های انتخاب‌شده اعمال می‌شود.</p>
            @foreach ($admins as $admin)
                <form method="POST" action="{{ route('panel.access.assign', $admin->id) }}" class="space-y-4 rounded-2xl bg-surface p-5 shadow-xs" data-admin-access="{{ $admin->id }}">
                    @csrf @method('PUT')
                    <h3 class="font-semibold text-ink">{{ $admin->name ?: $admin->phone }}</h3>
                    <x-form.checkbox name="full_access" :id="'full-'.$admin->id" label="دسترسی کامل" :checked="!isset($profiles[$admin->id]) || $profiles[$admin->id]->full_access" class="min-h-11" />
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($roles as $role)
                            <x-form.checkbox name="roles[]" :id="'assign-'.$admin->id.'-'.$role->id" :value="$role->id" :label="$role->name" :checked="in_array($role->id, $assignments[$admin->id] ?? [])" class="min-h-11" />
                        @endforeach
                    </div>
                    <button class="min-h-11 rounded-xl bg-brand px-5 text-on-brand">ذخیره دسترسی</button>
                </form>
            @endforeach
            <section class="rounded-2xl bg-surface p-5 shadow-xs">
                <h3 class="font-semibold text-ink">آخرین تغییرها</h3>
                @forelse ($audits as $audit)
                    <p class="mt-3 break-words text-sm text-muted num">{{ $audit->created_at }} · {{ $audit->action }} · {{ $audit->actor_id }}</p>
                @empty <p class="mt-3 text-sm text-muted">تغییری ثبت نشده است.</p> @endforelse
            </section>
        </section>
    </div>
</x-layouts.panel>
