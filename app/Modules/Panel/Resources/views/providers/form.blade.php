{{-- Form e sakht/virayesh e manba' (P3-06). Classic POST + redirect؛ khata
     ha az `$errors` miad (x-form.field khodesh neshun mide).
     Logo az ketabkhane e Media entekhab mishe. --}}

@php
    $editing = $mode === 'edit';
    $old = static fn (string $field, mixed $default = null) => old($field, $values[$field] ?? $default);
@endphp

<x-layouts.panel :title="$editing ? 'ویرایش منبع' : 'منبع جدید'" current="providers"
                 :breadcrumbs="['پنل', 'منابع قیمت', $editing ? 'ویرایش' : 'جدید']">
    <div class="mx-auto w-full max-w-3xl">
        <h1 class="text-2xl font-extrabold text-ink">{{ $editing ? 'ویرایش منبع' : 'منبع جدید' }}</h1>
        @if ($editing)
            <p class="ltr mt-1 text-sm text-muted-3">{{ $values['key'] }}</p>
        @endif

        <form method="POST"
              action="{{ $editing ? route('panel.providers.update', $values['key']) : route('panel.providers.store') }}"
              class="mt-6 space-y-6">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <x-form.field name="logo_media_id" label="لوگوی منبع">
                <x-form.media-picker name="logo_media_id" :label="null" :value="$old('logo_media_id')" :media="$logo" />
            </x-form.field>

            <section class="rounded-2xl bg-surface card-p-lg shadow-xs">
                <h2 class="text-base font-bold text-ink">شناسه و نام</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-form.field name="key" label="کلید" :required="! $editing"
                                  hint="لاتین کوچک؛ بعد از ساخت قفل می‌شود (هویت Influx/Redis).">
                        <x-form.input name="key" ltr :value="$old('key')" :readonly="$editing" maxlength="32" placeholder="wallgold" />
                    </x-form.field>

                    <x-form.field name="driver" label="درایور" required
                                  hint="چند منبع می‌توانند یک درایور داشته باشند.">
                        <x-form.select name="driver" :options="array_combine($drivers, $drivers)"
                                       placeholder="انتخاب کنید" :value="$old('driver')" />
                    </x-form.field>

                    <x-form.field name="name" label="نام انگلیسی" required>
                        <x-form.input name="name" ltr :value="$old('name')" maxlength="120" />
                    </x-form.field>

                    <x-form.field name="name_fa" label="نام فارسی" required>
                        <x-form.input name="name_fa" :value="$old('name_fa')" maxlength="120" />
                    </x-form.field>

                    <x-form.field name="kind" label="نوع" required
                                  hint="پلتفرم یعنی خرید/فروش واقعی دارد و نرخش «محاسبه‌شده» علامت نمی‌خورد.">
                        <x-form.select name="kind" :options="$kinds" :value="$old('kind', 'reference')" />
                    </x-form.field>

                    <x-form.field name="website" label="وب‌سایت">
                        <x-form.input name="website" ltr type="url" :value="$old('website')" placeholder="https://..." />
                    </x-form.field>
                </div>
            </section>

            <section class="rounded-2xl bg-surface card-p-lg shadow-xs">
                <h2 class="text-base font-bold text-ink">زمان‌بندی و واحد</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-form.field name="poll_interval_seconds" label="بازه poll (ثانیه)" required>
                        <x-form.number name="poll_interval_seconds" ltr :value="$old('poll_interval_seconds', 15)" />
                    </x-form.field>

                    <x-form.field name="timeout" label="مهلت پاسخ (ثانیه)" required
                                  hint="سقف ۶۰ — همه‌ی منبع‌ها منتظر کندترین می‌مانند.">
                        <x-form.number name="timeout" ltr :value="$old('timeout', 10)" />
                    </x-form.field>

                    <x-form.field name="freshness_seconds" label="سقف تازگی (ثانیه)" required
                                  hint="قدیمی‌تر از این، «کهنه» است. هر نماد می‌تواند در اسپردها override شود.">
                        <x-form.number name="freshness_seconds" ltr :value="$old('freshness_seconds', 900)" />
                    </x-form.field>

                    <x-form.field name="hide_after_seconds" label="مخفی بعد از (ثانیه)" required
                                  hint="نسبی به تازگی: سقف تازگی + این مقدار = حذف از تابلو.">
                        <x-form.number name="hide_after_seconds" ltr :value="$old('hide_after_seconds', 600)" />
                    </x-form.field>

                    <x-form.field name="upstream_unit" label="واحد پیش‌فرض feed" required
                                  hint="فقط پیش‌فرض — واحد هر نماد در map درایور است.">
                        <x-form.select name="upstream_unit" :options="$units" :value="$old('upstream_unit', 'rial')" />
                    </x-form.field>

                    <x-form.field name="sort" label="ترتیب نمایش" required>
                        <x-form.number name="sort" ltr :value="$old('sort', 0)" />
                    </x-form.field>
                </div>
            </section>

            <section class="rounded-2xl bg-surface card-p-lg shadow-xs">
                <h2 class="text-base font-bold text-ink">نمایش</h2>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <x-form.toggle name="is_trust_anchor" label="مرجع اعتماد قیمت"
                                   :checked="(bool) $old('is_trust_anchor', false)" />
                    <p class="text-xs text-muted">اولین قیمت منابع جدید فقط با مرجع اعتماد سنجیده می‌شود. برای منبع دستی فعال نکنید.</p>
                    <x-form.toggle name="is_active" label="فعال"
                                   description="منبع جدید را بعد از یک tick سالم روشن کنید."
                                   :checked="(bool) $old('is_active', false)" />
                    <x-form.toggle name="in_comparison" label="در جدول مقایسه"
                                   :checked="(bool) $old('in_comparison', true)" />
                    <x-form.toggle name="on_board" label="روی تابلو"
                                   :checked="(bool) $old('on_board', true)" />
                    <x-form.toggle name="in_chart" label="در نمودار"
                                   :checked="(bool) $old('in_chart', true)" />
                </div>
            </section>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('panel.providers.index') }}"
                   class="inline-flex h-11 items-center rounded-xl border border-line px-4 text-sm font-medium text-ink hover:bg-surface-2">انصراف</a>
                <button type="submit"
                        class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-on-brand shadow-(--bp-elev-brand) hover:bg-brand-2">
                    <x-icon name="check" class="size-4" />
                    {{ $editing ? 'ذخیره تغییرات' : 'ساخت منبع' }}
                </button>
            </div>
        </form>
    </div>
    <x-panel.media-library />
</x-layouts.panel>
