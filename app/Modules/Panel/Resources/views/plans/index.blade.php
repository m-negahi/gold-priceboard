<x-layouts.panel title="پلن‌های API" current="plans" :breadcrumbs="['پنل', 'پلن‌های API']">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-extrabold text-ink">پلن‌های API</h1><p class="mt-1 text-sm text-muted">مبلغ‌ها ریال هستند. تغییر پلن، اشتراک‌های فروخته‌شده را تغییر نمی‌دهد.</p></div>
        @can('plans.manage')<a href="{{ route('panel.plans.create') }}" wire:navigate class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand"><x-icon name="plus" class="size-4" /> پلن جدید</a>@endcan
    </div>
    <section class="mt-5 overflow-hidden rounded-2xl bg-surface shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[55rem] text-sm">
                <thead><tr class="border-b border-line text-xs text-muted"><th class="px-4 py-3 text-start">پلن</th><th class="px-4 py-3 text-start">مدت</th><th class="px-4 py-3 text-start">سهمیه دوره</th><th class="px-4 py-3 text-start">سهمیه روزانه</th><th class="px-4 py-3 text-start">قیمت (ریال)</th><th class="px-4 py-3 text-start">مازاد هر فراخوانی (ریال)</th><th class="px-4 py-3 text-start">وضعیت</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-line-2">
                    @forelse ($plans as $plan)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $plan['name'] }}<p class="ltr text-xs font-normal text-muted">{{ $plan['key'] }}</p></td>
                            <td class="num px-4 py-3">{{ $plan['durationDays'] }} روز</td>
                            <td class="num px-4 py-3">{{ $plan['callsIncluded'] }}</td>
                            <td class="num px-4 py-3">{{ $plan['callsPerDay'] }}</td>
                            <td class="num px-4 py-3">{{ $plan['priceRial'] }}</td>
                            <td class="num px-4 py-3">{{ $plan['costPerCallRial'] }}</td>
                            <td class="px-4 py-3"><x-panel.pill :tone="$plan['isActive'] ? 'ok' : 'flat'">{{ $plan['isActive'] ? 'فعال' : 'بایگانی' }}</x-panel.pill></td>
                            <td class="px-4 py-3">@can('plans.manage')<a href="{{ route('panel.plans.edit', $plan['id']) }}" wire:navigate class="text-brand">ویرایش</a>@endcan</td>
                        </tr>
                    @empty<tr><td colspan="8" class="px-4 py-8 text-center text-muted">پلنی تعریف نشده است.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.panel>
