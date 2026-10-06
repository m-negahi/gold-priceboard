<x-layouts.panel :title="$plan ? 'ویرایش پلن' : 'پلن جدید'" current="plans" :breadcrumbs="['پنل', 'پلن‌های API', $plan ? 'ویرایش' : 'جدید']">
    <a href="{{ route('panel.plans.index') }}" wire:navigate class="text-sm text-brand">بازگشت به پلن‌ها</a>
    <h1 class="mt-2 text-2xl font-extrabold">{{ $plan ? 'ویرایش پلن' : 'پلن جدید' }}</h1>
    @if ($errors->any())<p role="alert" class="mt-4 rounded-xl bg-down-soft p-3 text-sm text-down">{{ $errors->first() }}</p>@endif
    <form method="POST" action="{{ $plan ? route('panel.plans.update', $plan['id']) : route('panel.plans.store') }}" class="mt-5 grid max-w-4xl gap-4 rounded-2xl bg-surface card-p shadow-xs sm:grid-cols-2">
        @csrf
        @if ($plan)@method('PUT')@endif
        <x-form.field name="key" label="کلید لاتین" required><x-form.input name="key" ltr :value="old('key', $plan['key'] ?? '')" /></x-form.field>
        <x-form.field name="name" label="نام پلن" required><x-form.input name="name" :value="old('name', $plan['name'] ?? '')" /></x-form.field>
        <x-form.field name="duration_days" label="مدت (روز)" required><x-form.input name="duration_days" ltr inputmode="numeric" :value="old('duration_days', $plan['durationDays'] ?? 30)" /></x-form.field>
        <x-form.field name="calls_included" label="فراخوانی مشمول دوره" required><x-form.input name="calls_included" ltr inputmode="numeric" :value="old('calls_included', $plan['callsIncluded'] ?? 0)" /></x-form.field>
        <x-form.field name="calls_per_day" label="فراخوانی مشمول هر روز" required><x-form.input name="calls_per_day" ltr inputmode="numeric" :value="old('calls_per_day', $plan['callsPerDay'] ?? 0)" /></x-form.field>
        <x-form.field name="price_rial" label="قیمت اشتراک (ریال)" required><x-form.input name="price_rial" ltr inputmode="numeric" :value="old('price_rial', $plan['priceMinor'] ?? 0)" /></x-form.field>
        <x-form.field name="cost_per_call_rial" label="هزینه مازاد هر فراخوانی (ریال)" required><x-form.input name="cost_per_call_rial" ltr inputmode="numeric" :value="old('cost_per_call_rial', $plan['costPerCallMinor'] ?? 0)" /></x-form.field>
        <x-form.field name="sort" label="ترتیب" required><x-form.input name="sort" ltr inputmode="numeric" :value="old('sort', $plan['sort'] ?? 0)" /></x-form.field>
        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan['isActive'] ?? true))> فعال</label>
        <div class="sm:col-span-2"><button class="h-11 rounded-xl bg-brand px-5 font-semibold text-on-brand">ذخیره پلن</button></div>
    </form>
    @if ($plan && $plan['isActive'])
        <form method="POST" action="{{ route('panel.plans.archive', $plan['id']) }}" class="mt-4">@csrf<button class="h-11 rounded-xl bg-down-soft px-4 text-sm font-semibold text-down">بایگانی پلن</button></form>
    @endif
</x-layouts.panel>
